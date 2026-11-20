{{-- @var \App\ViewModels\DashboardPageData $page --}}
<x-layouts.dashboard :viewer="$page->viewer">
    <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 pb-12 pt-6 sm:gap-7 sm:px-8 sm:pt-8 lg:px-12">
        <h1 class="sr-only">ダッシュボード</h1>

        <x-flash-messages />

        @if ($page->dataUnavailable())
            <div class="flex items-start gap-2.5 rounded-control border border-danger/40 bg-surface px-4 py-3 text-sm text-danger" role="alert">
                <x-icon name="alert-circle" :size="18" class="mt-0.5" />
                <p>統計情報と発行履歴を読み込めませんでした。時間をおいて再度お試しください。</p>
            </div>
        @endif

        {{-- すぐに短縮URLを発行（requirements.md 9: 開いてすぐ発行できる導線） --}}
        <section aria-labelledby="quick-issue-heading" class="rounded-[20px] bg-surface p-5 shadow-card sm:px-7 sm:py-6">
            <h2 id="quick-issue-heading" class="mb-3.5 font-rounded text-[15px] font-bold">すぐに短縮URLを発行</h2>
            @include('partials.shorten-form', [
                'form' => $page->form,
                'action' => route('dashboard.links.store'),
                'cardPreviewUrl' => route('dashboard.links.card-preview'),
                'idPrefix' => 'dashboard',
                'submitLabel' => '発行する',
                'caption' => $page->stats
                    ? "今月あと {$page->stats->remainingThisMonth()} 件発行できます（{$page->stats->monthlyLimit}件/月まで）"
                    : null,
            ])

            @if ($page->issuedLink)
                @include('partials.issued-link', ['link' => $page->issuedLink, 'timezone' => $page->displayTimezone])
            @endif
        </section>

        @if ($page->stats)
            <section aria-labelledby="stats-heading">
                <h2 id="stats-heading" class="sr-only">利用状況</h2>
                <dl @class([
                    'grid grid-cols-1 gap-4 sm:gap-5',
                    'sm:grid-cols-3' => ! $page->stats->showApi,
                    'sm:grid-cols-2 lg:grid-cols-4' => $page->stats->showApi,
                ])>
                    <div class="rounded-card border border-border bg-surface px-6 py-[22px]">
                        <dt class="text-[13px] text-text-secondary">{{ $page->stats->showApi ? '今月の発行数（ページ）' : '今月の発行数' }}</dt>
                        <dd class="mt-2.5 font-rounded text-stat font-extrabold">
                            {{ number_format($page->stats->monthlyIssued) }}<span class="text-base font-medium text-text-secondary"> / {{ number_format($page->stats->monthlyLimit) }}</span>
                        </dd>
                    </div>
                    @if ($page->stats->showApi)
                        {{-- API で発行したものは月間上限とは別に数える --}}
                        <div class="rounded-card border border-border bg-surface px-6 py-[22px]">
                            <dt class="text-[13px] text-text-secondary">今月の発行数（API）</dt>
                            <dd class="mt-2.5 font-rounded text-stat font-extrabold">
                                {{ number_format($page->stats->apiMonthlyIssued) }}<span class="text-base font-medium text-text-secondary"> 件（上限なし）</span>
                            </dd>
                        </div>
                    @endif
                    <div class="rounded-card border border-border bg-surface px-6 py-[22px]">
                        <dt class="text-[13px] text-text-secondary">総クリック数</dt>
                        <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($page->stats->totalClicks) }}</dd>
                    </div>
                    <div class="rounded-card border border-border bg-surface px-6 py-[22px]">
                        <dt class="text-[13px] text-text-secondary">有効なリンク数</dt>
                        <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($page->stats->activeLinks) }}</dd>
                    </div>
                </dl>
            </section>
        @endif

        @if ($page->links)
            <section aria-labelledby="history-heading" class="overflow-hidden rounded-card border border-border bg-surface">
                @php
                    $transferPending = session(\App\Http\Controllers\Dashboard\LinkTransferController::PENDING_SESSION_KEY);
                    $transferResult = session(\App\Http\Controllers\Dashboard\LinkTransferController::RESULT_SESSION_KEY);
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b border-table-divider px-5 py-4 sm:px-6">
                    <div class="flex items-baseline gap-3">
                        <h2 id="history-heading" class="scroll-mt-6 font-rounded text-[15px] font-bold">発行履歴</h2>
                        <p class="text-xs text-text-secondary">全 {{ number_format($page->links->total()) }} 件</p>
                    </div>
                    {{-- 別のサーバーへ移行するための CSV エクスポート・インポート --}}
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="ghost" size="sm" :href="route('dashboard.links.export')" download>
                            <x-icon name="download" :size="16" />
                            エクスポート
                        </x-button>
                        <x-button variant="ghost" size="sm" data-dialog-open="link-import-dialog">
                            <x-icon name="upload" :size="16" />
                            インポート
                        </x-button>
                    </div>
                </div>

                @if (is_array($transferPending))
                    @include('partials.link-transfer-confirm', ['pending' => $transferPending])
                @endif

                @if ($transferResult)
                    <div class="border-b border-table-divider px-5 pb-4 sm:px-6">
                        @include('partials.import-errors', ['result' => $transferResult])
                    </div>
                @endif

                @if ($page->hasSourceTabs())
                    {{-- ページで発行したものと API で発行したものを分けて表示する --}}
                    <nav aria-label="発行元" class="border-b border-table-divider px-5 py-3 sm:px-6">
                        <ul class="flex flex-wrap gap-1">
                            @foreach ([
                                [\App\Enums\LinkSource::Page, $page->pageLinkCount],
                                [\App\Enums\LinkSource::Api, $page->apiLinkCount],
                            ] as [$tabSource, $tabCount])
                                @php $isCurrent = $page->source === $tabSource; @endphp
                                <li>
                                    <a
                                        href="{{ route('dashboard.home', array_filter(['source' => $tabSource === \App\Enums\LinkSource::Api ? 'api' : null, 'sort' => request('sort'), 'dir' => request('dir')])) }}#history-heading"
                                        @if ($isCurrent) aria-current="page" @endif
                                        @class([
                                            'flex min-h-10 items-center gap-1.5 whitespace-nowrap rounded-control px-3 text-[13px] font-medium transition-colors',
                                            'bg-primary-tint text-primary-dark' => $isCurrent,
                                            'text-text-secondary hover:bg-primary-tint-soft hover:text-primary-dark' => ! $isCurrent,
                                        ])
                                    >
                                        <x-icon :name="$tabSource === \App\Enums\LinkSource::Api ? 'plug' : 'link'" :size="16" />
                                        {{ $tabSource->label() }}
                                        <span class="text-xs text-text-secondary">{{ number_format((int) $tabCount) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                @if ($page->links->isEmpty())
                    <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                        <div class="flex size-11 items-center justify-center rounded-control bg-primary-tint text-primary-dark">
                            <x-icon :name="$page->source === \App\Enums\LinkSource::Api ? 'plug' : 'link'" :size="22" />
                        </div>
                        @if ($page->source === \App\Enums\LinkSource::Api)
                            <p class="font-rounded font-bold">API で発行した短縮URLはまだありません</p>
                            <p class="text-sm text-text-secondary">API で発行すると、ここに表示されます（ページで発行した件数とは別に数えます）。</p>
                        @else
                            <p class="font-rounded font-bold">まだ短縮URLがありません</p>
                            <p class="text-sm text-text-secondary">上のフォームから最初の短縮URLを発行してみましょう。</p>
                        @endif
                    </div>
                @else
                    @include('partials.link-table', [
                        'links' => $page->links,
                        'headingId' => 'history-heading',
                        'caption' => '発行した短縮URLの一覧',
                        'sort' => $page->sort,
                    ])
                @endif
            </section>
        @endif
    </div>

    @include('partials.qr-dialog')
    @include('partials.link-import-dialog')
</x-layouts.dashboard>
