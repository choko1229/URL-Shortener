<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceType: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Bot = 'bot';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Desktop => 'パソコン',
            self::Mobile => 'スマートフォン',
            self::Tablet => 'タブレット',
            self::Bot => 'ボット',
            self::Unknown => '不明',
        };
    }
}
