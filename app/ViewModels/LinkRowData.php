<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\LinkStatus;
use App\Models\ShortUrl;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;

/** 短縮URL一覧テーブルの 1 行分 */
final readonly class LinkRowData
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $shortUrl,
        public string $displayUrl,
        public string $originalUrl,
        public int $clickCount,
        public LinkStatus $status,
        public string $expiryLabel,
        public bool $isPasswordProtected,
        public bool $isCustomSlug,
        // 管理者の一覧でのみ使う発行者の表示名（未ログイン発行は「未ログイン」）
        public ?string $ownerLabel = null,
    ) {}

    public static function fromModel(
        ShortUrl $link,
        ShortUrlBuilder $urls,
        CarbonImmutable $now,
        int $warningDays,
        string $timezone,
        bool $withOwner = false,
    ): self {
        $status = $link->statusAt($now, $warningDays);

        return new self(
            id: $link->id,
            slug: $link->slug,
            shortUrl: $urls->url($link->slug),
            displayUrl: $urls->display($link->slug),
            originalUrl: $link->original_url,
            clickCount: $link->click_count,
            status: $status,
            expiryLabel: self::expiryLabel($link->expires_at, $status, $now, $timezone),
            isPasswordProtected: $link->isPasswordProtected(),
            isCustomSlug: $link->isCustomSlug(),
            ownerLabel: $withOwner ? self::ownerLabel($link) : null,
        );
    }

    public function isUsable(): bool
    {
        return $this->status !== LinkStatus::Deleted && $this->status !== LinkStatus::Expired;
    }

    public function isDeleted(): bool
    {
        return $this->status === LinkStatus::Deleted;
    }

    private static function ownerLabel(ShortUrl $link): string
    {
        if ($link->user_id === null) {
            return '未ログイン';
        }

        $owner = $link->relationLoaded('user') ? $link->user : null;

        return $owner?->displayName() ?? '退会済みユーザー';
    }

    /** design.md のテーブル表記（無期限 / 残りN日 / 期限切れ）に合わせる */
    private static function expiryLabel(?CarbonImmutable $expiresAt, LinkStatus $status, CarbonImmutable $now, string $timezone): string
    {
        if ($status === LinkStatus::Deleted) {
            return '削除済み';
        }

        if ($expiresAt === null) {
            return '無期限';
        }

        return match ($status) {
            LinkStatus::Expired => '期限切れ',
            LinkStatus::ExpiringSoon => self::remainingLabel($now, $expiresAt),
            default => $expiresAt->setTimezone($timezone)->format('Y/m/d H:i').' まで',
        };
    }

    private static function remainingLabel(CarbonImmutable $now, CarbonImmutable $expiresAt): string
    {
        $hours = (int) floor(abs($now->diffInHours($expiresAt)));

        if ($hours < 24) {
            return '残り'.max(1, $hours).'時間';
        }

        return '残り'.intdiv($hours, 24).'日';
    }
}
