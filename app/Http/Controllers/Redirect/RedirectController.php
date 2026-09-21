<?php

declare(strict_types=1);

namespace App\Http\Controllers\Redirect;

use App\Http\Controllers\Controller;
use App\Models\ShortUrl;
use App\Services\Redirect\ClickRecorder;
use App\Services\Redirect\RedirectTicket;
use App\Services\Redirect\RedirectTicketCodec;
use App\Services\Redirect\SafeBrowsingChecker;
use App\Services\Redirect\SafetyStatus;
use App\Services\Redirect\SafetyVerdict;
use App\Support\AccessPolicy;
use App\Support\ShortenerSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * リダイレクト確認のサブドメイン（requirements.md 3: 手順 5〜6）。
 * 転送先を表示し、悪意URLチェックの結果に応じて元URLへ移動させる。
 * 中間ページからのチケット（暗号化済み）で認証するため CSRF 検証は行わない。
 */
final class RedirectController extends Controller
{
    public function __construct(
        private readonly RedirectTicketCodec $tickets,
        private readonly SafeBrowsingChecker $safety,
        private readonly ClickRecorder $clicks,
        private readonly ShortenerSettings $settings,
        private readonly AccessPolicy $access,
    ) {}

    /** 直接開かれた場合はトップページへ */
    public function home(): RedirectResponse
    {
        return redirect()->route('main.home');
    }

    public function go(Request $request): HttpStatus
    {
        $now = CarbonImmutable::now();
        $ticket = $this->tickets->decode($request->input('ticket'), $now);

        if ($ticket === null) {
            return $this->noStore(response()->view('redirect.invalid', [], HttpStatus::HTTP_BAD_REQUEST));
        }

        $link = $this->activeLink($ticket, $now);

        if ($link === null) {
            return $this->noStore(response()->view('redirect.invalid', [], HttpStatus::HTTP_NOT_FOUND));
        }

        if ($link->isExpiredAt($now)) {
            return $this->noStore(response()->view('redirect.expired', [
                'expiresAt' => $link->expires_at?->setTimezone($this->settings->displayTimezone()),
            ], HttpStatus::HTTP_GONE));
        }

        // JavaScript で自動送信されたアクセスのみ数える（リンクプレビュー用のボットを除外）。再読み込みは数えない
        if ($this->tickets->markUsed($ticket)) {
            $this->clicks->record($link, $ticket, $request->ip(), $request->userAgent());
        }

        // 限定モードで、利用を許可された人が発行したものは、確認の画面を出さずにそのまま移動する
        if ($this->access->skipsSafetyCheck($link)) {
            return $this->noStore(redirect()->away($link->original_url, HttpStatus::HTTP_FOUND));
        }

        return $this->noStore(response()->view('redirect.show', [
            'destination' => $link->original_url,
            'ticket' => (string) $request->input('ticket'),
        ]));
    }

    public function check(Request $request): JsonResponse
    {
        $now = CarbonImmutable::now();
        $ticket = $this->tickets->decode($request->input('ticket'), $now);
        $link = $ticket !== null ? $this->activeLink($ticket, $now) : null;

        if ($ticket === null || $link === null || $link->isExpiredAt($now)) {
            return $this->noStore(response()->json([
                'status' => 'invalid',
                'message' => 'リンクの有効時間が過ぎました。元のリンクをもう一度開いてください。',
            ], HttpStatus::HTTP_BAD_REQUEST));
        }

        // 限定モードで、利用を許可された人が発行したものは Safe Browsing に問い合わせない
        $verdict = $this->access->skipsSafetyCheck($link)
            ? SafetyVerdict::safe()
            : $this->safety->check($link->original_url);

        return $this->noStore(response()->json([
            'status' => $verdict->status->value,
            'message' => $verdict->reason,
            'threats' => $verdict->threatLabels(),
            // 危険と判定された場合は転送先を返さない（requirements.md 2-7: 転送を中止）
            'destination' => $verdict->status === SafetyStatus::Unsafe ? null : $link->original_url,
        ]));
    }

    private function activeLink(RedirectTicket $ticket, CarbonImmutable $now): ?ShortUrl
    {
        $link = ShortUrl::query()->find($ticket->shortUrlId);

        if ($link === null) {
            return null;
        }

        if ($link->isPasswordProtected() && ! $ticket->passwordVerified) {
            Log::warning('パスワード未確認のチケットで保護リンクにアクセスされました。', ['short_url_id' => $link->id]);

            return null;
        }

        // 発行時に http/https のみ許可しているが、表示・転送の前にも確認する
        if (preg_match('#\Ahttps?://#i', $link->original_url) !== 1) {
            Log::error('転送先の URL が http/https ではありません。', ['short_url_id' => $link->id]);

            return null;
        }

        return $link;
    }

    /**
     * @template T of HttpStatus
     *
     * @param  T  $response
     * @return T
     */
    private function noStore(HttpStatus $response): HttpStatus
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
