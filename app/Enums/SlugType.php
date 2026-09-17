<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 短縮コードの種別。
 * ランダムコードは大文字小文字を区別せず重複判定し、カスタムスラッグは区別する（requirements.md 2-1）。
 */
enum SlugType: string
{
    case Random = 'random';
    case Custom = 'custom';
}
