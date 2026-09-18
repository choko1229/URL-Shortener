<?php

declare(strict_types=1);

namespace App\Enums;

/** 一覧に表示するリンクの状態（design.md 4. テーブル） */
enum LinkStatus: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    // 論理削除済み（コードは欠番。管理者の一覧でのみ表示する）
    case Deleted = 'deleted';
}
