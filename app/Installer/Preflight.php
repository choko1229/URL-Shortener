<?php

declare(strict_types=1);

namespace App\Installer;

use Throwable;

/**
 * 未インストール時に、Laravel を起動する前の準備を行う（public/index.php から呼び出す）。
 *
 * - Laravel が書き込むディレクトリの権限確認
 * - .env が無ければ .env.example から生成（APP_KEY を発行し、DB 不要のセッション設定にする）
 *
 * Laravel 起動前のためフレームワークの機能（ログ・設定・ビュー）は使わない。
 */
final class Preflight
{
    /** @var list<string> Laravel が書き込むディレクトリ（設置フォルダからの相対パス） */
    public const WRITABLE_DIRECTORIES = [
        'bootstrap/cache',
        'storage/app/private',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
    ];

    public function __construct(private readonly string $basePath) {}

    public function isInstalled(): bool
    {
        return is_file($this->basePath.'/storage/'.InstallationState::LOCK_FILE);
    }

    /**
     * @return list<string> 利用者に伝える問題点。空なら Laravel を起動してよい
     */
    public function prepare(bool $secureRequest): array
    {
        $problems = [];

        foreach (self::WRITABLE_DIRECTORIES as $directory) {
            $path = $this->basePath.'/'.$directory;

            if (! is_dir($path) || ! is_writable($path)) {
                $problems[] = "{$directory} フォルダに書き込めません。パーミッションを確認してください（例: 755 または 775）。";
            }
        }

        $environment = new EnvironmentFile($this->basePath.'/.env');

        if (! $environment->exists()) {
            try {
                $environment->write($this->installTimeValues($secureRequest), $this->template());
            } catch (Throwable $e) {
                error_log('[chok.ooo] .env を作成できませんでした: '.$e->getMessage());
                $problems[] = '設定ファイル（.env）を作成できません。設置フォルダの書き込み権限を確認してください。';
            }
        }

        return $problems;
    }

    /**
     * HTTPS でアクセスされているか（Laravel の信頼済みプロキシ設定に依存しない簡易判定）
     *
     * @param  array<string, mixed>  $server  $_SERVER
     */
    public static function isSecureRequest(array $server): bool
    {
        $https = strtolower((string) ($server['HTTPS'] ?? ''));

        return ($https !== '' && $https !== 'off')
            || strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (string) ($server['SERVER_PORT'] ?? '') === '443';
    }

    /** @param  list<string>  $problems */
    public static function renderFailurePage(array $problems): string
    {
        $items = implode('', array_map(
            static fn (string $problem): string => '<li>'.htmlspecialchars($problem, ENT_QUOTES, 'UTF-8').'</li>',
            $problems,
        ));

        return <<<HTML
            <!DOCTYPE html>
            <html lang="ja">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
            <title>セットアップを開始できません | chok.ooo</title>
            <style>
            body{margin:0;padding:40px 16px;background:#F4FBFF;color:#0F2A3D;font-family:'Noto Sans JP',system-ui,sans-serif;line-height:1.7}
            main{max-width:640px;margin:0 auto;background:#fff;border-radius:24px;padding:32px;box-shadow:0 12px 32px rgba(15,42,61,.08)}
            h1{font-size:20px;margin:0 0 12px}li{margin:6px 0}p{color:#5E7A87;font-size:14px}
            </style>
            </head>
            <body>
            <main>
            <h1>セットアップを開始できません</h1>
            <p>サーバーの設定に次の問題があります。解決してからページを再読み込みしてください。</p>
            <ul>{$items}</ul>
            </main>
            </body>
            </html>
            HTML;
    }

    /** @return array<string, string|bool|null> */
    private function installTimeValues(bool $secureRequest): array
    {
        return [
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            // DB 未設定でも動くよう、セットアップ中はファイルを使う（完了時に database へ切り替える）
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            // 設置先のドメインが未確定のため Cookie のドメインは指定しない
            'SESSION_DOMAIN' => null,
            'SESSION_SECURE_COOKIE' => $secureRequest,
        ];
    }

    private function template(): string
    {
        $path = $this->basePath.'/.env.example';
        $contents = is_file($path) ? @file_get_contents($path) : false;

        return is_string($contents) ? $contents : '';
    }
}
