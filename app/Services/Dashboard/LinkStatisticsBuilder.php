<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\DeviceType;
use App\Models\ShortUrl;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\ViewModels\LinkRowData;
use App\ViewModels\LinkStatsData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Locale;

/** リンクごとの統計画面のデータ（参照のみ） */
final class LinkStatisticsBuilder
{
    private const RECENT_DAYS = 30;

    private const TOP_LIMIT = 10;

    public function __construct(
        private readonly ShortenerSettings $settings,
        private readonly ShortUrlBuilder $urls,
    ) {}

    public function build(ShortUrl $link, CarbonImmutable $now): LinkStatsData
    {
        $timezone = $this->settings->displayTimezone();
        $daily = $this->daily($link, $now, $timezone);
        $row = LinkRowData::fromModel($link, $this->urls, $now, $this->settings->expiryWarningDays(), $timezone, withOwner: true);

        return new LinkStatsData(
            link: $row,
            originalUrl: $link->original_url,
            ownerLabel: $row->ownerLabel,
            createdAt: ($link->created_at ?? $now)->setTimezone($timezone),
            lastClickedAt: $link->last_clicked_at?->setTimezone($timezone),
            totalClicks: $link->click_count,
            recentClicks: array_sum(array_column($daily, 'count')),
            recentDays: self::RECENT_DAYS,
            daily: $daily,
            referrers: $this->grouped($link, 'referrer_host', static fn (?string $host): string => $host ?? '直接アクセス・不明'),
            countries: $this->grouped($link, 'country_code', self::countryName(...)),
            devices: $this->grouped($link, 'device_type', static fn (?string $type): string => DeviceType::tryFrom((string) $type)?->label() ?? '不明'),
            previewMode: $link->preview_mode,
            previewTitle: $link->preview_title,
            previewDescription: $link->preview_description,
            previewImageUrl: $link->preview_image_url,
        );
    }

    /** @return list<array{date: string, label: string, count: int}> */
    private function daily(ShortUrl $link, CarbonImmutable $now, string $timezone): array
    {
        $today = $now->setTimezone($timezone)->startOfDay();
        $from = $today->subDays(self::RECENT_DAYS - 1);

        $counts = [];
        foreach ($link->clicks()->where('clicked_at', '>=', $from->utc())->pluck('clicked_at') as $clickedAt) {
            $date = CarbonImmutable::parse($clickedAt, 'UTC')->setTimezone($timezone)->format('Y-m-d');
            $counts[$date] = ($counts[$date] ?? 0) + 1;
        }

        $days = [];
        for ($i = 0; $i < self::RECENT_DAYS; $i++) {
            $day = $from->addDays($i);
            $key = $day->format('Y-m-d');
            $days[] = ['date' => $key, 'label' => $day->format('n/j'), 'count' => $counts[$key] ?? 0];
        }

        return $days;
    }

    /**
     * @param  callable(?string): string  $label
     * @return list<array{label: string, count: int}>
     */
    private function grouped(ShortUrl $link, string $column, callable $label): array
    {
        return $link->clicks()
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(self::TOP_LIMIT)
            ->toBase()
            ->get()
            ->map(static fn (object $row): array => [
                'label' => $label(is_string($row->{$column}) ? $row->{$column} : null),
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private static function countryName(?string $code): string
    {
        if ($code === null) {
            return '不明';
        }

        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-'.$code, 'ja') : '';

        return $name !== '' && $name !== $code ? "{$name}（{$code}）" : $code;
    }
}
