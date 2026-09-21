<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteAccessRequest;
use App\Http\Requests\Admin\SiteIconRequest;
use App\Http\Requests\Admin\SiteIdentityRequest;
use App\Http\Requests\Admin\SitePageRequest;
use App\Http\Requests\Admin\SiteThemeRequest;
use App\Models\SitePage;
use App\Services\Site\LegalTemplates;
use App\Support\AccessPolicy;
use App\Support\ShortenerSettings;
use App\Support\SiteIcon;
use App\Support\SiteIdentity;
use App\Support\Theme;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * サイト設定（サイト名などの表示と、利用規約・プライバシーポリシーの編集）。
 * 設置した人が自分のサービスとして使えるよう、文言はコードではなくここから変更する。
 */
final class SiteController extends Controller
{
    public function index(Request $request, SiteIdentity $site, Theme $theme, SiteIcon $icon, AccessPolicy $access, ShortenerSettings $settings): View
    {
        return view('dashboard.admin.site', [
            'viewer' => ViewerData::fromUser($request->user()),
            'identity' => [
                'name' => $site->name(),
                'tagline' => $site->tagline(),
                'operator' => $site->operator(),
            ],
            'themeValues' => [
                'color' => $theme->color(),
                'color_scheme' => $theme->scheme()->value,
                'font' => $theme->font()->value,
            ],
            'selectedIcon' => $icon->selected(),
            'hasUploadedIcon' => $icon->path() !== null,
            'accessValues' => [
                'mode' => $access->mode()->value,
                'outsider_action' => $access->outsiderAction()->value,
                'redirect_url' => $access->redirectUrl(),
            ],
            'pages' => SitePage::query()->whereIn('slug', array_keys(SitePage::AVAILABLE))->get()->keyBy('slug'),
            'timezone' => $settings->displayTimezone(),
        ]);
    }

    public function updateIdentity(SiteIdentityRequest $request, SiteIdentity $site): RedirectResponse
    {
        $site->save($request->identity());

        Log::notice('サイト設定を変更しました。', ['user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', 'サイトの表示を保存しました。');
    }

    public function updateTheme(SiteThemeRequest $request, Theme $theme): RedirectResponse
    {
        $theme->save($request->theme());

        Log::notice('サイトの見た目を変更しました。', ['user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', '見た目を保存しました。');
    }

    public function updateIcon(SiteIconRequest $request, SiteIcon $icon): RedirectResponse
    {
        $file = $request->file('file');

        if ($request->string('icon')->toString() !== SiteIcon::UPLOADED) {
            $icon->useBuiltIn($request->string('icon')->toString());
        } elseif ($file instanceof UploadedFile) {
            $icon->store($file);
        }

        Log::notice('サービスアイコンを変更しました。', ['user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', 'サービスアイコンを保存しました。ファビコンにも同じものを使います。');
    }

    public function updateAccess(SiteAccessRequest $request, AccessPolicy $access): RedirectResponse
    {
        $access->save($request->access());

        Log::notice('公開範囲を変更しました。', ['user_id' => $request->user()?->getAuthIdentifier(), 'mode' => $access->mode()->value]);

        return back()->with('notice', $access->isRestricted()
            ? '限定モードにしました。管理者と「ユーザー」タブで許可した人だけが使えます。'
            : 'すべての人が使えるようにしました。');
    }

    public function updatePage(SitePageRequest $request, string $slug): RedirectResponse
    {
        abort_unless(LegalTemplates::exists($slug), 404);

        SitePage::query()->updateOrCreate(
            ['slug' => $slug],
            ['title' => $request->string('title')->trim()->toString(), 'body' => $request->string('body')->toString()],
        );

        Log::notice('固定ページを保存しました。', ['slug' => $slug, 'user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', $slug === SitePage::ABOUT
            ? '「このドメインについて」を保存しました。限定モードで、許可されていない人に表示します。'
            : 'ページを保存しました。フッターから開けます。');
    }

    /** ひな形を編集欄に読み込む（保存はしない） */
    public function loadTemplate(string $slug, LegalTemplates $templates): RedirectResponse
    {
        abort_unless(LegalTemplates::exists($slug), 404);

        $template = $templates->for($slug);

        return back()
            ->withInput(['slug' => $slug] + $template)
            ->with('notice', 'テンプレートを読み込みました。内容を確認・修正してから保存してください。');
    }

    public function destroyPage(Request $request, string $slug): RedirectResponse
    {
        abort_unless(LegalTemplates::exists($slug), 404);

        SitePage::query()->where('slug', $slug)->delete();

        Log::notice('固定ページを削除しました。', ['slug' => $slug, 'user_id' => $request->user()?->getAuthIdentifier()]);

        return back()->with('notice', $slug === SitePage::ABOUT
            ? '「このドメインについて」を削除しました。限定モードでは短い既定の文を表示します。'
            : 'ページを削除しました。フッターにも表示されなくなります。');
    }
}
