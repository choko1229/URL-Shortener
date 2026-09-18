<?php

declare(strict_types=1);

namespace App\Services\Auth;

/** Discord の identify スコープで取得できる情報（requirements.md 4-1: email・guilds は取得しない） */
final readonly class DiscordProfile
{
    public function __construct(
        public string $id,
        public string $username,
        public ?string $globalName,
        public ?string $avatarHash,
    ) {}
}
