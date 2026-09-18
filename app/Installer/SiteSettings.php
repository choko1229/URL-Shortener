<?php

declare(strict_types=1);

namespace App\Installer;

/** セットアップ画面の「サイト設定」 */
final readonly class SiteSettings
{
    public function __construct(
        public string $mainDomain,
        public string $dashboardDomain,
        public string $apiDomain,
        public string $redirectDomain,
        public bool $secure,
        public ?string $discordClientId,
        public ?string $discordClientSecret,
    ) {}

    public function baseUrl(): string
    {
        return ($this->secure ? 'https' : 'http').'://'.$this->mainDomain;
    }

    public function dashboardUrl(): string
    {
        return ($this->secure ? 'https' : 'http').'://'.$this->dashboardDomain;
    }

    public function hasDiscordCredentials(): bool
    {
        return $this->discordClientId !== null && $this->discordClientSecret !== null;
    }

    /** @return array<string, string|bool|null> */
    public function environmentValues(): array
    {
        return [
            'APP_URL' => $this->baseUrl(),
            'SHORTENER_MAIN_DOMAIN' => $this->mainDomain,
            'SHORTENER_DASHBOARD_DOMAIN' => $this->dashboardDomain,
            'SHORTENER_API_DOMAIN' => $this->apiDomain,
            'SHORTENER_REDIRECT_DOMAIN' => $this->redirectDomain,
            'SHORTENER_SHORT_URL_BASE' => $this->baseUrl(),
            'SESSION_DOMAIN' => $this->sessionCookieDomain(),
            'SESSION_SECURE_COOKIE' => $this->secure,
            // セットアップ中のファイル保存から、本来の DB 保存に切り替える
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
        ];
    }

    /**
     * アクセス中のホスト名から各サブドメインの初期値を推測する
     *
     * @return array{main: string, dashboard: string, api: string, redirect: string}
     */
    public static function suggestDomains(string $host): array
    {
        $main = (string) preg_replace('/\A(?:www|dash|api|redirect)\./', '', strtolower($host));

        return [
            'main' => $main,
            'dashboard' => 'dash.'.$main,
            'api' => 'api.'.$main,
            'redirect' => 'redirect.'.$main,
        ];
    }

    /** ダッシュボードがメインドメインのサブドメインなら、ログイン状態を共有するため Cookie のドメインを広げる */
    private function sessionCookieDomain(): ?string
    {
        return str_ends_with($this->dashboardDomain, '.'.$this->mainDomain) ? '.'.$this->mainDomain : null;
    }
}
