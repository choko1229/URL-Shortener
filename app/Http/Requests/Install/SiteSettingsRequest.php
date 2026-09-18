<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use App\Installer\SiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SiteSettingsRequest extends FormRequest
{
    /** ホスト名（ポート・スキームなし）。localhost などの単一ラベルも許可 */
    private const HOST_PATTERN = '/\A(?=.{1,253}\z)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/';

    /** Google の API キー・reCAPTCHA キー（英数字・ハイフン・アンダースコア） */
    private const API_KEY_PATTERN = '/\A[A-Za-z0-9_\-]{20,100}\z/';

    /** @var array<string, string> */
    private const DOMAIN_FIELDS = [
        'main_domain' => 'メインドメイン',
        'dashboard_domain' => 'ダッシュボードのドメイン',
        'api_domain' => 'API のドメイン',
        'redirect_domain' => 'リダイレクト確認のドメイン',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (array_keys(self::DOMAIN_FIELDS) as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) ? strtolower(trim($value)) : $value;
        }

        $this->merge($normalized);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [];

        foreach (array_keys(self::DOMAIN_FIELDS) as $field) {
            $rules[$field] = ['required', 'string', 'regex:'.self::HOST_PATTERN];
        }

        return $rules + [
            'discord_client_id' => ['nullable', 'required_with:discord_client_secret', 'string', 'regex:/\A\d{17,20}\z/'],
            'discord_client_secret' => ['nullable', 'required_with:discord_client_id', 'string', 'min:16', 'max:128', 'regex:/\A[A-Za-z0-9_\-]+\z/'],
            'safe_browsing_api_key' => ['nullable', 'string', 'regex:'.self::API_KEY_PATTERN],
            'recaptcha_site_key' => ['nullable', 'required_with:recaptcha_secret_key', 'string', 'regex:'.self::API_KEY_PATTERN],
            'recaptcha_secret_key' => ['nullable', 'required_with:recaptcha_site_key', 'string', 'regex:'.self::API_KEY_PATTERN],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [
            static function (Validator $validator): void {
                $domains = array_filter(
                    array_intersect_key($validator->getData(), self::DOMAIN_FIELDS),
                    'is_string',
                );

                foreach (array_count_values($domains) as $domain => $count) {
                    if ($count > 1) {
                        $field = (string) array_search((string) $domain, $domains, true);
                        $validator->errors()->add($field, '4つのドメインにはそれぞれ異なる値を指定してください。');
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [
            'discord_client_id.required_with' => 'Client Secret を入力した場合は Client ID も入力してください。',
            'discord_client_id.regex' => 'Client ID は 17〜20 桁の数字です。',
            'discord_client_secret.required_with' => 'Client ID を入力した場合は Client Secret も入力してください。',
            'discord_client_secret.min' => 'Client Secret が短すぎます。コピーした値を確認してください。',
            'discord_client_secret.max' => 'Client Secret が長すぎます。コピーした値を確認してください。',
            'discord_client_secret.regex' => 'Client Secret の形式が正しくありません。',
            'safe_browsing_api_key.regex' => 'Safe Browsing の API キーの形式が正しくありません。',
            'recaptcha_site_key.required_with' => 'シークレットキーを入力した場合はサイトキーも入力してください。',
            'recaptcha_site_key.regex' => 'reCAPTCHA のサイトキーの形式が正しくありません。',
            'recaptcha_secret_key.required_with' => 'サイトキーを入力した場合はシークレットキーも入力してください。',
            'recaptcha_secret_key.regex' => 'reCAPTCHA のシークレットキーの形式が正しくありません。',
        ];

        foreach (self::DOMAIN_FIELDS as $field => $label) {
            $messages["{$field}.required"] = "{$label}を入力してください。";
            $messages["{$field}.regex"] = "{$label}は「example.com」の形式で入力してください（https:// やポート番号は不要です）。";
        }

        return $messages;
    }

    public function toSettings(bool $secure): SiteSettings
    {
        return new SiteSettings(
            mainDomain: $this->string('main_domain')->toString(),
            dashboardDomain: $this->string('dashboard_domain')->toString(),
            apiDomain: $this->string('api_domain')->toString(),
            redirectDomain: $this->string('redirect_domain')->toString(),
            secure: $secure,
            discordClientId: $this->filled('discord_client_id') ? $this->string('discord_client_id')->toString() : null,
            discordClientSecret: $this->filled('discord_client_secret') ? $this->string('discord_client_secret')->toString() : null,
            safeBrowsingApiKey: $this->optionalString('safe_browsing_api_key'),
            recaptchaSiteKey: $this->optionalString('recaptcha_site_key'),
            recaptchaSecretKey: $this->optionalString('recaptcha_secret_key'),
        );
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
