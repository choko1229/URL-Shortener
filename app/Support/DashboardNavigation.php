<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IconName;
use App\ViewModels\NavItemData;
use App\ViewModels\ViewerData;

/** ダッシュボードのグローバルナビゲーション */
final class DashboardNavigation
{
    /** @return list<NavItemData> */
    public static function items(ViewerData $viewer, bool $isDashboardHome): array
    {
        $items = [
            new NavItemData('ダッシュボード', IconName::LayoutGrid, route('dashboard.home'), $isDashboardHome),
        ];

        // 管理者のみの機能（requirements.md 4-2, 5）。各画面は未実装
        if ($viewer->isAdmin) {
            $items[] = new NavItemData('APIキー', IconName::Key, null);
            $items[] = new NavItemData('予約語', IconName::Ban, null);
            $items[] = new NavItemData('ユーザー', IconName::Users, null);
        }

        $items[] = new NavItemData('設定', IconName::Sliders, null);

        return $items;
    }
}
