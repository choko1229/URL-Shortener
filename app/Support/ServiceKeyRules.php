<?php

declare(strict_types=1);

namespace App\Support;

/** 外部サービスのキーの入力形式（初回の Discord 設定画面と管理画面で共用） */
final class ServiceKeyRules
{
    public const DISCORD_CLIENT_ID_PATTERN = '/\A\d{17,20}\z/';

    public const DISCORD_CLIENT_SECRET_PATTERN = '/\A[A-Za-z0-9_\-]{16,128}\z/';

    /** Google の API キー・reCAPTCHA キー（英数字・ハイフン・アンダースコア） */
    public const GOOGLE_KEY_PATTERN = '/\A[A-Za-z0-9_\-]{20,100}\z/';

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'discord_client_id.required' => 'Client ID を入力してください。',
            'discord_client_id.regex' => 'Client ID は 17〜20 桁の数字です。',
            'discord_client_secret.required' => 'Client Secret を入力してください。',
            'discord_client_secret.regex' => 'Client Secret の形式が正しくありません。コピーした値を確認してください。',
            'safe_browsing_api_key.regex' => 'Safe Browsing の API キーの形式が正しくありません。',
            'recaptcha_site_key.required' => 'サイトキーを入力してください。',
            'recaptcha_site_key.regex' => 'reCAPTCHA のサイトキーの形式が正しくありません。',
            'recaptcha_secret_key.required' => 'シークレットキーを入力してください。',
            'recaptcha_secret_key.regex' => 'reCAPTCHA のシークレットキーの形式が正しくありません。',
        ];
    }
}
