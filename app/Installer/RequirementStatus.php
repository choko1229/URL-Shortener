<?php

declare(strict_types=1);

namespace App\Installer;

enum RequirementStatus: string
{
    case Passed = 'passed';
    // 動作はするが推奨を満たしていない
    case Warning = 'warning';
    // サーバー側で判定できず、利用者の目視確認が必要
    case Unknown = 'unknown';
    // 満たさないとセットアップを続けられない
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'OK',
            self::Warning => '注意',
            self::Unknown => '要確認',
            self::Failed => 'NG',
        };
    }
}
