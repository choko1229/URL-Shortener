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
        // 今月 API で発行した件数（月間上限とは別枠）
        public int $apiMonthlyIssued = 0,
        // API の件数を別に表示するか（管理者、または API で発行したリンクがある場合）
        public bool $showApi = false,
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
