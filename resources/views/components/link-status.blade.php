{{-- 有効期限の状態表示。色だけに頼らないようアイコンも併記する（design.md 4. テーブル） --}}
@props(['status', 'label'])

@php
    /** @var \App\Enums\LinkStatus $status */
    [$colorClass, $icon] = match ($status) {
        \App\Enums\LinkStatus::Active => ['text-text-secondary', null],
        \App\Enums\LinkStatus::ExpiringSoon => ['text-warning', 'clock'],
        \App\Enums\LinkStatus::Expired => ['text-danger', 'ban'],
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap text-[13px] font-medium', $colorClass]) }}>
    @if ($icon)
        <x-icon :name="$icon" :size="14" />
    @endif
    {{ $label }}
</span>
