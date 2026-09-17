<?php

declare(strict_types=1);

namespace App\Installer;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** セットアップ画面の「動作環境の確認」 */
final class RequirementChecker
{
    private const MINIMUM_PHP_VERSION = '8.2.0';

    /** @var list<string> Laravel 12 と本アプリが必要とする PHP 拡張 */
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring',
        'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml',
    ];

    /**
     * 外部から見えてはいけないファイル（設置フォルダ直下の URL パス => ファイル固有の文字列）。
     * ステータスコードだけでは、セットアップ画面へのリダイレクトや将来の短縮コード用ルートと区別できないため、
     * 応答にファイルの中身が含まれているかで判定する。
     *
     * @var array<string, string>
     */
    private const PROTECTED_FILES = [
        '/.env' => 'APP_KEY=',
        '/composer.json' => '"laravel/framework"',
        '/artisan' => 'Illuminate\Foundation',
    ];

    private const PROBE_TIMEOUT_SECONDS = 3;

    public function __construct(private readonly Application $app) {}

    /** @return list<RequirementResult> */
    public function check(string $siteUrl, bool $secureRequest): array
    {
        return [
            $this->phpVersion(),
            $this->extensions(),
            $this->writablePaths(),
            $this->https($secureRequest),
            $this->protectedFiles(rtrim($siteUrl, '/')),
        ];
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

    /**
     * .env などが Web から閲覧できないか、実際に HTTP でアクセスして確認する。
     * public_html 直下に設置した場合は、直下の .htaccess が機能していることの確認になる。
     */
    private function protectedFiles(string $siteUrl): RequirementResult
    {
        $label = '設定ファイルが外部から見えないこと';
        $urls = [];
        $exposed = [];
        $unreachable = false;

        foreach (self::PROTECTED_FILES as $path => $marker) {
            $url = $siteUrl.$path;
            $urls[] = $url;

            try {
                $response = Http::timeout(self::PROBE_TIMEOUT_SECONDS)
                    ->connectTimeout(self::PROBE_TIMEOUT_SECONDS)
                    ->withoutRedirecting()
                    ->get($url);
            } catch (ConnectionException $e) {
                Log::warning('セットアップ: 公開状態を確認するためのアクセスに失敗しました。', ['url' => $url, 'error' => $e->getMessage()]);
                $unreachable = true;

                continue;
            }

            if ($response->successful() && str_contains($response->body(), $marker)) {
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
                $urls,
            );
        }

        return new RequirementResult($label, RequirementStatus::Passed);
    }
}
