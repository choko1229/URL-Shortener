<?php

declare(strict_types=1);

namespace App\Services\GeoIp;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use RuntimeException;
use Throwable;

/**
 * 国判定のデータベース（DB-IP IP to Country Lite。登録不要・CC BY 4.0・毎月更新）を自動で取得・更新する。
 * 定期処理から呼ばれ、データベースが無ければすぐに、以後は新しい月の版が出たら差し替える。
 * 手動で MaxMind GeoLite2 を置いている場合は何もしない。テストで差し替えられるよう final にしていない。
 */
class GeoIpDatabaseUpdater
{
    public const RELEASE_KEY = 'geoip.release';

    public const UPDATED_AT_KEY = 'geoip.updated_at';

    public const LAST_ATTEMPT_KEY = 'geoip.last_attempt_at';

    private const DOWNLOAD_URL = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';

    // データベースが無い間の再試行間隔（取得に失敗しても毎回のアクセスで試さない）
    private const RETRY_WHEN_MISSING_MINUTES = 60;

    // 新しい月の版を待つ間の再試行間隔
    private const RETRY_WHEN_OUTDATED_HOURS = 24;

    private const TIMEOUT_SECONDS = 120;

    // 異常なファイルでディスクを使い切らないための上限（実際は圧縮時 5MB・展開後 10MB 程度）
    private const MAX_DOWNLOAD_BYTES = 64 * 1024 * 1024;

    private const MAX_DATABASE_BYTES = 256 * 1024 * 1024;

    // 取得したデータベースで国を判定できるかの確認に使う IP（Google Public DNS）
    private const PROBE_IP = '8.8.8.8';

    public function __construct(
        private readonly GeoIpDatabase $database,
        private readonly Config $config,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('shortener.geoip.auto_update') && ! $this->database->hasManualDatabase();
    }

    public function isDue(CarbonImmutable $now): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $lastAttempt = $this->timestamp(self::LAST_ATTEMPT_KEY);

        if (! is_file($this->database->automaticPath)) {
            return $lastAttempt === null || $lastAttempt->lessThanOrEqualTo($now->subMinutes(self::RETRY_WHEN_MISSING_MINUTES));
        }

        if ($this->installedRelease() === self::release($now)) {
            return false;
        }

        return $lastAttempt === null || $lastAttempt->lessThanOrEqualTo($now->subHours(self::RETRY_WHEN_OUTDATED_HOURS));
    }

    /** 今月の版を取得する。まだ公開されていなければ先月の版を使う */
    public function update(CarbonImmutable $now): GeoIpUpdateResult
    {
        if ($this->database->hasManualDatabase()) {
            return GeoIpUpdateResult::skipped('MaxMind GeoLite2 を手動で設置しているため、自動取得は行いません。');
        }

        AppSetting::store(self::LAST_ATTEMPT_KEY, $now->toIso8601String());

        $errors = [];

        foreach ([self::release($now), self::release($now->subMonthNoOverflow())] as $release) {
            if ($release === $this->installedRelease() && is_file($this->database->automaticPath)) {
                return GeoIpUpdateResult::skipped("国判定のデータベースは最新です（{$release} 版）。");
            }

            try {
                $this->install($release);
            } catch (ReleaseNotPublished) {
                continue;
            } catch (Throwable $e) {
                Log::warning('国判定のデータベースを取得できませんでした。', ['release' => $release, 'exception' => $e::class, 'error' => $e->getMessage()]);
                $errors[] = $e->getMessage();

                break;
            }

            AppSetting::store(self::RELEASE_KEY, $release);
            AppSetting::store(self::UPDATED_AT_KEY, $now->toIso8601String());
            Log::info('国判定のデータベースを更新しました。', ['release' => $release]);

            return GeoIpUpdateResult::updated($release);
        }

        return GeoIpUpdateResult::failed(
            '国判定のデータベースを取得できませんでした。'.($errors === [] ? '配布元にデータがありませんでした。' : $errors[0]),
        );
    }

    public function installedRelease(): ?string
    {
        $value = AppSetting::valueFor(self::RELEASE_KEY);

        return is_string($value) ? $value : null;
    }

    public function updatedAt(): ?CarbonImmutable
    {
        return $this->timestamp(self::UPDATED_AT_KEY);
    }

    /**
     * ダウンロード → 展開 → 読み込めるか確認 → 差し替え。途中で失敗しても、使用中のデータベースは残る
     *
     * @throws ReleaseNotPublished 指定した月の版がまだ無い
     * @throws RuntimeException
     */
    private function install(string $release): void
    {
        $target = $this->database->automaticPath;
        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("{$directory} を作成できません。書き込み権限を確認してください。");
        }

        $compressed = $target.'.download.gz';
        $extracted = $target.'.download';

        try {
            $this->download(sprintf(self::DOWNLOAD_URL, $release), $compressed);
            $this->decompress($compressed, $extracted);
            $this->verify($extracted);

            if (! @rename($extracted, $target)) {
                throw new RuntimeException('データベースファイルを差し替えられませんでした。');
            }
        } finally {
            @unlink($compressed);
            @unlink($extracted);
        }
    }

    /** @throws ReleaseNotPublished|RuntimeException */
    private function download(string $url, string $destination): void
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->connectTimeout(10)->sink($destination)->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException('配布元（db-ip.com）に接続できませんでした。', previous: $e);
        }

        if ($response->notFound()) {
            throw new ReleaseNotPublished;
        }

        if (! $response->successful()) {
            throw new RuntimeException("配布元がエラーを返しました（HTTP {$response->status()}）。");
        }

        $size = @filesize($destination);
        if ($size === false || $size === 0 || $size > self::MAX_DOWNLOAD_BYTES) {
            throw new RuntimeException('ダウンロードしたファイルの大きさが想定外です。');
        }
    }

    /** @throws RuntimeException */
    private function decompress(string $source, string $destination): void
    {
        $input = @gzopen($source, 'rb');
        $output = @fopen($destination, 'wb');

        try {
            if ($input === false || $output === false) {
                throw new RuntimeException('ダウンロードしたファイルを展開できませんでした。');
            }

            $written = 0;
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('ダウンロードしたファイルを展開できませんでした。');
                }

                $written += strlen($chunk);
                if ($written > self::MAX_DATABASE_BYTES) {
                    throw new RuntimeException('展開したファイルの大きさが想定外です。');
                }

                fwrite($output, $chunk);
            }
        } finally {
            if (is_resource($input)) {
                gzclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    /** 国のデータベースとして読み込めて、実際に国を判定できることを確かめる @throws RuntimeException */
    private function verify(string $path): void
    {
        try {
            $reader = new Reader($path);

            try {
                $type = $reader->metadata()->databaseType;
                $record = $reader->get(self::PROBE_IP);
            } finally {
                $reader->close();
            }
        } catch (Throwable $e) {
            throw new RuntimeException('取得したファイルを国のデータベースとして読み込めませんでした。', previous: $e);
        }

        $code = is_array($record) && is_array($record['country'] ?? null) ? ($record['country']['iso_code'] ?? null) : null;

        if (! str_contains(strtolower((string) $type), 'country') || ! is_string($code) || preg_match('/\A[A-Z]{2}\z/', $code) !== 1) {
            throw new RuntimeException('取得したファイルの内容が国のデータベースではありませんでした。');
        }
    }

    /** DB-IP の版（毎月 1 日に公開される。UTC の年月） */
    private static function release(CarbonImmutable $time): string
    {
        return $time->utc()->format('Y-m');
    }

    private function timestamp(string $key): ?CarbonImmutable
    {
        $value = AppSetting::valueFor($key);

        if (! is_string($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
