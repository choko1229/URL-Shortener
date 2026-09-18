<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AccountException;
use App\Services\Account\UserRoleManager;
use App\Support\ShortenerSettings;
use App\ViewModels\ViewerData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ユーザー一覧と管理者への昇格・解除（requirements.md 4-2） */
final class UserController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, ShortenerSettings $settings): View
    {
        return view('dashboard.admin.users', [
            'viewer' => ViewerData::fromUser($request->user()),
            // 'admin' < 'member' の順で管理者を先頭に並べる
            'users' => User::query()->withCount('shortUrls')->orderBy('role')->orderBy('id')->paginate(self::PER_PAGE),
            'currentUserId' => $request->user()?->getAuthIdentifier(),
            'timezone' => $settings->displayTimezone(),
        ]);
    }

    public function updateRole(Request $request, User $user, UserRoleManager $roles): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        try {
            $roles->change($user, UserRole::from($validated['role']), $actor);
        } catch (AccountException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('notice', $user->displayName().' を'.UserRole::from($validated['role'])->label().'に変更しました。');
    }
}
