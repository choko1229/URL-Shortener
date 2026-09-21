<?php

declare(strict_types=1);

namespace App\Enums;

/** 限定モードで、利用を許可されていない人がトップページやダッシュボードを開いたときの動作 */
enum OutsiderAction: string
{
    case Page = 'page';
    case Redirect = 'redirect';

    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::Page : self::Page;
    }

    public function label(): string
    {
        return match ($this) {
            self::Page => 'このドメインの説明を表示する',
            self::Redirect => '別のURLへ移動する',
        };
    }
}
