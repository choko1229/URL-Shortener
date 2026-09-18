<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Settings\ExternalServiceSettings;
use App\Support\ExternalServiceKeys;
use App\Support\ServiceKeyRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discord ログインの初回設定（セットアップ直後、管理者がまだいない間だけ使える）。
 * 保存後はそのまま Discord ログインへ進み、最初にログインした人が管理者になる（requirements.md 4-2）。
 * 管理者の登録後は、管理画面の「外部サービス」で変更する。
 */
final class DiscordSetupController extends Controller
{
    public function show(ExternalServiceKeys $keys): View
    {
        self::abortIfAdminExists();

        return view('auth.discord-setup', [
            'callbackUrl' => route('auth.callback'),
            'clientId' => $keys->discordClientId(),
            'hasSecret' => $keys->discordClientSecret() !== null,
        ]);
    }

    public function store(Request $request, ExternalServiceKeys $keys, ExternalServiceSettings $settings): RedirectResponse
    {
        self::abortIfAdminExists();

        // 設定済みの Secret は空欄のままなら変更しない（Client ID だけ直す場合）
        $secretRequired = $keys->discordClientSecret() === null;

        $validated = $request->validate([
            'discord_client_id' => ['required', 'string', 'regex:'.ServiceKeyRules::DISCORD_CLIENT_ID_PATTERN],
            'discord_client_secret' => [$secretRequired ? 'required' : 'nullable', 'string', 'regex:'.ServiceKeyRules::DISCORD_CLIENT_SECRET_PATTERN],
        ], ServiceKeyRules::messages());

        $settings->save([
            AppSetting::DISCORD_CLIENT_ID => $validated['discord_client_id'],
            AppSetting::DISCORD_CLIENT_SECRET => $validated['discord_client_secret'] ?? null,
        ]);

        Log::notice('初回セットアップ: Discord ログインの設定を保存しました。', ['ip' => $request->ip()]);

        return redirect()->route('auth.login');
    }

    private static function abortIfAdminExists(): void
    {
        abort_if(User::adminExists(), Response::HTTP_NOT_FOUND);
    }
}
