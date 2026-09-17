<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShortUrl;
use App\Models\User;

/** requirements.md 2-4 の削除権限 */
final class ShortUrlPolicy
{
    public function delete(User $user, ShortUrl $shortUrl): bool
    {
        return $user->isAdmin() || $shortUrl->user_id === $user->id;
    }
}
