<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\SlugType;
use App\Models\ReservedWord;
use App\Models\RetiredSlug;
use App\Models\ShortUrl;
use Illuminate\Database\Eloquent\Builder;

/**
 * 短縮コードが使えるかの判定（requirements.md 2-1, 2-2, 2-4）。
 * - 削除済みのコード・編集で使われなくなったコードは欠番として再利用しない
 * - ランダムコードは大文字小文字を区別せずに重複判定する
 * - カスタムスラッグは大文字小文字を区別するが、ランダムコードと大文字小文字違いで重なる値は使えない
 */
final class SlugAvailability
{
    public function isReserved(string $slug): bool
    {
        return ReservedWord::query()->where('word', mb_strtolower($slug))->exists();
    }

    public function isTakenForCustom(string $slug): bool
    {
        $normalized = mb_strtolower($slug);

        $matches = static function (Builder $query) use ($slug, $normalized): void {
            $query->where('slug', $slug)->orWhere(static function (Builder $query) use ($normalized): void {
                $query->where('slug_normalized', $normalized)->where('slug_type', SlugType::Random->value);
            });
        };

        return ShortUrl::withTrashed()->where($matches)->exists()
            || RetiredSlug::query()->where($matches)->exists();
    }

    public function isTakenForRandom(string $code): bool
    {
        $normalized = mb_strtolower($code);

        return ShortUrl::withTrashed()->where('slug_normalized', $normalized)->exists()
            || RetiredSlug::query()->where('slug_normalized', $normalized)->exists();
    }
}
