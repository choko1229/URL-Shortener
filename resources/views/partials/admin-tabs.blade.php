{{-- 管理画面のタブ @var list<\App\ViewModels\NavItemData> $items --}}
<nav aria-label="管理メニュー" data-scroll-tabs class="-mx-4 overflow-x-auto px-4 sm:-mx-1 sm:px-1">
    <ul class="flex w-max gap-1 rounded-card border border-border bg-white p-1.5">
        @foreach ($items as $item)
            <li>
                <a href="{{ $item->url }}"@if ($item->isCurrent) aria-current="page"@endif
                    @class([
                        'flex min-h-10 items-center gap-1.5 whitespace-nowrap rounded-control px-3 text-[13px] font-medium transition-colors',
                        'bg-primary-tint text-primary-dark' => $item->isCurrent,
                        'text-text-secondary hover:bg-primary-tint-soft hover:text-primary-dark' => ! $item->isCurrent,
                    ])
                >
                    <x-icon :name="$item->icon->value" :size="16" />
                    {{ $item->label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
