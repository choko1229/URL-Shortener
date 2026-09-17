<?php

declare(strict_types=1);

namespace App\Enums;

/** requirements.md 2-2 の分類 */
enum ReservedWordCategory: string
{
    case System = 'system';
    case Auth = 'auth';
    case Feature = 'feature';
    case Confusing = 'confusing';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::System => 'システム関連',
            self::Auth => '認証関連',
            self::Feature => '機能関連',
            self::Confusing => '紛らわしい語',
            self::Custom => '個別追加',
        };
    }
}
