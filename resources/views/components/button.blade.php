{{--
    ボタン。href を渡すと <a>、渡さなければ <button> を出力する。
    アイコンのみ（size="icon"）の場合は必ず aria-label を付けること。
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-dark',
        'secondary' => 'border-[1.5px] border-primary bg-surface text-text-primary hover:bg-primary-tint',
        'dark' => 'bg-dark-panel-button text-white hover:bg-primary-darker data-[copied=true]:bg-primary-dark',
        'ghost' => 'text-text-secondary hover:bg-primary-tint hover:text-primary-dark data-[copied=true]:bg-primary-tint data-[copied=true]:text-primary-dark',
        'danger-ghost' => 'text-danger hover:bg-danger/10',
    ];
    $sizes = [
        'sm' => 'min-h-11 rounded-control px-4 text-sm',
        'md' => 'min-h-[52px] rounded-button px-7 text-[15px]',
        'icon' => 'size-11 rounded-control',
    ];

    if (! array_key_exists($variant, $variants) || ! array_key_exists($size, $sizes)) {
        \Illuminate\Support\Facades\Log::warning('x-button に未定義の variant / size が指定されました。', compact('variant', 'size'));
        $variant = array_key_exists($variant, $variants) ? $variant : 'primary';
        $size = array_key_exists($size, $sizes) ? $size : 'md';
    }

    $classes = implode(' ', [
        'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap font-rounded font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant],
        $sizes[$size],
    ]);
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
