<?php

declare(strict_types=1);

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\RecaptchaVerifier;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\ViewModels\HomePageData;
use App\ViewModels\IssuedLinkData;
use App\ViewModels\ShortUrlFormData;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** トップページ（短縮URL発行フォーム） */
final class HomeController extends Controller
{
    public function __invoke(Request $request, ShortenerSettings $settings, ShortUrlBuilder $urls, RecaptchaVerifier $recaptcha): View
    {
        $user = $request->user();
        $isMember = $user instanceof User;

        return view('main.home', [
            'page' => new HomePageData(
                viewer: $isMember ? ViewerData::fromUser($user) : ViewerData::guest(),
                form: ShortUrlFormData::build($isMember, $settings, $urls, CarbonImmutable::now(), $recaptcha->siteKey()),
                issuedLink: IssuedLinkData::fromSession($request->session()),
                displayTimezone: $settings->displayTimezone(),
            ),
        ]);
    }
}
