{{-- @var list<\App\ViewModels\NavItemData> $items, 'desktop'|'mobile' $layout --}}
<ul @class([
    'flex items-stretch text-sm font-medium',
    'h-full gap-5 whitespace-nowrap' => $layout === 'desktop',
    'h-12 gap-5 whitespace-nowrap' => $layout === 'mobile',
])>
    @foreach ($items as $item)
        <li class="flex">
            @if ($item->isAvailable())
                <a
                    href="{{ $item->url }}"
                    @if ($item->isCurrent) aria-current="page" @endif
                    @class([
                        'flex items-center gap-1.5 border-b-2 transition-colors',
                        'border-primary text-primary-dark' => $item->isCurrent,
                        'border-transparent text-text-secondary hover:text-primary-dark' => ! $item->isCurrent,
                    ])
                >
                    @if ($layout === 'mobile')
                        <x-icon :name="$item->icon->value" :size="16" />
                    @endif
                    {{ $item->label }}
                </a>
            @else
                <span class="flex cursor-not-allowed items-center gap-1.5 border-b-2 border-transparent text-text-muted" title="準備中">
                    @if ($layout === 'mobile')
                        <x-icon :name="$item->icon->value" :size="16" />
                    @endif
                    {{ $item->label }}
                    <span class="sr-only">（準備中）</span>
                </span>
            @endif
        </li>
    @endforeach
</ul>
