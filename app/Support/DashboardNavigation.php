<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IconName;
use App\ViewModels\NavItemData;
use App\ViewModels\ViewerData;
use Illuminate\Http\Request;

/** ダッシュボードのグローバルナビゲーション */
final class DashboardNavigation
{
    /** @return list<NavItemData> */
    public static function items(ViewerData $viewer, Request $request): array
    {
        $items = [
            new NavItemData('ダッシュボード', IconName::LayoutGrid, route('dashboard.home'), $request->routeIs('dashboard.home', 'dashboard.links.*', 'preview.dashboard*')),
        ];

        // 管理者のみの機能（requirements.md 4-2, 5, 7）
        if ($viewer->isAdmin) {
            $items[] = new NavItemData('全URL', IconName::Link, route('dashboard.admin.links'), $request->routeIs('dashboard.admin.links'));
            $items[] = new NavItemData('ユーザー', IconName::Users, route('dashboard.admin.users'), $request->routeIs('dashboard.admin.users*'));
            $items[] = new NavItemData('予約語', IconName::Ban, route('dashboard.admin.reserved-words'), $request->routeIs('dashboard.admin.reserved-words*'));
            $items[] = new NavItemData('APIキー', IconName::Key, route('dashboard.admin.api-keys'), $request->routeIs('dashboard.admin.api-keys*'));
            $items[] = new NavItemData('サイト設定', IconName::Sliders, route('dashboard.admin.site'), $request->routeIs('dashboard.admin.site*'));
            $items[] = new NavItemData('お問い合わせ', IconName::Mail, route('dashboard.admin.inquiries'), $request->routeIs('dashboard.admin.inquiries*'));
            $items[] = new NavItemData('外部サービス', IconName::Plug, route('dashboard.admin.services'), $request->routeIs('dashboard.admin.services*'));
            $items[] = new NavItemData('アップデート', IconName::Refresh, route('dashboard.admin.updates'), $request->routeIs('dashboard.admin.updates*'));
        }

        $items[] = new NavItemData('設定', IconName::Sliders, route('dashboard.settings'), $request->routeIs('dashboard.settings'));

        return $items;
    }
}
