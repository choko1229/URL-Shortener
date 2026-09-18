<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreShortUrlRequest;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\Security\RecaptchaVerifier;
use App\Services\ShortUrl\IssuanceException;
use App\Services\ShortUrl\QrCodeGenerator;
use App\Services\ShortUrl\ShortUrlIssuer;
use App\Support\ShortUrlBuilder;
use App\ViewModels\IssuedLinkData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/** 短縮URLの発行・削除（トップページとダッシュボードで共用） */
final class ShortUrlController extends Controller
{
    public function store(
        StoreShortUrlRequest $request,
        ShortUrlIssuer $issuer,
        RecaptchaVerifier $recaptcha,
        QrCodeGenerator $qrCodes,
        ShortUrlBuilder $urls,
    ): RedirectResponse {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $clientIp = (string) $request->ip();

        // スパム対策は未ログインの発行のみ（requirements.md 4-4）
        if ($user === null && ! $recaptcha->verify($request->validated('recaptcha_token'), $clientIp)) {
            return back()
                ->withInput($request->safe()->except(['password', 'recaptcha_token']))
                ->with('error', 'スパム対策の確認に失敗しました。ページを再読み込みして、もう一度お試しください。');
        }

        try {
            $issued = $issuer->issue($request->toDraft(), $user, $clientIp);
        } catch (IssuanceException $e) {
            $response = back()->withInput($request->safe()->except(['password', 'recaptcha_token']));

            return $e->field !== null
                ? $response->withErrors([$e->field => $e->getMessage()])
                : $response->with('error', $e->getMessage());
        }

        $link = $issued->shortUrl;

        return back()->with(IssuedLinkData::SESSION_KEY, IssuedLinkData::fromModel(
            $link,
            $issued->deletionToken,
            $urls,
            $qrCodes->dataUri($urls->url($link->slug)),
        ));
    }

    public function destroy(ShortUrl $shortUrl): RedirectResponse
    {
        Gate::authorize('delete', $shortUrl);

        // TODO: 論理削除（コードは欠番として再利用しない）
        Log::info('短縮URLの削除が要求されましたが、削除処理は未実装です。', [
            'short_url_id' => $shortUrl->id,
            'user_id' => auth()->id(),
        ]);

        return back()->with('notice', 'この機能は現在準備中です。');
    }
}
