<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreviewRequest;
use App\Http\Requests\UpdateSlugRequest;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\Dashboard\LinkStatisticsBuilder;
use App\Services\GeoIp\GeoIpDatabase;
use App\Services\ShortUrl\IssuanceException;
use App\Services\ShortUrl\SlugEditor;
use App\Support\ShortenerSettings;
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
    public function show(Request $request, ShortUrl $shortUrl, LinkStatisticsBuilder $statistics, ShortenerSettings $settings, GeoIpDatabase $geoIp): View
    {
        Gate::authorize('view', $shortUrl);
        $shortUrl->loadMissing('user');

        return view('dashboard.links.show', [
            'viewer' => ViewerData::fromUser(self::user($request)),
            'stats' => $statistics->build($shortUrl, CarbonImmutable::now()),
            'canEdit' => Gate::allows('update', $shortUrl),
            'slugMinLength' => $settings->customSlugMinLengthFor(self::user($request)),
            'slugMaxLength' => $settings->customSlugMaxLength(),
            // DB-IP のデータ（CC BY 4.0）で判定した国を表示する場合は出典のリンクが必要
            'showGeoIpAttribution' => $geoIp->source()?->requiresAttribution() ?? false,
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

    /** 共有時のカード（OGP）の設定を変更する */
    public function updatePreview(UpdatePreviewRequest $request, ShortUrl $shortUrl): RedirectResponse
    {
        Gate::authorize('update', $shortUrl);

        $shortUrl->fill($request->previewAttributes())->save();

        return back()->with('notice', '共有時のカードの設定を保存しました。');
    }

    private static function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
