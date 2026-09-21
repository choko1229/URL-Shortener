{{--
    全URLの管理（管理者）
    @var \App\ViewModels\ViewerData $viewer
    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $links
    @var array{owner: string, state: string, q: string} $filters
--}}
<x-dashboard.admin-page :viewer="$viewer" title="全URL" description="すべての短縮URLを確認・削除できます。ログインせずに発行されたURLの統計も確認できます。">
    @php $importResult = session(\App\Http\Controllers\Admin\LinkImportController::RESULT_SESSION_KEY); @endphp

    <section aria-labelledby="import-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="import-heading" class="font-rounded text-[15px] font-bold">CSVでまとめて発行</h2>
            <x-button variant="secondary" size="sm" :href="route('dashboard.admin.links.import.template')" download>
                <x-icon name="copy" :size="16" />
                テンプレートをダウンロード
            </x-button>
        </div>
        <p class="mt-2 text-[13px] leading-relaxed text-text-secondary">
            1行目に見出し、2行目以降にURLを並べた CSV を取り込みます。発行した短縮URLの発行者はあなたになります。月間上限・予約語・スラッグの文字数の設定は適用しません（スラッグは1〜20文字）。一度に{{ \App\Services\ShortUrl\CsvImporter::MAX_ROWS }}行まで。
        </p>

        <form method="POST" action="{{ route('dashboard.admin.links.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            <div class="min-w-0 flex-1">
                <label for="import-file" class="block text-[13px] font-medium text-text-secondary">CSVファイル</label>
                <input
                    id="import-file"
                    name="file"
                    type="file"
                    accept=".csv,text/csv"
                    required
                    class="mt-2 block w-full text-sm text-text-secondary file:mr-3 file:min-h-10 file:cursor-pointer file:rounded-control file:border-[1.5px] file:border-primary file:bg-surface file:px-4 file:text-[13px] file:font-medium file:text-text-primary hover:file:bg-primary-tint"
                    aria-describedby="import-file-hint{{ $errors->has('file') ? ' import-file-error' : '' }}"
                >
                <p id="import-file-hint" class="mt-1.5 text-xs text-text-secondary">
                    列は {{ implode(' / ', \App\Services\ShortUrl\CsvImporter::COLUMNS) }}（url 以外は空欄で構いません）。文字コードは UTF-8 と Shift_JIS のどちらでも読めます。
                </p>
                <x-field-error name="file" id="import-file-error" />
            </div>
            <x-button type="submit" size="sm" class="min-h-[52px] sm:mt-[30px]">取り込む</x-button>
        </form>

        @if ($importResult && $importResult->errors !== [])
            <div class="mt-4 rounded-control border border-danger/40 bg-primary-tint-soft p-4" role="alert">
                <p class="text-[13px] font-medium text-danger">取り込めなかった行（{{ $importResult->skipped() }}件）</p>
                <ul class="mt-2 space-y-1 text-[13px] text-text-secondary">
                    @foreach (array_slice($importResult->errors, 0, 20, true) as $line => $message)
                        <li><span class="font-medium text-text-primary">{{ $line }}行目:</span> {{ $message }}</li>
                    @endforeach
                </ul>
                @if ($importResult->skipped() > 20)
                    <p class="mt-2 text-xs text-text-secondary">ほか {{ $importResult->skipped() - 20 }}行</p>
                @endif
            </div>
        @endif
    </section>

    <form method="GET" action="{{ route('dashboard.admin.links') }}" class="flex flex-col gap-3 rounded-card border border-border bg-surface p-4 sm:flex-row sm:items-end sm:p-5">
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

    <section aria-labelledby="all-links-heading" class="overflow-hidden rounded-card border border-border bg-surface">
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
</x-dashboard.admin-page>
