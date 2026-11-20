<?php

declare(strict_types=1);

namespace App\Enums;

/** 短縮URLをどこから発行したか。API で発行したものはページの月間発行数とは別に数える */
enum LinkSource: string
{
    // 発行フォーム・CSV での取り込み
    case Page = 'page';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'ページで発行',
            self::Api => 'API で発行',
        };
    }
}
