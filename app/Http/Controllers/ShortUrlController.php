<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreShortUrlRequest;
use App\Models\ShortUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * 短縮URLの発行・削除（トップページとダッシュボードで共用）。
 * 入力検証と権限確認までを行い、DB 保存・削除処理は未実装。
 */
final class ShortUrlController extends Controller
{
    private const NOT_IMPLEMENTED_NOTICE = 'この機能は現在準備中です。入力内容は保存されていません。';

    public function store(StoreShortUrlRequest $request): RedirectResponse
    {
        // TODO: 予約語・重複・月間上限・レート制限・reCAPTCHA を確認し、短縮URLを保存して IssuedLinkData を flash する
        Log::info('短縮URLの発行が要求されましたが、保存処理は未実装です。', [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'expiry' => $request->validated('expiry'),
            'has_custom_slug' => filled($request->validated('custom_slug')),
            'has_password' => filled($request->validated('password')),
        ]);

        return back()
            ->withInput($request->safe()->except(['password']))
            ->with('notice', self::NOT_IMPLEMENTED_NOTICE);
    }

    public function destroy(ShortUrl $shortUrl): RedirectResponse
    {
        Gate::authorize('delete', $shortUrl);

        // TODO: 論理削除（コードは欠番として再利用しない）
        Log::info('短縮URLの削除が要求されましたが、削除処理は未実装です。', [
            'short_url_id' => $shortUrl->id,
            'user_id' => auth()->id(),
        ]);

        return back()->with('notice', self::NOT_IMPLEMENTED_NOTICE);
    }
}
