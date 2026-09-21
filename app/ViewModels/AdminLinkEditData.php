<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\UserRole;
use App\Models\ShortUrl;
use App\Models\User;

/** 管理者による短縮URLの編集欄（元URL・有効期限・発行者）の初期値 */
final readonly class AdminLinkEditData
{
    /**
     * @param  array<int, string>  $users  選べる発行者（ID => 表示名）
     */
    public function __construct(
        public int $linkId,
        public string $originalUrl,
        public ?string $expiresAtLocal,
        public ?int $userId,
        public array $users,
        public string $timezone,
    ) {}

    public static function from(ShortUrl $link, string $timezone): self
    {
        $users = User::query()
            ->orderByRaw('case when role = ? then 0 else 1 end', [UserRole::Admin->value])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (User $user): array => [$user->id => $user->displayName().'（@'.$user->username.'）'])
            ->all();

        return new self(
            linkId: $link->id,
            originalUrl: $link->original_url,
            expiresAtLocal: $link->expires_at?->setTimezone($timezone)->format(ShortUrlFormData::DATETIME_LOCAL_FORMAT),
            userId: $link->user_id,
            users: $users,
            timezone: $timezone,
        );
    }
}
