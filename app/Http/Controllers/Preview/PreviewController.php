<?php

declare(strict_types=1);

namespace App\Http\Controllers\Preview;

use App\Enums\SlugType;
use App\Http\Controllers\Controller;
use App\Models\ShortUrl;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\ViewModels\DashboardPageData;
use App\ViewModels\DashboardStatsData;
use App\ViewModels\HomePageData;
use App\ViewModels\IssuedLinkData;
use App\ViewModels\LinkRowData;
use App\ViewModels\ShortUrlFormData;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 【local 環境専用】認証・保存処理の実装前に画面を確認するためのプレビュー。
 * DB を使わず固定のサンプルデータで描画する。routes/preview.php は APP_ENV=local のときだけ読み込まれる。
 */
final class PreviewController extends Controller
{
    public function __construct(
        private readonly ShortenerSettings $settings,
        private readonly ShortUrlBuilder $urls,
    ) {}

    public function home(): View
    {
        $now = CarbonImmutable::now();

        return view('main.home', [
            'page' => new HomePageData(
                viewer: ViewerData::guest(),
                form: ShortUrlFormData::build(false, $this->settings, $this->urls, $now),
                issuedLink: new IssuedLinkData(
                    shortUrl: $this->urls->url('aB3xQ9k'),
                    displayUrl: $this->urls->display('aB3xQ9k'),
                    originalUrl: 'https://example.com/very/long/path/to/shorten?utm_source=preview',
                    expiresAt: $now->addDays(30),
                    isPasswordProtected: false,
                    deletionToken: 'preview-3f9a1c7e5b2d4a8c9e0f1b2c3d4e5f6a',
                    qrCodeDataUri: null,
                ),
                displayTimezone: $this->settings->displayTimezone(),
            ),
        ]);
    }

    public function dashboard(Request $request): View
    {
        return $this->renderDashboard($request, new ViewerData(true, false, 'ちょこ', null));
    }

    public function adminDashboard(Request $request): View
    {
        return $this->renderDashboard($request, new ViewerData(true, true, 'ちょこ', null));
    }

    private function renderDashboard(Request $request, ViewerData $viewer): View
    {
        $now = CarbonImmutable::now();
        $warningDays = $this->settings->expiryWarningDays();
        $timezone = $this->settings->displayTimezone();

        $rows = array_map(
            fn (ShortUrl $link): LinkRowData => LinkRowData::fromModel($link, $this->urls, $now, $warningDays, $timezone),
            $this->sampleLinks($now),
        );

        return view('dashboard.index', [
            'page' => new DashboardPageData(
                viewer: $viewer,
                form: ShortUrlFormData::build(true, $this->settings, $this->urls, $now),
                stats: new DashboardStatsData(
                    monthlyIssued: 18,
                    monthlyLimit: $this->settings->memberMonthlyLimit(),
                    totalClicks: 1204,
                    activeLinks: 12,
                ),
                links: new LengthAwarePaginator($rows, total: 24, perPage: count($rows), currentPage: 1, options: [
                    'path' => $request->url(),
                ]),
            ),
        ]);
    }

    /** @return list<ShortUrl> 保存しないメモリ上のモデル */
    private function sampleLinks(CarbonImmutable $now): array
    {
        $samples = [
            ['aB3xQ9k', SlugType::Random, 'https://github.com/choko1229/chok-ooo/pulls?q=is%3Aopen', 312, null, false],
            ['kanri-memo', SlugType::Custom, 'https://www.notion.so/choko/vrc-preparation-checklist-2026', 58, $now->addDays(2), true],
            ['x7Rp2mQ', SlugType::Random, 'https://booth.pm/ja/items/12345678', 804, $now->addDays(21), false],
            ['p9Ln4wZ', SlugType::Random, 'https://drive.google.com/file/d/1a2b3c4d5e6f7g8h9i0j/view', 9, $now->subDay(), false],
        ];

        return array_map(static function (array $sample): ShortUrl {
            [$slug, $type, $url, $clicks, $expiresAt, $protected] = $sample;

            $link = new ShortUrl;
            $link->forceFill([
                'id' => crc32($slug),
                'slug' => $slug,
                'slug_type' => $type,
                'original_url' => $url,
                'click_count' => $clicks,
                'expires_at' => $expiresAt,
                'password_hash' => $protected ? 'preview' : null,
            ]);

            return $link;
        }, $samples);
    }
}
