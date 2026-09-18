<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\DiscordAuthException;
use App\Services\Auth\DiscordOAuthClient;
use App\Services\Auth\DiscordUserRegistrar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Discord ログイン（requirements.md 4-1）。
 * ログイン状態を使うダッシュボードと同じドメイン（dash.chok.ooo）で処理する。
 */
final class DiscordAuthController extends Controller
{
    private const STATE_SESSION_KEY = 'discord_oauth_state';

    public function redirect(Request $request, DiscordOAuthClient $discord): RedirectResponse
    {
        if (! $discord->isConfigured()) {
            Log::warning('Discord の Client ID / Secret が未設定のためログインできません。');

            return redirect()->route('main.home')->with('error', 'Discord ログインが設定されていません。サイトの管理者に連絡してください。');
        }

        // CSRF 対策の state をセッションに保存し、コールバックで照合する
        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return redirect()->away($discord->authorizationUrl($state, route('auth.callback')));
    }

    public function callback(Request $request, DiscordOAuthClient $discord, DiscordUserRegistrar $registrar): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $state = $request->query('state');

        if (! is_string($expectedState) || ! is_string($state) || ! hash_equals($expectedState, $state)) {
            Log::warning('Discord ログインの state が一致しませんでした。');

            return redirect()->route('main.home')->with('error', 'ログインの確認に失敗しました。もう一度お試しください。');
        }

        if ($request->filled('error')) {
            return redirect()->route('main.home')->with('notice', 'Discord ログインをキャンセルしました。');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect()->route('main.home')->with('error', 'Discord ログインに失敗しました。もう一度お試しください。');
        }

        try {
            $profile = $discord->fetchProfile($discord->exchangeCode($code, route('auth.callback')));
        } catch (DiscordAuthException $e) {
            return redirect()->route('main.home')->with('error', $e->getMessage());
        }

        $user = $registrar->loginOrRegister($profile, CarbonImmutable::now());

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        Log::info('Discord でログインしました。', ['user_id' => $user->id]);

        return redirect()
            ->intended(route('dashboard.home'))
            ->with('notice', $user->isAdmin() && $user->wasRecentlyCreated ? '管理者として登録しました。' : 'ログインしました。');
    }
}
