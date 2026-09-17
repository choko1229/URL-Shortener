<?php

declare(strict_types=1);

namespace App\ViewModels;

final readonly class DashboardStatsData
{
    public function __construct(
        public int $monthlyIssued,
        public int $monthlyLimit,
        public int $totalClicks,
        public int $activeLinks,
    ) {}

    public function remainingThisMonth(): int
    {
        return max(0, $this->monthlyLimit - $this->monthlyIssued);
    }

    public function hasReachedLimit(): bool
    {
        return $this->remainingThisMonth() === 0;
    }
}
