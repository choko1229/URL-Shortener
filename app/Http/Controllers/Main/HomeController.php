<?php

declare(strict_types=1);

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\ViewModels\HomePageData;
use App\ViewModels\IssuedLinkData;
use App\ViewModels\ShortUrlFormData;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** chok.ooo トップページ（短縮URL発行フォーム） */
final class HomeController extends Controller
{
    public function __invoke(Request $request, ShortenerSettings $settings, ShortUrlBuilder $urls): View
    {
        $user = $request->user();
        $isMember = $user instanceof User;

        return view('main.home', [
            'page' => new HomePageData(
                viewer: $isMember ? ViewerData::fromUser($user) : ViewerData::guest(),
                form: ShortUrlFormData::build($isMember, $settings, $urls, CarbonImmutable::now()),
                issuedLink: $this->pullIssuedLink($request),
                displayTimezone: $settings->displayTimezone(),
            ),
        ]);
    }

    private function pullIssuedLink(Request $request): ?IssuedLinkData
    {
        $issued = $request->session()->get(IssuedLinkData::SESSION_KEY);

        if ($issued === null || $issued instanceof IssuedLinkData) {
            return $issued;
        }

        Log::warning('セッション内の発行結果の形式が不正なため表示しませんでした。', [
            'type' => get_debug_type($issued),
        ]);

        return null;
    }
}
