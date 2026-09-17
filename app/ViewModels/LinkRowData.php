<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\LinkStatus;
use App\Models\ShortUrl;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;

/** ダッシュボードの発行履歴テーブル 1 行分 */
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
    ) {}

    public static function fromModel(
        ShortUrl $link,
        ShortUrlBuilder $urls,
        CarbonImmutable $now,
        int $warningDays,
        string $timezone,
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
        );
    }

    /** design.md のテーブル表記（無期限 / 残りN日 / 期限切れ）に合わせる */
    private static function expiryLabel(?CarbonImmutable $expiresAt, LinkStatus $status, CarbonImmutable $now, string $timezone): string
    {
        if ($expiresAt === null) {
            return '無期限';
        }

        return match ($status) {
            LinkStatus::Expired => '期限切れ',
            LinkStatus::ExpiringSoon => self::remainingLabel($now, $expiresAt),
            LinkStatus::Active => $expiresAt->setTimezone($timezone)->format('Y/m/d H:i').' まで',
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
