<?php

declare(strict_types=1);

namespace App\Enums;

enum UpdateRunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    // コードを変更する前に失敗した（バックアップの失敗など）
    case Failed = 'failed';
    // 失敗したため更新前の状態に戻した
    case RolledBack = 'rolled_back';
    // ロールバックにも失敗した（手動での復旧が必要）
    case RollbackFailed = 'rollback_failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => '実行中',
            self::Succeeded => '成功',
            self::Failed => '失敗（変更なし）',
            self::RolledBack => '失敗（ロールバック済み）',
            self::RollbackFailed => '失敗（ロールバックにも失敗）',
        };
    }
}
