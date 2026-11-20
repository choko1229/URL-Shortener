<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LinkSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLinkRequest;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\ShortUrl\IssuanceException;
use App\Services\ShortUrl\QrCodeGenerator;
use App\Services\ShortUrl\ShortUrlIssuer;
use App\Services\ShortUrl\ShortUrlResolver;
use App\Support\ShortDomains;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 管理者専用 API（requirements.md 5: 自分が管理する他プロジェクトからの発行用。レート制限なし）
 */
final class LinkController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly ShortUrlBuilder $urls,
        private readonly ShortDomains $domains,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = ShortUrl::query()
            ->where('user_id', self::user($request)->id)
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ShortUrl $link): array => $this->resource($link))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreLinkRequest $request, ShortUrlIssuer $issuer, QrCodeGenerator $qrCodes): JsonResponse
    {
        try {
            // 管理者専用のため月間上限・レート制限は適用しない。API で発行したものはページの発行数とは別に数える
            $issued = $issuer->issue($request->toDraft(), self::user($request), (string) $request->ip(), enforceLimits: false, source: LinkSource::Api);
        } catch (IssuanceException $e) {
            $field = $e->field === 'custom_slug' ? 'slug' : ($e->field ?? 'url');

            return response()->json(['message' => $e->getMessage(), 'errors' => [$field => [$e->getMessage()]]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $link = $issued->shortUrl;

        return response()->json([
            'data' => $this->resource($link) + ['qr_code' => $qrCodes->dataUri($this->urls->urlFor($link))],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $code, ShortUrlResolver $resolver): JsonResponse
    {
        return response()->json(['data' => $this->resource($this->find($request, $code, $resolver))]);
    }

    public function destroy(Request $request, string $code, ShortUrlResolver $resolver): Response
    {
        $link = $this->find($request, $code, $resolver);
        $link->delete();

        Log::info('API で短縮URLを削除しました。', ['short_url_id' => $link->id, 'user_id' => self::user($request)->id]);

        return response()->noContent();
    }

    /** 追加ドメインの短縮URLは ?domain=go.example.com で指定する（省略時はメインドメイン） */
    private function find(Request $request, string $code, ShortUrlResolver $resolver): ShortUrl
    {
        $input = $request->query('domain');
        $domain = $this->domains->resolveInput(is_string($input) ? $input : null, $this->domains->forApi());
        abort_if($domain === null, Response::HTTP_NOT_FOUND, '短縮URLが見つかりません。');

        $link = $resolver->find($code, $domain);

        abort_if($link === null || $link->trashed(), Response::HTTP_NOT_FOUND, '短縮URLが見つかりません。');

        return $link;
    }

    /** @return array<string, mixed> */
    private function resource(ShortUrl $link): array
    {
        return [
            'code' => $link->slug,
            'domain' => $this->urls->host((string) $link->domain),
            'short_url' => $this->urls->urlFor($link),
            'source' => $link->source->value,
            'original_url' => $link->original_url,
            'custom_slug' => $link->isCustomSlug(),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'expired' => $link->isExpiredAt(CarbonImmutable::now()),
            'password_protected' => $link->isPasswordProtected(),
            'click_count' => $link->click_count,
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    private static function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
