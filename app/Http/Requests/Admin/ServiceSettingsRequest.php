<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AppSetting;
use App\Services\Security\RecaptchaVerifier;
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
            'recaptcha_project_id' => ['nullable', 'string', 'regex:'.ServiceKeyRules::GOOGLE_PROJECT_ID_PATTERN],
            'recaptcha_api_key' => ['nullable', 'string', 'regex:'.ServiceKeyRules::GOOGLE_KEY_PATTERN],
            'clear_recaptcha' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        $keys = $this->container->make(ExternalServiceKeys::class);
        $recaptcha = $this->container->make(RecaptchaVerifier::class);

        return [
            function (Validator $validator) use ($keys): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->filled('discord_client_secret') && $keys->discordClientSecret() === null) {
                    $validator->errors()->add('discord_client_secret', 'Client Secret を入力してください。');
                }

                if (! $this->boolean('clear_recaptcha') && $this->wantsRecaptcha($keys)) {
                    $this->requireRecaptchaFields($validator, $keys);
                }
            },
            // 形式が正しくても、プロジェクト ID と API キーの組み合わせで使えるとは限らないため、保存前に Google で確かめる
            function (Validator $validator) use ($keys, $recaptcha): void {
                if ($validator->errors()->isNotEmpty() || $this->boolean('clear_recaptcha') || ! $this->recaptchaChanged($keys)) {
                    return;
                }

                $error = $recaptcha->configurationError(
                    $this->string('recaptcha_site_key')->trim()->toString(),
                    $this->string('recaptcha_project_id')->trim()->toString(),
                    $this->optional('recaptcha_api_key') ?? (string) $keys->recaptchaApiKey(),
                );

                if ($error !== null) {
                    $validator->errors()->add('recaptcha_api_key', $error);
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
            AppSetting::RECAPTCHA_PROJECT_ID => $clearRecaptcha ? '' : $this->optional('recaptcha_project_id'),
            AppSetting::RECAPTCHA_API_KEY => $clearRecaptcha ? '' : $this->optional('recaptcha_api_key'),
        ];
    }

    /** reCAPTCHA の欄に何か入力されているか、すでに設定済みか */
    private function wantsRecaptcha(ExternalServiceKeys $keys): bool
    {
        return $this->filled('recaptcha_site_key')
            || $this->filled('recaptcha_project_id')
            || $this->filled('recaptcha_api_key')
            || $keys->recaptchaSiteKey() !== null;
    }

    /** キー ID・プロジェクト ID・API キーはそろって初めて使える（API キーは保存済みなら空欄のままでよい） */
    private function requireRecaptchaFields(Validator $validator, ExternalServiceKeys $keys): void
    {
        $hint = '（reCAPTCHA を使わない場合は「reCAPTCHA の設定を削除する」にチェックしてください）';

        if (! $this->filled('recaptcha_site_key')) {
            $validator->errors()->add('recaptcha_site_key', 'キー ID を入力してください'.$hint.'。');
        }

        if (! $this->filled('recaptcha_project_id')) {
            $validator->errors()->add('recaptcha_project_id', 'プロジェクト ID を入力してください'.$hint.'。');
        }

        if (! $this->filled('recaptcha_api_key') && $keys->recaptchaApiKey() === null) {
            $validator->errors()->add('recaptcha_api_key', 'API キーを入力してください。');
        }
    }

    private function recaptchaChanged(ExternalServiceKeys $keys): bool
    {
        return $this->filled('recaptcha_api_key')
            || $this->optional('recaptcha_site_key') !== $keys->recaptchaSiteKey()
            || $this->optional('recaptcha_project_id') !== $keys->recaptchaProjectId();
    }

    private function optional(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
