{{--
    全URLの管理（管理者）
    @var \App\ViewModels\ViewerData $viewer
    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $links
    @var array{owner: string, state: string, q: string} $filters
--}}
<x-dashboard.page :viewer="$viewer" title="全URL" description="すべての短縮URLを確認・削除できます。ログインせずに発行されたURLの統計も確認できます。">
    <form method="GET" action="{{ route('dashboard.admin.links') }}" class="flex flex-col gap-3 rounded-card border border-border bg-white p-4 sm:flex-row sm:items-end sm:p-5">
        <div class="min-w-0 flex-1">
            <label for="filter-q" class="block text-[13px] font-medium text-text-secondary">コード・元URLで検索</label>
            <input id="filter-q" name="q" type="search" value="{{ $filters['q'] }}" class="form-control mt-2" autocomplete="off">
        </div>
        <div>
            <label for="filter-owner" class="block text-[13px] font-medium text-text-secondary">発行者</label>
            <select id="filter-owner" name="owner" class="form-control mt-2">
                <option value="all" @selected($filters['owner'] === 'all')>すべて</option>
                <option value="member" @selected($filters['owner'] === 'member')>ログインユーザー</option>
                <option value="guest" @selected($filters['owner'] === 'guest')>未ログイン</option>
            </select>
        </div>
        <div>
            <label for="filter-state" class="block text-[13px] font-medium text-text-secondary">状態</label>
            <select id="filter-state" name="state" class="form-control mt-2">
                <option value="active" @selected($filters['state'] === 'active')>削除されていないもの</option>
                <option value="deleted" @selected($filters['state'] === 'deleted')>削除済み</option>
                <option value="all" @selected($filters['state'] === 'all')>すべて</option>
            </select>
        </div>
        <x-button type="submit" variant="secondary" size="sm" class="min-h-[52px]">絞り込む</x-button>
    </form>

    <section aria-labelledby="all-links-heading" class="overflow-hidden rounded-card border border-border bg-white">
        <div class="flex items-center justify-between gap-4 border-b border-table-divider px-5 py-5 sm:px-6">
            <h2 id="all-links-heading" class="font-rounded text-[15px] font-bold">短縮URL一覧</h2>
            <p class="text-xs text-text-secondary">全 {{ number_format($links->total()) }} 件</p>
        </div>

        @if ($links->isEmpty())
            <p class="px-6 py-14 text-center text-sm text-text-secondary">条件に合う短縮URLはありません。</p>
        @else
            @include('partials.link-table', [
                'links' => $links,
                'headingId' => 'all-links-heading',
                'caption' => 'すべての短縮URLの一覧（新しい順）',
                'showOwner' => true,
            ])
        @endif
    </section>

    @include('partials.qr-dialog')
</x-dashboard.page>
