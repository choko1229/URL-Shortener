<?php

declare(strict_types=1);

namespace App\Enums;

/** 短縮URL一覧で並べ替えに使える列 */
enum LinkSortColumn: string
{
    case Created = 'created';
    case Slug = 'slug';
    case Clicks = 'clicks';
    case Expires = 'expires';

    /** 列見出しを初めて押したときの向き（新しい順・多い順・期限の近い順・A→Z） */
    public function defaultDescending(): bool
    {
        return match ($this) {
            self::Created, self::Clicks => true,
            self::Slug, self::Expires => false,
        };
    }
}
