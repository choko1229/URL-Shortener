<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'メンバー',
            self::Admin => '管理者',
        };
    }
}
