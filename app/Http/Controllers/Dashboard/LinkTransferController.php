<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLinkTransferRequest;
use App\Models\User;
use App\Services\ShortUrl\Transfer\LinkTransfer;
use App\Services\ShortUrl\Transfer\LinkTransferPlan;
use App\Services\ShortUrl\Transfer\LinkTransferResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * 発行履歴の CSV エクスポート・インポート（別のサーバーへの移行）。
 * 移行先で同じ短縮コードが使われている行があれば、すぐには取り込まずダッシュボードで扱いを選んでもらう。
 */
final class LinkTransferController extends Controller
{
    /** 取り込みの結果（取り込めなかった理由の一覧）を次の画面に渡す */
    public const RESULT_SESSION_KEY = 'link_transfer_result';

    /** 重なりの確認待ち（キャッシュのキーと、画面に出す重なりの一覧） */
    public const PENDING_SESSION_KEY = 'link_transfer_pending';

    private const PENDING_TTL_MINUTES = 30;

    public function export(Request $request, LinkTransfer $transfer): Response
    {
        $filename = 'short-urls-'.CarbonImmutable::now()->format('Ymd-His').'.csv';

        return response($transfer->export($this->user($request)), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            // パスワードのハッシュ値を含むため、途中で保存させない
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function store(ImportLinkTransferRequest $request, LinkTransfer $transfer): RedirectResponse
    {
        $user = $this->user($request);
        $this->forgetPending($request);

        $plan = $transfer->analyze($request->csvPath(), $user);

        if ($plan->rejected) {
            return $this->finish(LinkTransferResult::fromRejectedPlan($plan));
        }

        if (! $plan->hasConflicts()) {
            return $this->finish($transfer->apply($plan, $user));
        }

        $token = Str::random(32);
        Cache::put($this->cacheKey($user, $token), $plan->toArray(), now()->addMinutes(self::PENDING_TTL_MINUTES));

        $request->session()->put(self::PENDING_SESSION_KEY, [
            'token' => $token,
            'ready' => count($plan->rows) - count($plan->conflicts),
            'failed' => count(array_unique(array_map(static fn ($e): int => $e->line, $plan->errors))),
            'conflicts' => array_values(array_map(static fn ($c): array => $c->toArray(), $plan->conflicts)),
        ]);

        return to_route('dashboard.home')->withFragment('history-heading');
    }

    /** 重なりの扱いを選んで取り込む */
    public function confirm(Request $request, LinkTransfer $transfer): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:32'],
            'resolutions' => ['nullable', 'array'],
            'resolutions.*' => ['string', 'in:'.implode(',', [LinkTransfer::SKIP, LinkTransfer::OVERWRITE, LinkTransfer::REISSUE])],
        ]);

        $user = $this->user($request);
        $key = $this->cacheKey($user, (string) $validated['token']);
        $data = Cache::pull($key);
        $this->forgetPending($request);

        if (! is_array($data)) {
            return to_route('dashboard.home')->with('error', '取り込みの確認の有効期限が切れました。もう一度 CSV を選んでください。');
        }

        $plan = LinkTransferPlan::fromArray($data);

        $resolutions = [];
        foreach ((array) ($validated['resolutions'] ?? []) as $line => $resolution) {
            $conflict = $plan->conflicts[(int) $line] ?? null;
            // 自分の短縮URL以外は上書きできない（画面を書き換えて送られてもスキップにする）
            if ($conflict !== null && ($resolution !== LinkTransfer::OVERWRITE || $conflict->canOverwrite())) {
                $resolutions[(int) $line] = (string) $resolution;
            }
        }

        return $this->finish($transfer->apply($plan, $user, $resolutions));
    }

    /** 確認画面で「取り込みをやめる」 */
    public function cancel(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);
        if (is_array($pending) && is_string($pending['token'] ?? null)) {
            Cache::forget($this->cacheKey($this->user($request), $pending['token']));
        }
        $this->forgetPending($request);

        return to_route('dashboard.home')->with('notice', '取り込みをやめました。何も変更していません。');
    }

    private function finish(LinkTransferResult $result): RedirectResponse
    {
        return to_route('dashboard.home')
            ->withFragment('history-heading')
            ->with($result->succeeded() ? 'notice' : 'error', $result->message())
            ->with(self::RESULT_SESSION_KEY, $result);
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget(self::PENDING_SESSION_KEY);
    }

    private function cacheKey(User $user, string $token): string
    {
        return "link-transfer:{$user->id}:{$token}";
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            // auth ミドルウェア配下でのみ呼ばれる想定
            throw new LogicException('発行履歴の移行には認証済みユーザーが必要です。');
        }

        return $user;
    }
}
