<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\LinkSource;
use App\Support\LinkSort;
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
        // 直前に発行した短縮URL（発行結果パネルに表示）
        public ?IssuedLinkData $issuedLink,
        public string $displayTimezone,
        // 発行履歴の並び順
        public LinkSort $sort = new LinkSort,
        // 発行履歴のタブ（ページで発行／API で発行）
        public LinkSource $source = LinkSource::Page,
        // タブごとの件数（削除していないもの）。API のタブを出さない場合は null
        public ?int $pageLinkCount = null,
        public ?int $apiLinkCount = null,
    ) {}

    /** 発行履歴を「ページで発行／API で発行」のタブに分けるか */
    public function hasSourceTabs(): bool
    {
        return $this->apiLinkCount !== null;
    }

    /** DB 障害などで統計・履歴を取得できなかった場合 true（発行フォームは表示を続ける） */
    public function dataUnavailable(): bool
    {
        return $this->stats === null || $this->links === null;
    }
}
