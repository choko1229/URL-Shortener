{{-- 発行履歴の「インポート」から開く。ファイルの選び直しが必要なとき（検証エラー）は開いた状態で表示する --}}
<dialog
    id="link-import-dialog"
    class="dialog"
    aria-labelledby="link-import-dialog-title"
    @if ($errors->has('file')) data-dialog-auto-open @endif
>
    <form method="POST" action="{{ route('dashboard.links.import') }}" enctype="multipart/form-data" class="p-6 sm:p-8">
        @csrf
        <h2 id="link-import-dialog-title" class="font-rounded text-section font-bold">発行履歴をインポート</h2>
        <p class="mt-2 text-sm text-text-secondary">
            別のサーバーの発行履歴で「エクスポート」した CSV を取り込みます。短縮URLは同じコードのまま引き継ぎ、パスワードや有効期限、SNS のカードもそのまま移ります。
        </p>
        <p class="mt-2 text-sm text-text-secondary">
            このサーバーで同じ短縮URLがすでに使われている場合は、取り込む前にどうするか確認します。
        </p>

        <label for="link-import-file" class="mt-5 block text-[13px] font-medium text-text-secondary">CSVファイル</label>
        <input
            id="link-import-file"
            name="file"
            type="file"
            accept=".csv,text/csv"
            required
            class="mt-1.5 block w-full text-sm file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border-[1.5px] file:border-primary file:bg-surface file:px-4 file:font-rounded file:font-bold file:text-text-primary hover:file:bg-primary-tint"
            aria-describedby="link-import-file-hint{{ $errors->has('file') ? ' link-import-file-error' : '' }}"
        >
        <p id="link-import-file-hint" class="mt-1.5 text-xs text-text-secondary">
            一度に {{ \App\Services\ShortUrl\Transfer\LinkTransfer::MAX_ROWS }} 行まで。文字コードは UTF-8 と Shift_JIS のどちらでも読めます。
        </p>
        <x-field-error name="file" id="link-import-file-error" />

        <div class="mt-6 flex flex-wrap justify-end gap-2.5">
            <x-button type="submit" variant="secondary" size="sm" formmethod="dialog" formnovalidate>キャンセル</x-button>
            <x-button type="submit" size="sm">
                <x-icon name="upload" :size="16" />
                読み込む
            </x-button>
        </div>
    </form>
</dialog>
