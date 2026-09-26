{{--
    短縮URLの一覧テーブル（ダッシュボード・管理者の全URL一覧で共用）
    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\ViewModels\LinkRowData> $links
    @var string $headingId   テーブルを説明する見出しの id
    @var string $caption
    @var bool $showOwner     発行者の列を表示するか
    @var \App\Support\LinkSort|null $sort 並び順（渡すと列見出しから並べ替えられる）
--}}
@php
    $showOwner ??= false;
    $sort ??= null;
    $columns = \App\Enums\LinkSortColumn::class;
@endphp

{{-- スマホでは横スクロール（design.md 5）。キーボードでもスクロールできるよう tabindex を付与 --}}
<div class="overflow-x-auto" role="region" aria-labelledby="{{ $headingId }}" tabindex="0">
    <table class="w-full min-w-[900px] border-collapse text-left">
        <caption class="sr-only">{{ $caption }}{{ $sort ? '（'.$sort->directionLabel().'）' : '' }}</caption>
        <thead class="bg-table-header">
            <tr>
                <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary" @if ($sort) aria-sort="{{ $sort->ariaSort($columns::Slug) }}" @endif>
                    <x-sort-header label="短縮URL" :column="$columns::Slug" :sort="$sort" />
                </th>
                <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">元URL</th>
                @if ($showOwner)
                    <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">発行者</th>
                @endif
                <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary" @if ($sort) aria-sort="{{ $sort->ariaSort($columns::Created) }}" @endif>
                    <x-sort-header label="発行日" :column="$columns::Created" :sort="$sort" />
                </th>
                <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary" @if ($sort) aria-sort="{{ $sort->ariaSort($columns::Clicks) }}" @endif>
                    <x-sort-header label="クリック数" :column="$columns::Clicks" :sort="$sort" />
                </th>
                <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary" @if ($sort) aria-sort="{{ $sort->ariaSort($columns::Expires) }}" @endif>
                    <x-sort-header label="有効期限" :column="$columns::Expires" :sort="$sort" />
                </th>
                <th scope="col" class="whitespace-nowrap px-6 py-3 text-right text-xs font-medium text-text-secondary">操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($links as $link)
                @php
                    /** @var \App\ViewModels\LinkRowData $link */
                    $muted = ! $link->isUsable();
                @endphp
                <tr class="border-t border-table-divider">
                    <td class="whitespace-nowrap px-6 py-3.5 text-[13px]">
                        <span class="inline-flex items-center gap-1.5">
                            @if ($link->isDeleted())
                                <span class="font-semibold text-text-muted">{{ $link->displayUrl }}</span>
                            @else
                                <a
                                    href="{{ $link->shortUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    @class([
                                        'font-semibold',
                                        'text-primary-dark hover:text-primary-darker' => ! $muted,
                                        'text-text-muted hover:text-text-secondary' => $muted,
                                    ])
                                >{{ $link->displayUrl }}<span class="sr-only">（新しいタブで開く）</span></a>
                            @endif
                            @if ($link->isPasswordProtected)
                                <x-icon name="lock" :size="13" class="text-text-muted" />
                                <span class="sr-only">パスワード保護あり</span>
                            @endif
                        </span>
                    </td>
                    <td @class(['max-w-[300px] px-6 py-3.5 text-[13px]', 'text-text-secondary' => ! $muted, 'text-text-muted' => $muted])>
                        {{-- 元URLは javascript: 等の混入に備えてリンクにせずテキストで表示する --}}
                        <span class="block truncate" title="{{ $link->originalUrl }}">{{ $link->originalUrl }}</span>
                    </td>
                    @if ($showOwner)
                        <td class="whitespace-nowrap px-6 py-3.5 text-[13px] text-text-secondary">{{ $link->ownerLabel }}</td>
                    @endif
                    <td @class(['whitespace-nowrap px-6 py-3.5 text-[13px] tabular-nums', 'text-text-secondary' => ! $muted, 'text-text-muted' => $muted])>
                        {{ $link->createdLabel }}
                    </td>
                    <td @class(['px-6 py-3.5 text-[13px] tabular-nums', 'text-text-muted' => $muted])>
                        {{ number_format($link->clickCount) }}
                    </td>
                    <td class="px-6 py-3.5">
                        <x-link-status :status="$link->status" :label="$link->expiryLabel" />
                        @if ($link->expiryDetail)
                            <span class="mt-0.5 block whitespace-nowrap text-[11px] tabular-nums text-text-muted">{{ $link->expiryDetail }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-2 text-right">
                        <div class="inline-flex items-center gap-1">
                            @unless ($link->isDeleted())
                                <x-copy-button :text="$link->shortUrl" :label="$link->displayUrl.' をコピー'" :icon-size="16" />
                                <x-button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="$link->displayUrl.' のQRコードを表示'"
                                    :title="$link->displayUrl.' のQRコードを表示'"
                                    aria-haspopup="dialog"
                                    data-qr-open
                                    :data-qr-src="route('main.short-link.qr', ['code' => $link->slug, 'format' => 'svg'])"
                                    :data-qr-png="route('main.short-link.qr', ['code' => $link->slug, 'format' => 'png'])"
                                    :data-qr-label="$link->displayUrl"
                                >
                                    <x-icon name="qr-code" :size="16" />
                                </x-button>
                            @endunless
                            {{-- 管理者の一覧（全URL）: 元URL・有効期限・発行者を変更する --}}
                            @if (($showOwner ?? false) && ! $link->isDeleted())
                                <x-button
                                    variant="ghost"
                                    size="icon"
                                    :href="route('dashboard.links.show', ['shortUrl' => $link->id]).'#admin-edit'"
                                    :aria-label="$link->displayUrl.' を編集（元URL・有効期限・発行者）'"
                                    :title="$link->displayUrl.' を編集（元URL・有効期限・発行者）'"
                                >
                                    <x-icon name="pencil" :size="16" />
                                </x-button>
                            @endif
                            <x-button
                                variant="ghost"
                                size="icon"
                                :href="route('dashboard.links.show', ['shortUrl' => $link->id])"
                                :aria-label="$link->displayUrl.' の統計・詳細'"
                                :title="$link->displayUrl.' の統計・詳細'"
                            >
                                <x-icon name="bar-chart" :size="16" />
                            </x-button>
                            @unless ($link->isDeleted())
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
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{ $links->links('partials.pagination') }}
