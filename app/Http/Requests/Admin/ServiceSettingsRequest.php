<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AppSetting;
use App\Support\ExternalServiceKeys;
use App\Support\ServiceKeyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 管理画面「外部サービス」の入力。
 * 機密値の欄は空欄なら変更しない（保存済みの値は画面に出さないため）。
 */
final class ServiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'discord_client_id' => ['required', 'string', 'regex:'.ServiceKeyRules::DISCORD_CLIENT_ID_PATTERN],
            'discord_client_secret' => ['nullable', 'string', 'regex:'.ServiceKeyRules::DISCORD_CLIENT_SECRET_PATTERN],
            'safe_browsing_api_key' => ['nullable', 'string', 'regex:'.ServiceKeyRules::GOOGLE_KEY_PATTERN],
            'clear_safe_browsing_api_key' => ['nullable', 'boolean'],
            'recaptcha_site_key' => ['nullable', 'string', 'regex:'.ServiceKeyRules::GOOGLE_KEY_PATTERN],
            'recaptcha_secret_key' => ['nullable', 'string', 'regex:'.ServiceKeyRules::GOOGLE_KEY_PATTERN],
            'clear_recaptcha' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        $keys = $this->container->make(ExternalServiceKeys::class);

        return [
            function (Validator $validator) use ($keys): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->filled('discord_client_secret') && $keys->discordClientSecret() === null) {
                    $validator->errors()->add('discord_client_secret', 'Client Secret を入力してください。');
                }

                if ($this->boolean('clear_recaptcha')) {
                    return;
                }

                if ($this->filled('recaptcha_site_key')) {
                    if (! $this->filled('recaptcha_secret_key') && $keys->recaptchaSecretKey() === null) {
                        $validator->errors()->add('recaptcha_secret_key', 'シークレットキーを入力してください。');
                    }
                } elseif ($this->filled('recaptcha_secret_key') || $keys->recaptchaSiteKey() !== null) {
                    $validator->errors()->add('recaptcha_site_key', 'サイトキーを入力してください（reCAPTCHA を使わない場合は「reCAPTCHA の設定を削除する」にチェックしてください）。');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ServiceKeyRules::messages();
    }

    /**
     * ExternalServiceSettings::save() に渡す変更内容（null は変更しない、空文字は削除）
     *
     * @return array<string, string|null>
     */
    public function changes(): array
    {
        $clearRecaptcha = $this->boolean('clear_recaptcha');

        return [
            AppSetting::DISCORD_CLIENT_ID => $this->string('discord_client_id')->trim()->toString(),
            AppSetting::DISCORD_CLIENT_SECRET => $this->optional('discord_client_secret'),
            AppSetting::SAFE_BROWSING_API_KEY => $this->boolean('clear_safe_browsing_api_key') ? '' : $this->optional('safe_browsing_api_key'),
            AppSetting::RECAPTCHA_SITE_KEY => $clearRecaptcha ? '' : $this->optional('recaptcha_site_key'),
            AppSetting::RECAPTCHA_SECRET_KEY => $clearRecaptcha ? '' : $this->optional('recaptcha_secret_key'),
        ];
    }

    private function optional(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
