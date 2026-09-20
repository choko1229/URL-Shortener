<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\AppSetting;
use App\Support\ExternalServiceKeys;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 外部サービス（Discord・Safe Browsing・reCAPTCHA）のキーの保存。
 * 読み出しは ExternalServiceKeys。機密値は APP_KEY で暗号化して app_settings に保存する（requirements.md 7-2, 10）。
 */
final class ExternalServiceSettings
{
    /** @var array<string, bool> 保存できるキー => 暗号化するか */
    private const KEYS = [
        AppSetting::DISCORD_CLIENT_ID => false,
        AppSetting::DISCORD_CLIENT_SECRET => true,
        AppSetting::SAFE_BROWSING_API_KEY => true,
        // サイトキーはページに埋め込む公開値のため平文
        AppSetting::RECAPTCHA_SITE_KEY => false,
        AppSetting::RECAPTCHA_PROJECT_ID => false,
        AppSetting::RECAPTCHA_API_KEY => true,
    ];

    public function __construct(private readonly ExternalServiceKeys $keys) {}

    /**
     * @param  array<string, string|null>  $changes  AppSetting のキー => 新しい値（null は変更しない、空文字は削除）
     *
     * @throws InvalidArgumentException 保存対象外のキーが含まれる場合
     */
    public function save(array $changes): void
    {
        foreach (array_keys($changes) as $key) {
            if (! array_key_exists($key, self::KEYS)) {
                throw new InvalidArgumentException("外部サービスの設定として保存できないキーです: {$key}");
            }
        }

        DB::transaction(static function () use ($changes): void {
            foreach ($changes as $key => $value) {
                if ($value === null) {
                    continue;
                }

                $value === ''
                    ? AppSetting::query()->where('key', $key)->delete()
                    : AppSetting::store($key, $value, encrypt: self::KEYS[$key]);
            }
        });

        $this->keys->forget();
    }
}
