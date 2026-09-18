<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\SlugType;
use App\Models\ShortUrl;

/**
 * アクセスされたコードから短縮URLを探す。
 * 完全一致を優先し、見つからなければランダムコードに限り大文字小文字を無視して照合する
 * （カスタムスラッグは大文字小文字を区別する: requirements.md 2-1）。
 * 論理削除済みも返すため、呼び出し側で trashed() を確認すること。
 */
final class ShortUrlResolver
{
    public function find(string $code): ?ShortUrl
    {
        $exact = ShortUrl::withTrashed()->where('slug', $code)->first();

        if ($exact !== null) {
            return $exact;
        }

        return ShortUrl::withTrashed()
            ->where('slug_normalized', mb_strtolower($code))
            ->where('slug_type', SlugType::Random->value)
            ->first();
    }
}
