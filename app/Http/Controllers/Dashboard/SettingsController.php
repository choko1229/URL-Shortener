<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AccountCloser;
use App\Services\Account\AccountException;
use App\Services\Account\UserRoleManager;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** アカウント設定と退会（requirements.md 4-4） */
final class SettingsController extends Controller
{
    public function show(Request $request, UserRoleManager $roles, ShortenerSettings $settings): View
    {
        $user = self::user($request);

        return view('dashboard.settings', [
            'viewer' => ViewerData::fromUser($user),
            'user' => $user,
            'linkCount' => $user->shortUrls()->count(),
            'isLastAdmin' => DB::transaction(static fn (): bool => $roles->isLastAdmin($user)),
            'timezone' => $settings->displayTimezone(),
        ]);
    }

    public function destroyAccount(Request $request, AccountCloser $closer): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => '退会する場合は確認のチェックを入れてください。']);

        $user = self::user($request);

        try {
            $closer->close($user, $request->boolean('delete_links'));
        } catch (AccountException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('main.home')->with('notice', '退会しました。ご利用ありがとうございました。');
    }

    private static function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
