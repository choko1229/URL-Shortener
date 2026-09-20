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

    /** Google Cloud のプロジェクト ID（英小文字で始まる 6〜30 文字。英小文字・数字・ハイフン） */
    public const GOOGLE_PROJECT_ID_PATTERN = '/\A[a-z][a-z0-9\-]{4,28}[a-z0-9]\z/';

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'discord_client_id.required' => 'Client ID を入力してください。',
            'discord_client_id.regex' => 'Client ID は 17〜20 桁の数字です。',
            'discord_client_secret.required' => 'Client Secret を入力してください。',
            'discord_client_secret.regex' => 'Client Secret の形式が正しくありません。コピーした値を確認してください。',
            'safe_browsing_api_key.regex' => 'Safe Browsing の API キーの形式が正しくありません。',
            'recaptcha_site_key.regex' => 'reCAPTCHA のキー ID の形式が正しくありません。',
            'recaptcha_project_id.regex' => 'プロジェクト ID の形式が正しくありません（英小文字で始まる 6〜30 文字）。',
            'recaptcha_api_key.regex' => 'API キーの形式が正しくありません。',
        ];
    }
}
