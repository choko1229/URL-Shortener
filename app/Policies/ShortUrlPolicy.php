<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShortUrl;
use App\Models\User;

/**
 * 短縮URLの権限（requirements.md 2-4, 2-6, 4-2）。
 * - 閲覧（統計）・編集・削除: 発行した本人、または管理者
 * - 未ログインで発行されたもの（user_id が null）は管理者のみ
 */
final class ShortUrlPolicy
{
    public function view(User $user, ShortUrl $shortUrl): bool
    {
        return $this->ownsOrAdministers($user, $shortUrl);
    }

    public function update(User $user, ShortUrl $shortUrl): bool
    {
        return ! $shortUrl->trashed() && $this->ownsOrAdministers($user, $shortUrl);
    }

    public function delete(User $user, ShortUrl $shortUrl): bool
    {
        return ! $shortUrl->trashed() && $this->ownsOrAdministers($user, $shortUrl);
    }

    private function ownsOrAdministers(User $user, ShortUrl $shortUrl): bool
    {
        return $user->isAdmin() || ($shortUrl->user_id !== null && $shortUrl->user_id === $user->id);
    }
}
