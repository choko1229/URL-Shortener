<?php

declare(strict_types=1);

namespace App\Http\Controllers\Redirect;

use App\Enums\PreviewMode;
use App\Http\Controllers\Controller;
use App\Models\ShortUrl;
use App\Services\Redirect\CrawlerDetector;
use App\Services\Redirect\PasswordAttemptGuard;
use App\Services\Redirect\RedirectTicketCodec;
use App\Services\ShortUrl\ShortUrlResolver;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\Support\SiteIdentity;
use App\ViewModels\SharePreviewData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * chok.ooo/{code} へのアクセス受付（requirements.md 3: 手順 1〜4）。
 * 中間ページを返し、JavaScript で redirect サブドメインへ自動 POST させる。
 */
final class ShortLinkController extends Controller
{
    /** カスタムスラッグの文字種（英数字・ハイフン・アンダースコア）かつ 3〜20 文字 */
    public const CODE_PATTERN = '[A-Za-z0-9_\-]{3,20}';

    public function __construct(
        private readonly ShortUrlResolver $resolver,
        private readonly RedirectTicketCodec $tickets,
        private readonly PasswordAttemptGuard $passwordGuard,
        private readonly ShortenerSettings $settings,
        private readonly CrawlerDetector $crawlers,
        private readonly SiteIdentity $site,
        private readonly ShortUrlBuilder $urls,
    ) {}

    public function show(Request $request, string $code): HttpStatus
    {
        $now = CarbonImmutable::now();
        $link = $this->resolveActive($code);

        if ($link->isExpiredAt($now)) {
            return $this->expired($link);
        }

        // SNS のカード生成（requirements.md には無い補助機能）。発行者の設定に従う
        if ($this->crawlers->isCrawler($request->userAgent())) {
            return $this->forCrawler($link);
        }

        if ($link->isPasswordProtected()) {
            return $this->passwordForm($link, $code, $request);
        }

        return $this->intermediate($request, $link, passwordVerified: false, now: $now);
    }

    public function unlock(Request $request, string $code): Response
    {
        $now = CarbonImmutable::now();
        $link = $this->resolveActive($code);

        if ($link->isExpiredAt($now)) {
            return $this->expired($link);
        }

        if (! $link->isPasswordProtected()) {
            return $this->intermediate($request, $link, passwordVerified: false, now: $now);
        }

        $clientIp = (string) $request->ip();

        if ($this->passwordGuard->lockedSeconds($link, $clientIp) > 0) {
            return $this->passwordForm($link, $code, $request);
        }

        $password = $request->input('password');

        if (! is_string($password) || $password === '' || strlen($password) > 72 || ! Hash::check($password, (string) $link->password_hash)) {
            $remaining = $this->passwordGuard->recordFailure($link, $clientIp);

            return $this->passwordForm(
                $link,
                $code,
                $request,
                $remaining > 0 ? "パスワードが違います。あと {$remaining} 回間違えると、しばらく入力できなくなります。" : null,
            );
        }

        $this->passwordGuard->clear($link, $clientIp);

        return $this->intermediate($request, $link, passwordVerified: true, now: $now);
    }

    /**
     * カードを作りにきたクローラーへの応答。
     * 「転送先のカードを見せる」なら転送先へ通し、それ以外はカードの内容だけを返す（クリックは数えない）。
     */
    private function forCrawler(ShortUrl $link): HttpStatus
    {
        if ($link->previewMode() === PreviewMode::Destination) {
            return $this->noStore(redirect()->away($link->original_url, HttpStatus::HTTP_FOUND));
        }

        return $this->noStore(response()->view('redirect.preview', [
            'preview' => SharePreviewData::forLink($link, $this->site, $this->urls->url($link->slug)),
        ]));
    }

    /** 存在しない・削除済みのコードは 404（削除済みは欠番のまま再利用しない） */
    private function resolveActive(string $code): ShortUrl
    {
        $link = $this->resolver->find($code);

        abort_if($link === null || $link->trashed(), HttpStatus::HTTP_NOT_FOUND);

        return $link;
    }

    private function intermediate(Request $request, ShortUrl $link, bool $passwordVerified, CarbonImmutable $now): Response
    {
        return $this->noStore(response()->view('redirect.intermediate', [
            'ticket' => $this->tickets->encode($link, self::referrerHost($request), $passwordVerified, $now),
        ]));
    }

    private function passwordForm(ShortUrl $link, string $code, Request $request, ?string $error = null): Response
    {
        $lockedSeconds = $this->passwordGuard->lockedSeconds($link, (string) $request->ip());

        $status = match (true) {
            $lockedSeconds > 0 => HttpStatus::HTTP_TOO_MANY_REQUESTS,
            $error !== null => HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            default => HttpStatus::HTTP_OK,
        };

        return $this->noStore(response()->view('redirect.password', [
            'code' => $code,
            'error' => $error,
            'lockedMinutes' => $lockedSeconds > 0 ? (int) ceil($lockedSeconds / 60) : null,
        ], $status));
    }

    private function expired(ShortUrl $link): Response
    {
        return $this->noStore(response()->view('redirect.expired', [
            'expiresAt' => $link->expires_at?->setTimezone($this->settings->displayTimezone()),
        ], HttpStatus::HTTP_GONE));
    }

    /** アクセス元のホスト名のみを記録する（パスやクエリには個人情報が含まれうるため） */
    private static function referrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? mb_substr(strtolower($host), 0, 255) : null;
    }

    private function noStore(HttpStatus $response): HttpStatus
    {
        return $response->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }
}
