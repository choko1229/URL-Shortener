{{--
    発行履歴のインポートで、このサーバーで同じ短縮URLがすでに使われていた行の扱いを選ぶ。
    選ぶまでは何も取り込まない（取り込み待ちの内容はキャッシュに 30 分だけ置く）。
    @var array{token: string, ready: int, failed: int, conflicts: list<array{line: int, slug: string, kind: string, reason: string, import_url: string, current_url: ?string}>} $pending
--}}
@use('App\Services\ShortUrl\Transfer\LinkTransfer')
@use('App\Services\ShortUrl\Transfer\LinkTransferConflict')
@php
    $shortUrls = app(\App\Support\ShortUrlBuilder::class);
    $conflicts = $pending['conflicts'];
    $hasOwn = collect($conflicts)->contains(static fn (array $c): bool => $c['kind'] === LinkTransferConflict::OWN);
@endphp

<div class="border-b border-table-divider bg-primary-tint-soft px-5 py-5 sm:px-6" data-transfer-confirm>
    <div class="flex items-start gap-2.5">
        <x-icon name="alert-circle" :size="18" class="mt-0.5 shrink-0 text-primary-dark" />
        <div class="min-w-0">
            <h3 class="font-rounded text-[15px] font-bold">同じ短縮URLがすでに使われています（{{ count($conflicts) }}件）</h3>
            <p class="mt-1 text-sm text-text-secondary">
                まだ何も取り込んでいません。それぞれどうするか選んでから「取り込む」を押してください。
                重なっていない {{ number_format($pending['ready']) }} 件は、そのまま同じ短縮URLで取り込みます。
                @if ($pending['failed'] > 0)
                    内容に問題のある {{ number_format($pending['failed']) }} 行は取り込みません（取り込んだあとに理由を表示します）。
                @endif
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('dashboard.links.import.confirm') }}" class="mt-4">
        @csrf
        <input type="hidden" name="token" value="{{ $pending['token'] }}">

        <div class="flex flex-wrap items-center gap-2 text-[13px]">
            <span class="text-text-secondary">すべてを:</span>
            @if ($hasOwn)
                <x-button variant="secondary" size="sm" data-transfer-bulk="{{ LinkTransfer::OVERWRITE }}">上書き（自分の短縮URLのみ）</x-button>
            @endif
            <x-button variant="secondary" size="sm" data-transfer-bulk="{{ LinkTransfer::REISSUE }}">新しい短縮URLで発行</x-button>
            <x-button variant="secondary" size="sm" data-transfer-bulk="{{ LinkTransfer::SKIP }}">スキップ</x-button>
        </div>

        <ul class="mt-4 flex flex-col gap-3">
            @foreach ($conflicts as $conflict)
                @php $name = 'resolutions['.$conflict['line'].']'; @endphp
                <li class="rounded-control border border-border bg-surface p-4">
                    <fieldset>
                        <legend class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="font-mono text-sm font-bold">{{ $shortUrls->display($conflict['slug']) }}</span>
                            <span class="text-xs text-text-secondary">{{ $conflict['line'] }}行目</span>
                        </legend>
                        <p class="mt-1 text-[13px] text-text-secondary">{{ $conflict['reason'] }}</p>
                        <dl class="mt-2 grid gap-1 text-[13px] sm:grid-cols-[auto_1fr] sm:gap-x-3">
                            @if ($conflict['current_url'] !== null)
                                <dt class="text-text-secondary">今の転送先</dt>
                                <dd class="break-all font-mono">{{ $conflict['current_url'] }}</dd>
                            @endif
                            <dt class="text-text-secondary">CSV の転送先</dt>
                            <dd class="break-all font-mono">{{ $conflict['import_url'] }}</dd>
                        </dl>

                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                            @if ($conflict['kind'] === LinkTransferConflict::OWN)
                                <label class="flex min-h-11 cursor-pointer items-center gap-2">
                                    <input type="radio" name="{{ $name }}" value="{{ LinkTransfer::OVERWRITE }}" class="size-4 accent-primary">
                                    CSV の内容で上書き
                                </label>
                            @endif
                            <label class="flex min-h-11 cursor-pointer items-center gap-2">
                                <input type="radio" name="{{ $name }}" value="{{ LinkTransfer::REISSUE }}" class="size-4 accent-primary">
                                新しい短縮URLで発行
                            </label>
                            <label class="flex min-h-11 cursor-pointer items-center gap-2">
                                <input type="radio" name="{{ $name }}" value="{{ LinkTransfer::SKIP }}" class="size-4 accent-primary" checked>
                                スキップ（取り込まない）
                            </label>
                        </div>
                    </fieldset>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 flex flex-wrap justify-end gap-2.5">
            <x-button type="submit" variant="secondary" size="sm" form="link-transfer-cancel">取り込みをやめる</x-button>
            <x-button type="submit" size="sm">取り込む</x-button>
        </div>
    </form>

    <form id="link-transfer-cancel" method="POST" action="{{ route('dashboard.links.import.cancel') }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>
