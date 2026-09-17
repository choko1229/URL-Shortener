{{-- @var \App\ViewModels\DashboardPageData $page --}}
<x-layouts.dashboard :viewer="$page->viewer">
    <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 pb-12 pt-6 sm:gap-7 sm:px-8 sm:pt-8 lg:px-12">
        <h1 class="sr-only">ダッシュボード</h1>

        <x-flash-messages />

        @if ($page->dataUnavailable())
            <div class="flex items-start gap-2.5 rounded-control border border-danger/40 bg-white px-4 py-3 text-sm text-danger" role="alert">
                <x-icon name="alert-circle" :size="18" class="mt-0.5" />
                <p>統計情報と発行履歴を読み込めませんでした。時間をおいて再度お試しください。</p>
            </div>
        @endif

        {{-- すぐに短縮URLを発行（requirements.md 9: 開いてすぐ発行できる導線） --}}
        <section aria-labelledby="quick-issue-heading" class="rounded-[20px] bg-white p-5 shadow-card sm:px-7 sm:py-6">
            <h2 id="quick-issue-heading" class="mb-3.5 font-rounded text-[15px] font-bold">すぐに短縮URLを発行</h2>
            @include('partials.shorten-form', [
                'form' => $page->form,
                'action' => route('dashboard.links.store'),
                'idPrefix' => 'dashboard',
                'submitLabel' => '発行する',
                'caption' => $page->stats
                    ? "今月あと {$page->stats->remainingThisMonth()} 件発行できます（{$page->stats->monthlyLimit}件/月まで）"
                    : null,
            ])
        </section>

        @if ($page->stats)
            <section aria-labelledby="stats-heading">
                <h2 id="stats-heading" class="sr-only">利用状況</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5">
                    <div class="rounded-card border border-border bg-white px-6 py-[22px]">
                        <dt class="text-[13px] text-text-secondary">今月の発行数</dt>
                        <dd class="mt-2.5 font-rounded text-stat font-extrabold">
                            {{ number_format($page->stats->monthlyIssued) }}<span class="text-base font-medium text-text-secondary"> / {{ number_format($page->stats->monthlyLimit) }}</span>
                        </dd>
                    </div>
                    <div class="rounded-card border border-border bg-white px-6 py-[22px]">
                        <dt class="text-[13px] text-text-secondary">総クリック数</dt>
                        <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($page->stats->totalClicks) }}</dd>
                    </div>
                    <div class="rounded-card border border-border bg-white px-6 py-[22px]">
                        <dt class="text-[13px] text-text-secondary">有効なリンク数</dt>
                        <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($page->stats->activeLinks) }}</dd>
                    </div>
                </dl>
            </section>
        @endif

        @if ($page->links)
            <section aria-labelledby="history-heading" class="overflow-hidden rounded-card border border-border bg-white">
                <div class="flex items-center justify-between gap-4 border-b border-table-divider px-5 py-5 sm:px-6">
                    <h2 id="history-heading" class="font-rounded text-[15px] font-bold">発行履歴</h2>
                    <p class="text-xs text-text-secondary">全 {{ number_format($page->links->total()) }} 件</p>
                </div>

                @if ($page->links->isEmpty())
                    <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                        <div class="flex size-11 items-center justify-center rounded-control bg-primary-tint text-primary-dark">
                            <x-icon name="link" :size="22" />
                        </div>
                        <p class="font-rounded font-bold">まだ短縮URLがありません</p>
                        <p class="text-sm text-text-secondary">上のフォームから最初の短縮URLを発行してみましょう。</p>
                    </div>
                @else
                    {{-- スマホでは横スクロール（design.md 5）。キーボードでもスクロールできるよう tabindex を付与 --}}
                    <div class="overflow-x-auto" role="region" aria-labelledby="history-heading" tabindex="0">
                        <table class="w-full min-w-[760px] border-collapse text-left">
                            <caption class="sr-only">発行した短縮URLの一覧（新しい順）</caption>
                            <thead class="bg-table-header">
                                <tr>
                                    <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">短縮URL</th>
                                    <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">元URL</th>
                                    <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">クリック数</th>
                                    <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">有効期限</th>
                                    <th scope="col" class="whitespace-nowrap px-6 py-3 text-right text-xs font-medium text-text-secondary">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($page->links as $link)
                                    @php
                                        /** @var \App\ViewModels\LinkRowData $link */
                                        $isExpired = $link->status === \App\Enums\LinkStatus::Expired;
                                    @endphp
                                    <tr class="border-t border-table-divider">
                                        <td class="whitespace-nowrap px-6 py-3.5 text-[13px]">
                                            <span class="inline-flex items-center gap-1.5">
                                                <a
                                                    href="{{ $link->shortUrl }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    @class([
                                                        'font-semibold',
                                                        'text-primary-dark hover:text-primary-darker' => ! $isExpired,
                                                        'text-text-muted hover:text-text-secondary' => $isExpired,
                                                    ])
                                                >{{ $link->displayUrl }}<span class="sr-only">（新しいタブで開く）</span></a>
                                                @if ($link->isPasswordProtected)
                                                    <x-icon name="lock" :size="13" class="text-text-muted" />
                                                    <span class="sr-only">パスワード保護あり</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td @class(['max-w-[320px] px-6 py-3.5 text-[13px]', 'text-text-secondary' => ! $isExpired, 'text-text-muted' => $isExpired])>
                                            {{-- 元URLは javascript: 等の混入に備えてリンクにせずテキストで表示する --}}
                                            <span class="block truncate" title="{{ $link->originalUrl }}">{{ $link->originalUrl }}</span>
                                        </td>
                                        <td @class(['px-6 py-3.5 text-[13px] tabular-nums', 'text-text-muted' => $isExpired])>
                                            {{ number_format($link->clickCount) }}
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <x-link-status :status="$link->status" :label="$link->expiryLabel" />
                                        </td>
                                        <td class="px-6 py-2 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <x-copy-button :text="$link->shortUrl" :label="$link->displayUrl.' をコピー'" :icon-size="16" />
                                                <x-button variant="ghost" size="icon" :aria-label="$link->displayUrl.' のQRコード（準備中）'" title="QRコード（準備中）" disabled>
                                                    <x-icon name="qr-code" :size="16" />
                                                </x-button>
                                                <form
                                                    method="POST"
                                                    action="{{ route('dashboard.links.destroy', ['shortUrl' => $link->id]) }}"
                                                    data-confirm="{{ $link->displayUrl }} を削除しますか？削除したコードは再利用できません。"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button type="submit" variant="danger-ghost" size="icon" :aria-label="$link->displayUrl.' を削除'" :title="$link->displayUrl.' を削除'">
                                                        <x-icon name="trash" :size="16" />
                                                    </x-button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $page->links->links('partials.pagination') }}
                @endif
            </section>
        @endif
    </div>
</x-layouts.dashboard>
