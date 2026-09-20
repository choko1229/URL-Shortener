@props(['size' => 'md'])

{{-- サイト名とアイコンは設置した人が管理画面で変更できる（$site / $siteIcon はビューコンポーザーで渡している） --}}

@php
    $isSmall = $size === 'sm';
@endphp

<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    @if ($siteIcon->isUploaded())
        <img
            src="{{ route('site-icon') }}?v={{ $siteIcon->version() }}"
            alt=""
            width="{{ $isSmall ? 32 : 36 }}"
            height="{{ $isSmall ? 32 : 36 }}"
            @class([
                'shrink-0 object-contain',
                'size-8 rounded-[9px]' => $isSmall,
                'size-9 rounded-[10px]' => ! $isSmall,
            ])
        >
    @else
        <span @class([
            'inline-flex items-center justify-center bg-primary text-white',
            'size-8 rounded-[9px]' => $isSmall,
            'size-9 rounded-[10px]' => ! $isSmall,
        ])>
            <x-icon :name="$siteIcon->builtIn()->value" :size="$isSmall ? 17 : 20" :stroke-width="2.2" />
        </span>
    @endif
    <span @class([
        'font-rounded font-extrabold text-text-primary',
        'text-lg' => $isSmall,
        'text-[22px]' => ! $isSmall,
    ])>{{ $site->name() }}</span>
</span>
