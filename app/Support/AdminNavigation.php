<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IconName;
use App\ViewModels\NavItemData;
use Illuminate\Http\Request;

/**
 * 「管理」の中のタブ（requirements.md 4-2 管理者の特権）。
 * ヘッダーの項目が増えすぎないよう、管理者向けの画面は 1 か所にまとめてタブで切り替える。
 */
final class AdminNavigation
{
    /** 「管理」を開いたときに最初に表示する画面 */
    public static function firstUrl(): string
    {
        return route('dashboard.admin.links');
    }

    /** @return list<NavItemData> */
    public static function tabs(Request $request): array
    {
        return [
            new NavItemData('全URL', IconName::Link, route('dashboard.admin.links'), $request->routeIs('dashboard.admin.links')),
            new NavItemData('ユーザー', IconName::Users, route('dashboard.admin.users'), $request->routeIs('dashboard.admin.users*')),
            new NavItemData('お問い合わせ', IconName::Mail, route('dashboard.admin.inquiries'), $request->routeIs('dashboard.admin.inquiries*')),
            new NavItemData('サイト設定', IconName::Sliders, route('dashboard.admin.site'), $request->routeIs('dashboard.admin.site*')),
            new NavItemData('外部サービス', IconName::Plug, route('dashboard.admin.services'), $request->routeIs('dashboard.admin.services*')),
            new NavItemData('予約語', IconName::Ban, route('dashboard.admin.reserved-words'), $request->routeIs('dashboard.admin.reserved-words*')),
            new NavItemData('APIキー', IconName::Key, route('dashboard.admin.api-keys'), $request->routeIs('dashboard.admin.api-keys*')),
            new NavItemData('アップデート', IconName::Refresh, route('dashboard.admin.updates'), $request->routeIs('dashboard.admin.updates*')),
        ];
    }
}
