<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 管理者権限の付与・解除（requirements.md 4-2: 既存の管理者がダッシュボードから昇格させる。複数人可）。
 * 管理者が一人もいなくなる変更は拒否する（次にログインした人が自動で管理者になってしまうため）。
 */
final class UserRoleManager
{
    /** @throws AccountException */
    public function change(User $target, UserRole $role, User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw new AccountException('権限を変更できるのは管理者のみです。');
        }

        if ($target->role === $role) {
            return;
        }

        DB::transaction(function () use ($target, $role): void {
            if ($role !== UserRole::Admin && $this->isLastAdmin($target)) {
                throw new AccountException('管理者が一人もいなくなるため、この変更はできません。先に別のユーザーを管理者にしてください。');
            }

            $target->role = $role;
            $target->save();
        });

        Log::notice('ユーザーの権限を変更しました。', [
            'target_user_id' => $target->id,
            'role' => $role->value,
            'actor_user_id' => $actor->id,
        ]);
    }

    public function isLastAdmin(User $user): bool
    {
        return $user->isAdmin()
            && User::query()->where('role', UserRole::Admin->value)->whereKeyNot($user->id)->lockForUpdate()->doesntExist();
    }
}
