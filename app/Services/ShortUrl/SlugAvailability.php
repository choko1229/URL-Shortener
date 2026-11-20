<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\SlugType;
use App\Models\ReservedWord;
use App\Models\RetiredSlug;
use App\Models\ShortUrl;
use App\Support\ShortDomains;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * 短縮コードが使えるかの判定（requirements.md 2-1, 2-2, 2-4）。
 * - 短縮コードはドメインごとに一意（go.example.com/sale と example.com/sale は別のリンクにできる）
 * - 削除済みのコード・編集で使われなくなったコードは、そのドメインでは欠番として再利用しない
 * - ランダムコードは大文字小文字を区別せずに重複判定する
 * - カスタムスラッグは大文字小文字を区別するが、ランダムコードと大文字小文字違いで重なる値は使えない
 * - 予約語はすべてのドメインで共通
 */
final class SlugAvailability
{
    /** 短縮URLとして開かれるルート（メインドメイン・追加ドメイン） */
    private const SHORT_LINK_ROUTES = ['main.short-link.show', 'short.show'];

    public function __construct(private readonly Router $router) {}

    /**
     * そのドメインの既存の画面（/terms や /up など）と同じパスか。
     * 同じだと短縮URLとして開けなくなるため、管理者（予約語を無視できる）でも使えない
     */
    public function conflictsWithRoute(string $slug, string $domain = ShortDomains::PRIMARY): bool
    {
        $route = $this->routeAt($slug, $domain);

        return $route !== null && ! in_array($route, self::SHORT_LINK_ROUTES, true);
    }

    /** そのドメインで GET /{path} を開いたときに使われるルートの名前（名前の無いルートは空文字） */
    public function routeAt(string $path, string $domain = ShortDomains::PRIMARY): ?string
    {
        $host = $domain === ShortDomains::PRIMARY ? (string) config('shortener.domains.main') : $domain;
        $request = Request::create('http://'.$host.'/'.$path, 'GET');

        try {
            return $this->router->getRoutes()->match($request)->getName() ?? '';
        } catch (HttpExceptionInterface) {
            return null;
        }
    }

    /** 有効な（削除されていない）短縮URLがそのパスを使っているか。ランダムコードは大文字小文字を区別せずに届く */
    public function isUsedByActiveLink(string $path, string $domain = ShortDomains::PRIMARY): bool
    {
        return ShortUrl::query()
            ->where('domain', $domain)
            ->where(static function (Builder $query) use ($path): void {
                $query->where('slug', $path)->orWhere(static function (Builder $query) use ($path): void {
                    $query->where('slug_normalized', mb_strtolower($path))->where('slug_type', SlugType::Random->value);
                });
            })
            ->exists();
    }

    public function isReserved(string $slug): bool
    {
        return ReservedWord::query()->where('word', mb_strtolower($slug))->exists();
    }

    public function isTakenForCustom(string $slug, string $domain = ShortDomains::PRIMARY): bool
    {
        $normalized = mb_strtolower($slug);

        $matches = static function (Builder $query) use ($slug, $normalized, $domain): void {
            $query->where('domain', $domain)->where(static function (Builder $query) use ($slug, $normalized): void {
                $query->where('slug', $slug)->orWhere(static function (Builder $query) use ($normalized): void {
                    $query->where('slug_normalized', $normalized)->where('slug_type', SlugType::Random->value);
                });
            });
        };

        return ShortUrl::withTrashed()->where($matches)->exists()
            || RetiredSlug::query()->where($matches)->exists();
    }

    public function isTakenForRandom(string $code, string $domain = ShortDomains::PRIMARY): bool
    {
        $normalized = mb_strtolower($code);

        return ShortUrl::withTrashed()->where('domain', $domain)->where('slug_normalized', $normalized)->exists()
            || RetiredSlug::query()->where('domain', $domain)->where('slug_normalized', $normalized)->exists();
    }
}
