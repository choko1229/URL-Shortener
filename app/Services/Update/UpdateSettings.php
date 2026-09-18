<?php

declare(strict_types=1);

namespace App\Services\Update;

use App\Models\AppSetting;
use App\Support\ExternalServiceKeys;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;

/**
 * 自動アップデートの設定（管理画面から変更する。requirements.md 7-2: トークンは暗号化して DB に保存）
 */
final class UpdateSettings
{
    public const REPOSITORY_PATTERN = '/\A[A-Za-z0-9_.\-]+\/[A-Za-z0-9_.\-]+\z/';

    public function __construct(
        private readonly ExternalServiceKeys $keys,
        private readonly Config $config,
    ) {}

    /** 既定は有効（requirements.md 7-1 案A: 完全自動適用）。トークン未設定の間は実行されない */
    public function enabled(): bool
    {
        $value = AppSetting::valueFor(AppSetting::UPDATE_ENABLED);

        return is_bool($value) ? $value : true;
    }

    public function repository(): string
    {
        $value = AppSetting::valueFor(AppSetting::UPDATE_REPOSITORY);

        return is_string($value) && preg_match(self::REPOSITORY_PATTERN, $value) === 1
            ? $value
            : (string) $this->config->get('shortener.update.repository');
    }

    public function githubToken(): ?string
    {
        return $this->keys->githubToken();
    }

    public function discordWebhookUrl(): ?string
    {
        return $this->keys->discordWebhookUrl();
    }

    /**
     * @param  string|null  $githubToken  null なら変更しない、空文字なら削除
     * @param  string|null  $webhookUrl  null なら変更しない、空文字なら削除
     */
    public function save(bool $enabled, string $repository, ?string $githubToken, ?string $webhookUrl): void
    {
        DB::transaction(static function () use ($enabled, $repository, $githubToken, $webhookUrl): void {
            AppSetting::store(AppSetting::UPDATE_ENABLED, $enabled);
            AppSetting::store(AppSetting::UPDATE_REPOSITORY, $repository);

            foreach ([AppSetting::UPDATE_GITHUB_TOKEN => $githubToken, AppSetting::DISCORD_WEBHOOK_URL => $webhookUrl] as $key => $value) {
                if ($value === null) {
                    continue;
                }

                $value === ''
                    ? AppSetting::query()->where('key', $key)->delete()
                    : AppSetting::store($key, $value, encrypt: true);
            }
        });

        $this->keys->forget();
    }
}
