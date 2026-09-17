<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| chok.ooo 固有設定
|--------------------------------------------------------------------------
|
| ドメインのようにデプロイ環境ごとに異なる値だけを .env から読み込む。
| 発行上限や期限などの業務ルールは .env に置かず、ここを「初期値」として
| app_settings テーブルの値で上書きする（App\Support\ShortenerSettings 参照）。
| 予約語はここにも置かず reserved_words テーブルで管理する。
|
*/

return [

    'domains' => [
        'main' => env('SHORTENER_MAIN_DOMAIN', 'chok.ooo'),
        'dashboard' => env('SHORTENER_DASHBOARD_DOMAIN', 'dash.chok.ooo'),
        'api' => env('SHORTENER_API_DOMAIN', 'api.chok.ooo'),
        'redirect' => env('SHORTENER_REDIRECT_DOMAIN', 'redirect.chok.ooo'),
    ],

    // 短縮URLの組み立てに使うベースURL（末尾スラッシュなし）
    'short_url_base' => env('SHORTENER_SHORT_URL_BASE', 'https://chok.ooo'),

    // 画面表示・月間集計に使うタイムゾーン（DB保存は UTC）
    'display_timezone' => 'Asia/Tokyo',

    // app_settings テーブルに値が無い場合の初期値
    'defaults' => [
        'custom_slug_min_length' => 3,
        'custom_slug_max_length' => 20,
        'member_monthly_limit' => 60,
        'guest_monthly_limit' => 5,
        'guest_max_expiry_days' => 30,
        'expiry_warning_days' => 3,
        'dashboard_links_per_page' => 10,
    ],

];
