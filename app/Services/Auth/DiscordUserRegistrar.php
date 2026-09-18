<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Discord ログイン時のユーザー登録・更新。
 * 管理者が一人もいない状態（セットアップ直後）で最初にログインしたユーザーを管理者にする（requirements.md 4-2）。
 */
final class DiscordUserRegistrar
{
    public function loginOrRegister(DiscordProfile $profile, CarbonImmutable $now): User
    {
        return DB::transaction(static function () use ($profile, $now): User {
            // 同時に初回ログインが起きても管理者が複数生まれないようロックする
            $hasAdmin = User::query()->where('role', UserRole::Admin->value)->lockForUpdate()->exists();

            $user = User::query()->where('discord_id', $profile->id)->lockForUpdate()->first()
                ?? new User(['discord_id' => $profile->id]);

            $user->fill([
                'username' => $profile->username,
                'global_name' => $profile->globalName,
                'avatar_hash' => $profile->avatarHash,
                'last_login_at' => $now,
            ]);

            if (! $hasAdmin) {
                $user->role = UserRole::Admin;
            }

            $user->save();

            if (! $hasAdmin) {
                Log::notice('管理者がいないため、ログインしたユーザーを管理者に設定しました。', ['user_id' => $user->id]);
            }

            return $user;
        });
    }
}
