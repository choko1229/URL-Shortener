<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShortUrl;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use App\ViewModels\LinkRowData;
use App\ViewModels\ViewerData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** 全URLの管理（requirements.md 4-2: 管理者は全URLを管理・削除、未ログイン発行分の統計も閲覧できる） */
final class LinkController extends Controller
{
    private const PER_PAGE = 20;

    /** @var list<string> */
    private const OWNER_FILTERS = ['all', 'guest', 'member'];

    /** @var list<string> */
    private const STATE_FILTERS = ['active', 'deleted', 'all'];

    public function index(Request $request, ShortenerSettings $settings, ShortUrlBuilder $urls): View
    {
        $owner = in_array($request->query('owner'), self::OWNER_FILTERS, true) ? (string) $request->query('owner') : 'all';
        $state = in_array($request->query('state'), self::STATE_FILTERS, true) ? (string) $request->query('state') : 'active';
        $keyword = trim((string) $request->query('q', ''));
        $keyword = mb_substr($keyword, 0, 100);

        $query = ShortUrl::withTrashed()->with('user')->latest('id');

        match ($state) {
            'active' => $query->whereNull('deleted_at'),
            'deleted' => $query->whereNotNull('deleted_at'),
            default => null,
        };

        match ($owner) {
            'guest' => $query->whereNull('user_id'),
            'member' => $query->whereNotNull('user_id'),
            default => null,
        };

        if ($keyword !== '') {
            $like = '%'.addcslashes($keyword, '%_\\').'%';
            $query->where(static function (Builder $q) use ($like): void {
                $q->where('slug', 'like', $like)->orWhere('original_url', 'like', $like);
            });
        }

        $now = CarbonImmutable::now();
        $warningDays = $settings->expiryWarningDays();
        $timezone = $settings->displayTimezone();

        return view('dashboard.admin.links', [
            'viewer' => ViewerData::fromUser($request->user()),
            'links' => $query->paginate(self::PER_PAGE)->withQueryString()
                ->through(static fn (ShortUrl $link): LinkRowData => LinkRowData::fromModel($link, $urls, $now, $warningDays, $timezone, withOwner: true)),
            'filters' => ['owner' => $owner, 'state' => $state, 'q' => $keyword],
        ]);
    }
}
