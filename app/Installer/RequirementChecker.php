<?php

declare(strict_types=1);

namespace App\Installer;

use App\Services\Update\PhpBinaryResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * セットアップ画面の「動作環境の確認」。
 * Web からの確認（設定ファイルの露出・サブドメインの向き先）はまとめて並行に行い、待ち時間を短くする。
 */
final class RequirementChecker
{
    private const MINIMUM_PHP_VERSION = '8.2.0';

    /** @var list<string> Laravel 12 と本アプリが必要とする PHP 拡張 */
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring',
        'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'zip',
    ];

    /**
     * 外部から見えてはいけないファイル（設置フォルダ直下の URL パス => ファイル固有の文字列）。
     * ステータスコードだけでは、セットアップ画面へのリダイレクトなどと区別できないため、応答にファイルの中身が含まれるかで判定する。
     *
     * @var array<string, string>
     */
    private const PROTECTED_FILES = [
        '/.env' => 'APP_KEY=',
        '/composer.json' => '"laravel/framework"',
        '/artisan' => 'Illuminate\Foundation',
    ];

    private const PROBE_TIMEOUT_SECONDS = 3;

    public function __construct(
        private readonly Application $app,
        private readonly PhpBinaryResolver $php,
    ) {}

    /**
     * @param  array<string, string>  $subdomainUrls  表示名 => このフォルダを向いているはずの URL（例: 'ダッシュボード' => 'https://dash.example.com'）
     * @return list<RequirementResult>
     */
    public function check(string $siteUrl, bool $secureRequest, array $subdomainUrls = []): array
    {
        $probes = $this->probe(rtrim($siteUrl, '/'), $subdomainUrls);

        return array_values(array_filter([
            $this->phpVersion(),
            $this->extensions(),
            $this->writablePaths(),
            $this->https($secureRequest),
            $this->protectedFiles($probes['files']),
            $subdomainUrls === [] ? null : $this->subdomains($probes['subdomains']),
            $this->backgroundTasks(),
        ]));
    }

    /** セットアップ中のこのサイトであることを示す値（別のサイトの応答と区別するため） */
    public function pingToken(): string
    {
        return hash_hmac('sha256', 'url-shortener-install-ping', (string) $this->app->make('config')->get('app.key'));
    }

    private function phpVersion(): RequirementResult
    {
        $label = 'PHP '.self::MINIMUM_PHP_VERSION.' 以上';

        return version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=')
            ? new RequirementResult($label, RequirementStatus::Passed, '現在のバージョン: '.PHP_VERSION)
            : new RequirementResult($label, RequirementStatus::Failed, '現在のバージョンは '.PHP_VERSION.' です。サーバーの管理画面で PHP 8.2 以上に切り替えてください。');
    }

    private function extensions(): RequirementResult
    {
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, static fn (string $extension): bool => ! extension_loaded($extension)));

        return $missing === []
            ? new RequirementResult('PHP 拡張モジュール', RequirementStatus::Passed)
            : new RequirementResult('PHP 拡張モジュール', RequirementStatus::Failed, '次の拡張モジュールが有効になっていません: '.implode(', ', $missing));
    }

    private function writablePaths(): RequirementResult
    {
        $notWritable = array_values(array_filter(
            Preflight::WRITABLE_DIRECTORIES,
            fn (string $directory): bool => ! is_writable($this->app->basePath($directory)),
        ));

        if (! is_writable($this->app->environmentFilePath())) {
            $notWritable[] = '.env';
        }

        return $notWritable === []
            ? new RequirementResult('書き込み権限', RequirementStatus::Passed)
            : new RequirementResult('書き込み権限', RequirementStatus::Failed, '次のファイル・フォルダに書き込めません: '.implode(', ', $notWritable));
    }

    private function https(bool $secureRequest): RequirementResult
    {
        return $secureRequest
            ? new RequirementResult('HTTPS での接続', RequirementStatus::Passed)
            : new RequirementResult('HTTPS での接続', RequirementStatus::Warning, 'HTTP で接続しています。SSL を設定してから https:// でセットアップすることをおすすめします（Cookie の保護設定に使われます）。');
    }

    /** @param  array<string, Response|null>  $responses  URL => 応答（接続できなければ null） */
    private function protectedFiles(array $responses): RequirementResult
    {
        $label = '設定ファイルが外部から見えないこと';
        $exposed = [];
        $unreachable = false;

        foreach ($responses as $url => $response) {
            if ($response === null) {
                $unreachable = true;

                continue;
            }

            $marker = self::PROTECTED_FILES[(string) parse_url($url, PHP_URL_PATH)] ?? '';
            if ($response->successful() && $marker !== '' && str_contains($response->body(), $marker)) {
                $exposed[] = $url;
            }
        }

        if ($exposed !== []) {
            Log::error('セットアップ: 設定ファイルが外部から閲覧可能な状態です。', ['urls' => $exposed]);

            return new RequirementResult(
                $label,
                RequirementStatus::Failed,
                '次の URL が外部から閲覧できる状態です。.htaccess が有効か（mod_rewrite・AllowOverride）確認するか、公開フォルダを public/ に設定してください。',
                $exposed,
            );
        }

        if ($unreachable) {
            return new RequirementResult(
                $label,
                RequirementStatus::Unknown,
                'サーバー自身から確認できませんでした。次の URL をブラウザで開き、ファイルの中身（「APP_KEY=」などの設定値やソースコード）が表示されないことを確認してください。',
                array_keys($responses),
            );
        }

        return new RequirementResult($label, RequirementStatus::Passed);
    }

    /** @param  array<string, array{url: string, response: Response|null}>  $probes */
    private function subdomains(array $probes): RequirementResult
    {
        $notReady = [];

        foreach ($probes as $name => $probe) {
            $response = $probe['response'];
            if ($response === null || ! $response->successful() || trim($response->body()) !== $this->pingToken()) {
                $notReady[] = "{$name}（".parse_url($probe['url'], PHP_URL_HOST).'）';
            }
        }

        return $notReady === []
            ? new RequirementResult('サブドメインの向き先', RequirementStatus::Passed)
            : new RequirementResult(
                'サブドメインの向き先',
                RequirementStatus::Warning,
                '次のサブドメインがこのフォルダを向いていないか、まだ確認できません: '.implode('、', $notReady).'。サーバーの管理画面で、公開フォルダをこのフォルダに設定してください（後から設定しても構いません）。',
            );
    }

    private function backgroundTasks(): RequirementResult
    {
        return $this->php->resolve() !== null
            ? new RequirementResult('自動アップデートの実行環境', RequirementStatus::Passed)
            : new RequirementResult(
                '自動アップデートの実行環境',
                RequirementStatus::Warning,
                'PHP（CLI）を起動できないため、自動アップデート時のマイグレーション等は Web の処理の中で実行します（動作はします）。',
            );
    }

    /**
     * @param  array<string, string>  $subdomainUrls
     * @return array{files: array<string, Response|null>, subdomains: array<string, array{url: string, response: Response|null}>}
     */
    private function probe(string $siteUrl, array $subdomainUrls): array
    {
        $fileUrls = array_map(static fn (string $path): string => $siteUrl.$path, array_keys(self::PROTECTED_FILES));
        $pingUrls = array_map(static fn (string $url): string => rtrim($url, '/').'/install/ping', $subdomainUrls);
        $targets = array_values(array_merge($fileUrls, array_values($pingUrls)));

        $responses = Http::pool(static fn (Pool $pool): array => array_map(
            static fn (string $url) => $pool->as($url)
                ->timeout(self::PROBE_TIMEOUT_SECONDS)
                ->connectTimeout(self::PROBE_TIMEOUT_SECONDS)
                ->withoutRedirecting()
                ->get($url),
            $targets,
        ));

        $result = static function (string $url) use ($responses): ?Response {
            $response = $responses[$url] ?? null;

            return $response instanceof Response ? $response : null;
        };

        $files = [];
        foreach ($fileUrls as $url) {
            $files[$url] = $result($url);
        }

        $subdomains = [];
        foreach ($pingUrls as $name => $url) {
            $subdomains[$name] = ['url' => $subdomainUrls[$name], 'response' => $result($url)];
        }

        return ['files' => $files, 'subdomains' => $subdomains];
    }
}
