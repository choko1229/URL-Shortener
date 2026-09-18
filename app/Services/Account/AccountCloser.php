<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 退会・アカウント削除（requirements.md 4-4）。
 * Discord の情報は消去して論理削除する。発行済みの短縮URLは記録として残す（requirements.md 2-4）が、
 * 本人の希望があれば一緒に削除（論理削除）する。同じ Discord アカウントで再ログインすると新規登録になる。
 */
final class AccountCloser
{
    public function __construct(private readonly UserRoleManager $roles) {}

    /** @throws AccountException */
    public function close(User $user, bool $deleteLinks): void
    {
        DB::transaction(function () use ($user, $deleteLinks): void {
            if ($this->roles->isLastAdmin($user)) {
                throw new AccountException('最後の管理者は退会できません。先に別のユーザーを管理者にしてください。');
            }

            if ($deleteLinks) {
                $user->shortUrls()->each(static fn ($link) => $link->delete());
            }

            $user->apiKeys()->active()->update(['revoked_at' => CarbonImmutable::now()]);

            $user->forceFill([
                // 一意制約を空けるため、元の Discord ID は残さない
                'discord_id' => 'deleted:'.$user->id,
                'username' => '退会済みユーザー',
                'global_name' => null,
                'avatar_hash' => null,
                'role' => UserRole::Member,
                'remember_token' => null,
            ])->save();

            $user->delete();
        });

        Log::notice('ユーザーが退会しました。', ['user_id' => $user->id, 'links_deleted' => $deleteLinks]);
    }
}
