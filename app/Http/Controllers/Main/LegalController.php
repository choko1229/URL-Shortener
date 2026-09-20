<?php

declare(strict_types=1);

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\SitePage;
use App\Models\User;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 設置した人が用意する固定ページ（利用規約・プライバシーポリシー）。
 * 内容は管理画面の「サイト設定」で Markdown として編集する。未作成なら公開しない。
 */
final class LegalController extends Controller
{
    public function __construct(private readonly ShortenerSettings $settings) {}

    public function terms(Request $request): View
    {
        return $this->page($request, SitePage::TERMS);
    }

    public function privacy(Request $request): View
    {
        return $this->page($request, SitePage::PRIVACY);
    }

    private function page(Request $request, string $slug): View
    {
        $page = SitePage::query()->where('slug', $slug)->first();

        abort_if($page === null, Response::HTTP_NOT_FOUND);

        $user = $request->user();

        return view('main.page', [
            'viewer' => $user instanceof User ? ViewerData::fromUser($user) : ViewerData::guest(),
            'page' => $page,
            'timezone' => $this->settings->displayTimezone(),
        ]);
    }
}
