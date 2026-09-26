@props([
    'text',
    'label',
    'variant' => 'ghost',
    'iconSize' => 18,
])

{{-- コピーできたら、チェックに変わって弾み、「コピーしました」の吹き出しが出る（app.css: .copy-button） --}}
<x-button
    :variant="$variant"
    size="icon"
    :aria-label="$label"
    :title="$label"
    :data-copy-text="$text"
    {{ $attributes->class('copy-button group') }}
>
    <x-icon name="copy" :size="$iconSize" class="group-data-[copied=true]:hidden" />
    <x-icon name="check" :size="$iconSize" class="hidden group-data-[copied=true]:block" />
</x-button>
