<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OutsiderAction;
use App\Models\SitePage;
use App\Models\User;
use App\Support\AccessPolicy;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 限定モード: 管理者と許可したユーザー以外には、トップページ・ダッシュボードの代わりに
 * このドメインの説明を表示する（設定によっては別の URL へ移動する）。
 */
final class RestrictToPermittedUsers
{
    public function __construct(
        private readonly AccessPolicy $policy,
        private readonly ShortenerSettings $settings,
    ) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($this->policy->allows($user instanceof User ? $user : null) || $request->routeIs('dashboard.logout')) {
            return $next($request);
        }

        // 限定モードに切り替える前からログインしていた人は、ここでログアウトさせる
        if ($user !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        abort_unless($request->isMethodSafe(), Response::HTTP_FORBIDDEN, 'このサービスは限定公開のため、利用が許可されていません。');

        return $this->outsiderResponse(wasLoggedIn: $user !== null);
    }

    private function outsiderResponse(bool $wasLoggedIn): Response
    {
        if ($this->policy->outsiderAction() === OutsiderAction::Redirect) {
            return redirect()->away((string) $this->policy->redirectUrl());
        }

        return response()->view('main.about', [
            'viewer' => ViewerData::guest(),
            'page' => SitePage::query()->where('slug', SitePage::ABOUT)->first(),
            'timezone' => $this->settings->displayTimezone(),
            'wasLoggedIn' => $wasLoggedIn,
        ]);
    }
}
