<?php

declare(strict_types=1);

namespace App\ViewModels;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class DashboardPageData
{
    /**
     * @param  LengthAwarePaginator<int, LinkRowData>|null  $links
     */
    public function __construct(
        public ViewerData $viewer,
        public ShortUrlFormData $form,
        public ?DashboardStatsData $stats,
        public ?LengthAwarePaginator $links,
    ) {}

    /** DB 障害などで統計・履歴を取得できなかった場合 true（発行フォームは表示を続ける） */
    public function dataUnavailable(): bool
    {
        return $this->stats === null || $this->links === null;
    }
}
