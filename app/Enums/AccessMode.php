<?php

declare(strict_types=1);

namespace App\Enums;

/** サイトを誰が使えるか（管理画面「サイト設定」で切り替える） */
enum AccessMode: string
{
    case Public = 'public';
    case Restricted = 'restricted';

    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::Public : self::Public;
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'すべての人が使える',
            self::Restricted => '管理者と許可したユーザーだけが使える（限定モード）',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'ログインしていない人も、トップページから短縮URLを発行できます。',
            self::Restricted => 'それ以外の人がトップページやダッシュボードを開くと、このドメインの説明を表示するか、別のURLへ移動します。発行済みの短縮URLは誰でも開けます。',
        };
    }
}
