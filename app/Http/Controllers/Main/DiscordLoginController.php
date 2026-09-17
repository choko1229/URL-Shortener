<?php

declare(strict_types=1);

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Discord ログインの入口。
 * TODO: Discord OAuth2（identify スコープ）へのリダイレクトとコールバック処理を実装する。
 */
final class DiscordLoginController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Log::notice('Discord ログインが要求されましたが、認証処理は未実装です。');

        return redirect()
            ->route('main.home')
            ->with('notice', 'Discordログインは現在準備中です。');
    }
}
