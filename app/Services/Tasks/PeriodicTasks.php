<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\AppSetting;
use App\Services\Update\UpdateOutcome;
use App\Services\Update\Updater;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 1 日 1 回の定期処理（requirements.md 7-1: 自動アップデートの確認）。
 * サーバーの cron が無くても動くよう、WordPress の WP-Cron と同じくサイトへのアクセスをきっかけに実行する。
 * cron（php artisan schedule:run）が設定されていればそちらから実行する。どちらから呼ばれても 1 日 1 回だけ動く。
 */
class PeriodicTasks
{
    public const LAST_RUN_KEY = 'tasks.update.last_run_at';

    public const LAST_TRIGGER_KEY = 'tasks.update.last_trigger';

    public const CRON_HEARTBEAT_KEY = 'tasks.cron_heartbeat_at';

    // 日本時間の 4:00 を過ぎたら、その日の分を実行する
    private const RUN_HOUR = 4;

    private const TIMEZONE = 'Asia/Tokyo';

    // cron が最後に動いてからこの時間以内なら、アクセスでの実行は行わない
    private const CRON_ACTIVE_MINUTES = 10;

    private const LOCK_KEY = 'chok-ooo:periodic-tasks';

    private const LOCK_SECONDS = 3600;

    public function __construct(private readonly Updater $updater) {}

    public function isDue(CarbonImmutable $now): bool
    {
        $lastRun = $this->timestamp(self::LAST_RUN_KEY);

        return $lastRun === null || $lastRun->lessThan($this->latestBoundary($now));
    }

    /**
     * 実行する時刻を過ぎていれば実行する。実行しなかった場合は null
     *
     * @param  'cron'|'web'  $trigger
     */
    public function runDue(string $trigger, CarbonImmutable $now): ?UpdateOutcome
    {
        if (! $this->isDue($now)) {
            return null;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);
        if (! $lock->get()) {
            return null;
        }

        try {
            // ロック取得までに別のプロセスが実行済みでないか確認する
            if (! $this->isDue($now)) {
                return null;
            }

            // 失敗してもアクセスのたびに再実行しないよう、先に実行時刻を記録する（次は翌日）
            AppSetting::store(self::LAST_RUN_KEY, $now->toIso8601String());
            AppSetting::store(self::LAST_TRIGGER_KEY, $trigger);

            Log::info('定期処理を実行します。', ['trigger' => $trigger]);

            return $this->updater->run();
        } catch (Throwable $e) {
            Log::error('定期処理でエラーが発生しました。', ['exception' => $e::class, 'error' => $e->getMessage()]);

            return null;
        } finally {
            $lock->release();
        }
    }

    public function recordCronHeartbeat(CarbonImmutable $now): void
    {
        AppSetting::store(self::CRON_HEARTBEAT_KEY, $now->toIso8601String());
    }

    public function cronIsActive(CarbonImmutable $now): bool
    {
        $heartbeat = $this->timestamp(self::CRON_HEARTBEAT_KEY);

        return $heartbeat !== null && $heartbeat->greaterThan($now->subMinutes(self::CRON_ACTIVE_MINUTES));
    }

    public function lastRunAt(): ?CarbonImmutable
    {
        return $this->timestamp(self::LAST_RUN_KEY);
    }

    public function lastTrigger(): ?string
    {
        $value = AppSetting::valueFor(self::LAST_TRIGGER_KEY);

        return is_string($value) ? $value : null;
    }

    /** 現在時刻以前で最も新しい「日本時間 4:00」 */
    private function latestBoundary(CarbonImmutable $now): CarbonImmutable
    {
        $local = $now->setTimezone(self::TIMEZONE);
        $today = $local->setTime(self::RUN_HOUR, 0);

        return ($local->lessThan($today) ? $today->subDay() : $today)->utc();
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
