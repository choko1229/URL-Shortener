<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Models\User;

/** ヘッダー等に表示する閲覧者情報 */
final readonly class ViewerData
{
    public function __construct(
        public bool $isAuthenticated,
        public bool $isAdmin,
        public string $displayName,
        public ?string $avatarUrl,
    ) {}

    public static function guest(): self
    {
        return new self(isAuthenticated: false, isAdmin: false, displayName: '', avatarUrl: null);
    }

    public static function fromUser(User $user): self
    {
        return new self(
            isAuthenticated: true,
            isAdmin: $user->isAdmin(),
            displayName: $user->displayName(),
            avatarUrl: $user->avatarUrl(),
        );
    }

    /** アバター画像が無い場合に表示する頭文字 */
    public function initial(): string
    {
        $initial = mb_substr($this->displayName, 0, 1);

        return $initial !== '' ? mb_strtoupper($initial) : '?';
    }
}
