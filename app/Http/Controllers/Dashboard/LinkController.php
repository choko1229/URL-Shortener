<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSlugRequest;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\Dashboard\LinkStatisticsBuilder;
use App\Services\ShortUrl\IssuanceException;
use App\Services\ShortUrl\QrCodeGenerator;
use App\Services\ShortUrl\SlugEditor;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** 短縮URLごとの詳細（統計・カスタムスラッグの編集・QRコード） */
final class LinkController extends Controller
{
    public function show(Request $request, ShortUrl $shortUrl, LinkStatisticsBuilder $statistics, ShortenerSettings $settings): View
    {
        Gate::authorize('view', $shortUrl);
        $shortUrl->loadMissing('user');

        return view('dashboard.links.show', [
            'viewer' => ViewerData::fromUser(self::user($request)),
            'stats' => $statistics->build($shortUrl, CarbonImmutable::now()),
            'canEdit' => Gate::allows('update', $shortUrl),
            'slugMinLength' => $settings->customSlugMinLength(),
            'slugMaxLength' => $settings->customSlugMaxLength(),
        ]);
    }

    public function updateSlug(UpdateSlugRequest $request, ShortUrl $shortUrl, SlugEditor $editor): RedirectResponse
    {
        Gate::authorize('update', $shortUrl);

        try {
            $editor->change($shortUrl, $request->string('custom_slug')->toString(), self::user($request));
        } catch (IssuanceException $e) {
            return back()->withInput()->withErrors([$e->field ?? 'custom_slug' => $e->getMessage()]);
        }

        return redirect()
            ->route('dashboard.links.show', ['shortUrl' => $shortUrl->id])
            ->with('notice', 'カスタムスラッグを変更しました。以前の短縮URLは使えなくなりました。');
    }

    /** QR コード画像（SVG） */
    public function qr(ShortUrl $shortUrl, QrCodeGenerator $qrCodes, ShortUrlBuilder $urls): Response
    {
        Gate::authorize('view', $shortUrl);
        abort_if($shortUrl->trashed(), Response::HTTP_NOT_FOUND);

        $svg = $qrCodes->svg($urls->url($shortUrl->slug));
        abort_if($svg === null, Response::HTTP_INTERNAL_SERVER_ERROR);

        return response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            // 画像として表示する前提。直接開かれてもスクリプト等を実行させない
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private static function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
