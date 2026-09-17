<?php

declare(strict_types=1);

namespace App\Enums;

/** ダッシュボードに表示するリンクの状態（design.md 4. テーブル） */
enum LinkStatus: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
}
