<?php

declare(strict_types=1);

namespace App\Enums;

/** 追加ドメインで短縮URLを発行できる経路 */
enum ShortDomainMode: string
{
    // 発行フォーム（トップページ・ダッシュボード）と API の両方
    case Page = 'page';
    // API からのみ
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'ページと API',
            self::Api => 'API のみ',
        };
    }
}
