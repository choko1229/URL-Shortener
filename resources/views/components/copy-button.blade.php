@props([
    'text',
    'label',
    'variant' => 'ghost',
    'iconSize' => 18,
])

<x-button
    :variant="$variant"
    size="icon"
    :aria-label="$label"
    :title="$label"
    :data-copy-text="$text"
    {{ $attributes }}
>
    <x-icon name="copy" :size="$iconSize" />
</x-button>
