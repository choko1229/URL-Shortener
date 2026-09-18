<?php

declare(strict_types=1);

namespace App\ViewModels;

use Carbon\CarbonImmutable;

/** リンクごとの統計（requirements.md 2-6: クリック数・リファラ・国・デバイス種別） */
final readonly class LinkStatsData
{
    /**
     * @param  list<array{date: string, label: string, count: int}>  $daily  直近の日別クリック数（古い順）
     * @param  list<array{label: string, count: int}>  $referrers
     * @param  list<array{label: string, count: int}>  $countries
     * @param  list<array{label: string, count: int}>  $devices
     */
    public function __construct(
        public LinkRowData $link,
        public string $originalUrl,
        public ?string $ownerLabel,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $lastClickedAt,
        public int $totalClicks,
        public int $recentClicks,
        public int $recentDays,
        public array $daily,
        public array $referrers,
        public array $countries,
        public array $devices,
    ) {}

    public function dailyMax(): int
    {
        return max(1, ...array_map(static fn (array $day): int => $day['count'], $this->daily));
    }
}
