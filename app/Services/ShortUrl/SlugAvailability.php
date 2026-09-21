<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\SlugType;
use App\Models\ReservedWord;
use App\Models\RetiredSlug;
use App\Models\ShortUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * 短縮コードが使えるかの判定（requirements.md 2-1, 2-2, 2-4）。
 * - 削除済みのコード・編集で使われなくなったコードは欠番として再利用しない
 * - ランダムコードは大文字小文字を区別せずに重複判定する
 * - カスタムスラッグは大文字小文字を区別するが、ランダムコードと大文字小文字違いで重なる値は使えない
 */
final class SlugAvailability
{
    private const SHORT_LINK_ROUTE = 'main.short-link.show';

    public function __construct(private readonly Router $router) {}

    /**
     * メインドメインの既存の画面（/terms や /up など）と同じパスか。
     * 同じだと短縮URLとして開けなくなるため、管理者（予約語を無視できる）でも使えない
     */
    public function conflictsWithRoute(string $slug): bool
    {
        $request = Request::create('http://'.config('shortener.domains.main').'/'.$slug, 'GET');

        try {
            return $this->router->getRoutes()->match($request)->getName() !== self::SHORT_LINK_ROUTE;
        } catch (HttpExceptionInterface) {
            return false;
        }
    }

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
