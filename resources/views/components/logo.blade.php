@props(['size' => 'md'])

{{-- サイト名は設置した人が管理画面で変更できる（$site はビューコンポーザーで渡している） --}}

@php
    $isSmall = $size === 'sm';
@endphp

<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <span @class([
        'inline-flex items-center justify-center bg-primary text-white',
        'size-8 rounded-[9px]' => $isSmall,
        'size-9 rounded-[10px]' => ! $isSmall,
    ])>
        <x-icon name="logo" :size="$isSmall ? 17 : 20" :stroke-width="2.2" />
    </span>
    <span @class([
        'font-rounded font-extrabold text-text-primary',
        'text-lg' => $isSmall,
        'text-[22px]' => ! $isSmall,
    ])>{{ $site->name() }}</span>
</span>
