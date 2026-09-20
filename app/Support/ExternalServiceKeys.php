<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * 外部サービス（Google Safe Browsing・reCAPTCHA）のキー。
 * セットアップ画面で app_settings に保存したものを読み出す（機密値は暗号化済み）。
 */
final class ExternalServiceKeys
{
    /** @var array<string, string|null> リクエスト内キャッシュ */
    private array $resolved = [];

    public function safeBrowsingApiKey(): ?string
    {
        return $this->string(AppSetting::SAFE_BROWSING_API_KEY);
    }

    public function recaptchaSiteKey(): ?string
    {
        return $this->string(AppSetting::RECAPTCHA_SITE_KEY);
    }

    public function recaptchaProjectId(): ?string
    {
        return $this->string(AppSetting::RECAPTCHA_PROJECT_ID);
    }

    public function recaptchaApiKey(): ?string
    {
        return $this->string(AppSetting::RECAPTCHA_API_KEY);
    }

    public function discordClientId(): ?string
    {
        return $this->string(AppSetting::DISCORD_CLIENT_ID);
    }

    public function discordClientSecret(): ?string
    {
        return $this->string(AppSetting::DISCORD_CLIENT_SECRET);
    }

    public function githubToken(): ?string
    {
        return $this->string(AppSetting::UPDATE_GITHUB_TOKEN);
    }

    public function discordWebhookUrl(): ?string
    {
        return $this->string(AppSetting::DISCORD_WEBHOOK_URL);
    }

    /** 保存後に同じリクエスト内で読み直せるよう、キャッシュを破棄する */
    public function forget(): void
    {
        $this->resolved = [];
    }

    private function string(string $key): ?string
    {
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        try {
            $value = AppSetting::valueFor($key);
        } catch (QueryException $e) {
            Log::warning('外部サービスのキーを読み込めませんでした。', ['key' => $key, 'exception' => $e::class]);
            $value = null;
        }

        return $this->resolved[$key] = is_string($value) && $value !== '' ? $value : null;
    }
}
