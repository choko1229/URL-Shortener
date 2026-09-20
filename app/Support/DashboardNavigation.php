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

        // 管理者のみの機能（requirements.md 4-2, 5, 7）。中身は「管理」画面のタブで切り替える
        if ($viewer->isAdmin) {
            $items[] = new NavItemData('管理', IconName::Shield, AdminNavigation::firstUrl(), $request->routeIs('dashboard.admin.*'));
        }

        $items[] = new NavItemData('設定', IconName::Sliders, route('dashboard.settings'), $request->routeIs('dashboard.settings'));

        return $items;
    }
}
