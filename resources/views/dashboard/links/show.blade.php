{{--
    短縮URLの詳細（requirements.md 2-4 編集・削除、2-6 統計、6 QRコード）
    @var \App\ViewModels\ViewerData $viewer
    @var \App\ViewModels\LinkStatsData $stats
    @var bool $canEdit
    @var int $slugMinLength
    @var int $slugMaxLength
--}}
@php
    $link = $stats->link;
    $dailyMax = $stats->dailyMax();
    $breakdowns = [
        'referrers' => ['title' => 'リファラ（流入元）', 'rows' => $stats->referrers],
        'countries' => ['title' => '国', 'rows' => $stats->countries],
        'devices' => ['title' => 'デバイス', 'rows' => $stats->devices],
    ];
@endphp

<x-layouts.dashboard :viewer="$viewer" :title="$link->displayUrl.' の詳細'">
    <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 pb-12 pt-6 sm:gap-7 sm:px-8 sm:pt-8 lg:px-12">
        <div>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard.home') }}" class="text-sm text-primary-dark hover:text-primary-darker">一覧に戻る</a>
            <h1 class="mt-2 flex flex-wrap items-center gap-2 font-rounded text-section font-bold">
                <span class="break-all">{{ $link->displayUrl }}</span>
                <x-link-status :status="$link->status" :label="$link->expiryLabel" />
            </h1>
            <p class="mt-1 break-all text-sm text-text-secondary">{{ $stats->originalUrl }}</p>
        </div>

        <x-flash-messages />

        <section aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="sr-only">概要</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5">
                <div class="rounded-card border border-border bg-white px-6 py-[22px]">
                    <dt class="text-[13px] text-text-secondary">総クリック数</dt>
                    <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($stats->totalClicks) }}</dd>
                </div>
                <div class="rounded-card border border-border bg-white px-6 py-[22px]">
                    <dt class="text-[13px] text-text-secondary">直近{{ $stats->recentDays }}日のクリック数</dt>
                    <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($stats->recentClicks) }}</dd>
                </div>
                <div class="rounded-card border border-border bg-white px-6 py-[22px]">
                    <dt class="text-[13px] text-text-secondary">最終クリック</dt>
                    <dd class="mt-2.5 font-rounded text-xl font-bold">{{ $stats->lastClickedAt?->format('Y/m/d H:i') ?? 'まだありません' }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="daily-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
            <h2 id="daily-heading" class="font-rounded text-[15px] font-bold">日別クリック数（直近{{ $stats->recentDays }}日）</h2>

            {{-- グラフは視覚用。数値は下の表で確認できる --}}
            <div class="mt-5" aria-hidden="true">
                <div class="flex h-40 items-end gap-[2px] border-b border-border-strong">
                    @foreach ($stats->daily as $day)
                        <div class="group relative flex h-full min-w-0 flex-1 items-end">
                            <div
                                class="w-full rounded-t-[4px] bg-primary-dark transition-opacity group-hover:opacity-80"
                                style="height: {{ $day['count'] > 0 ? max(2, round($day['count'] / $dailyMax * 100, 1)) : 0 }}%"
                            ></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-control bg-dark-panel px-2 py-1 text-xs text-white group-hover:block">
                                {{ $day['label'] }}: {{ number_format($day['count']) }}件
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex justify-between text-[11px] text-text-secondary">
                    <span>{{ $stats->daily[0]['label'] ?? '' }}</span>
                    <span>{{ $stats->daily[array_key_last($stats->daily)]['label'] ?? '' }}</span>
                </div>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer text-primary-dark hover:text-primary-darker">表で見る</summary>
                <div class="mt-3 max-h-72 overflow-y-auto">
                    <table class="w-full border-collapse text-left text-[13px]">
                        <caption class="sr-only">日別クリック数</caption>
                        <thead class="bg-table-header">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-xs font-medium text-text-secondary">日付</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-text-secondary">クリック数</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_reverse($stats->daily) as $day)
                                <tr class="border-t border-table-divider">
                                    <td class="px-4 py-1.5">{{ $day['date'] }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ number_format($day['count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            @foreach ($breakdowns as $key => $breakdown)
                @php
                    $max = max(1, ...array_map(static fn (array $row): int => $row['count'], $breakdown['rows'] ?: [['count' => 1]]));
                @endphp
                <section aria-labelledby="{{ $key }}-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
                    <h2 id="{{ $key }}-heading" class="font-rounded text-[15px] font-bold">{{ $breakdown['title'] }}</h2>
                    @if ($breakdown['rows'] === [])
                        <p class="mt-4 text-sm text-text-secondary">まだ記録がありません。</p>
                    @else
                        <table class="mt-4 w-full border-collapse text-left text-[13px]">
                            <caption class="sr-only">{{ $breakdown['title'] }}ごとのクリック数（上位10件）</caption>
                            <thead class="sr-only">
                                <tr><th scope="col">項目</th><th scope="col">クリック数</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($breakdown['rows'] as $row)
                                    <tr>
                                        <td class="py-1.5 pr-3">
                                            <span class="block truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                                            <span class="mt-1 block h-1.5 rounded-full bg-primary-tint" aria-hidden="true">
                                                <span class="block h-full rounded-full bg-primary-dark" style="width: {{ round($row['count'] / $max * 100, 1) }}%"></span>
                                            </span>
                                        </td>
                                        <td class="w-16 py-1.5 text-right align-top tabular-nums">{{ number_format($row['count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endforeach
        </div>

        <section aria-labelledby="manage-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
            <h2 id="manage-heading" class="font-rounded text-[15px] font-bold">管理</h2>
            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-xs text-text-secondary">発行者</dt><dd class="mt-0.5">{{ $stats->ownerLabel }}</dd></div>
                <div><dt class="text-xs text-text-secondary">発行日時</dt><dd class="mt-0.5">{{ $stats->createdAt->format('Y/m/d H:i') }}</dd></div>
                <div><dt class="text-xs text-text-secondary">パスワード保護</dt><dd class="mt-0.5">{{ $link->isPasswordProtected ? 'あり' : 'なし' }}</dd></div>
            </dl>

            @unless ($link->isDeleted())
                <div class="mt-5 flex flex-wrap gap-2.5">
                    <x-copy-button :text="$link->shortUrl" :label="$link->displayUrl.' をコピー'" variant="ghost" />
                    <x-button variant="secondary" size="sm" aria-haspopup="dialog" data-qr-open :data-qr-src="route('dashboard.links.qr', ['shortUrl' => $link->id])" :data-qr-label="$link->displayUrl">
                        <x-icon name="qr-code" :size="16" />
                        QRコード
                    </x-button>
                </div>
            @endunless

            @if ($canEdit)
                <form method="POST" action="{{ route('dashboard.links.slug', ['shortUrl' => $link->id]) }}" class="mt-6 max-w-xl space-y-2" data-confirm="カスタムスラッグを変更しますか？以前の短縮URLは使えなくなり、再利用もできません。">
                    @csrf
                    @method('PATCH')
                    <label for="custom-slug" class="block text-[13px] font-medium text-text-secondary">カスタムスラッグを変更</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            id="custom-slug"
                            name="custom_slug"
                            type="text"
                            required
                            minlength="{{ $slugMinLength }}"
                            maxlength="{{ $slugMaxLength }}"
                            pattern="[A-Za-z0-9_\-]+"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            value="{{ old('custom_slug', $link->isCustomSlug ? $link->slug : '') }}"
                            class="form-control"
                            aria-describedby="custom-slug-hint{{ $errors->has('custom_slug') ? ' custom-slug-error' : '' }}"
                            @error('custom_slug') aria-invalid="true" @enderror
                        >
                        <x-button type="submit" size="sm">変更する</x-button>
                    </div>
                    <p id="custom-slug-hint" class="text-xs text-text-secondary">
                        変更できるのはカスタムスラッグのみです（元URLは変更できません）。以前の短縮URLは使えなくなり、再利用もできません。
                    </p>
                    <x-field-error name="custom_slug" id="custom-slug-error" />
                </form>

                <form method="POST" action="{{ route('dashboard.links.destroy', ['shortUrl' => $link->id]) }}" class="mt-6" data-confirm="{{ $link->displayUrl }} を削除しますか？削除したコードは再利用できません。">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger-ghost" size="sm" class="border border-danger/40">
                        <x-icon name="trash" :size="16" />
                        この短縮URLを削除
                    </x-button>
                </form>
            @endif
        </section>
    </div>

    @include('partials.qr-dialog')
</x-layouts.dashboard>
