<?php

declare(strict_types=1);

namespace App\Services\GeoIp;

enum GeoIpSource: string
{
    // 自動で取得・更新する無料データベース（CC BY 4.0。表示する画面に出典リンクが必要）
    case DbIp = 'dbip';
    // 管理者が手動で置いた MaxMind GeoLite2
    case MaxMind = 'maxmind';

    public function label(): string
    {
        return match ($this) {
            self::DbIp => 'DB-IP IP to Country Lite（自動取得）',
            self::MaxMind => 'MaxMind GeoLite2 Country（手動で設置）',
        };
    }

    public function requiresAttribution(): bool
    {
        return $this === self::DbIp;
    }
}
