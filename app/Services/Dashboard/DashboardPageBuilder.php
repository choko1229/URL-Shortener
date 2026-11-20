<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\LinkSource;
use App\Models\ShortUrl;
use App\Models\User;
use App\Support\LinkSort;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\ViewModels\DashboardPageData;
use App\ViewModels\DashboardStatsData;
use App\ViewModels\IssuedLinkData;
use App\ViewModels\LinkRowData;
use App\ViewModels\ShortUrlFormData;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/** ダッシュボード表示用データの読み出し（参照のみ。書き込みは行わない） */
final class DashboardPageBuilder
{
    public function __construct(
        private readonly ShortenerSettings $settings,
        private readonly ShortUrlBuilder $urls,
    ) {}

    public function build(User $user, CarbonImmutable $now, ?IssuedLinkData $issuedLink = null, LinkSort $sort = new LinkSort, LinkSource $source = LinkSource::Page): DashboardPageData
    {
        $viewer = ViewerData::fromUser($user);
        $form = ShortUrlFormData::build(true, $this->settings, $this->urls, $now, isAdmin: $user->isAdmin());

        try {
            $stats = $this->stats($user, $now);
            $links = $this->links($user, $now, $sort, $stats->showApi ? $source : null);
            $pageCount = $stats->showApi ? ShortUrl::query()->ownedBy($user)->fromSource(LinkSource::Page)->count() : null;
            $apiCount = $stats->showApi ? ShortUrl::query()->ownedBy($user)->fromSource(LinkSource::Api)->count() : null;
        } catch (QueryException $e) {
            Log::error('ダッシュボードのデータ取得に失敗しました。', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return new DashboardPageData($viewer, $form, stats: null, links: null, issuedLink: $issuedLink, displayTimezone: $this->settings->displayTimezone(), sort: $sort);
        }

        return new DashboardPageData(
            $viewer,
            $form,
            $stats,
            $links,
            $issuedLink,
            $this->settings->displayTimezone(),
            $sort,
            $stats->showApi ? $source : LinkSource::Page,
            $pageCount,
            $apiCount,
        );
    }

    private function stats(User $user, CarbonImmutable $now): DashboardStatsData
    {
        // 月の区切りは表示タイムゾーン（JST）の暦月で判定する
        $localNow = $now->setTimezone($this->settings->displayTimezone());
        $monthStart = $localNow->startOfMonth()->utc();
        $monthEnd = $localNow->endOfMonth()->utc();

        $monthly = static fn (LinkSource $source): int => ShortUrl::withTrashed()
            ->ownedBy($user)
            ->fromSource($source)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        return new DashboardStatsData(
            // 削除→再発行で上限を回避できないよう、論理削除済みも発行数に含める。API で発行した分は別に数える
            monthlyIssued: $monthly(LinkSource::Page),
            monthlyLimit: $this->settings->memberMonthlyLimit(),
            totalClicks: (int) ShortUrl::query()->ownedBy($user)->sum('click_count'),
            activeLinks: ShortUrl::query()->ownedBy($user)->activeAt($now)->count(),
            apiMonthlyIssued: $monthly(LinkSource::Api),
            showApi: $user->isAdmin() || ShortUrl::withTrashed()->ownedBy($user)->fromSource(LinkSource::Api)->exists(),
        );
    }

    /** @return LengthAwarePaginator<int, LinkRowData> */
    /** @param  LinkSource|null  $source  null ならタブで分けずにすべて */
    private function links(User $user, CarbonImmutable $now, LinkSort $sort, ?LinkSource $source): LengthAwarePaginator
    {
        $warningDays = $this->settings->expiryWarningDays();
        $timezone = $this->settings->displayTimezone();
        $query = ShortUrl::query()->ownedBy($user);

        if ($source !== null) {
            $query->fromSource($source);
        }

        return $sort->apply($query)
            ->paginate($this->settings->dashboardLinksPerPage())
            ->withQueryString()
            ->through(fn (ShortUrl $link): LinkRowData => LinkRowData::fromModel($link, $this->urls, $now, $warningDays, $timezone));
    }
}
