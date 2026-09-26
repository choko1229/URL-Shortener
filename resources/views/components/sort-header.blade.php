{{--
    並べ替えできる列見出し（th の中身）。押すたびに昇順・降順を切り替える。
    $sort が null の場合は文字だけを表示する。
--}}
@props(['label', 'column', 'sort' => null])

@php
    /** @var \App\Enums\LinkSortColumn $column */
    /** @var \App\Support\LinkSort|null $sort */
    $active = $sort?->column === $column;
@endphp

@if ($sort === null)
    {{ $label }}
@else
    <a
        href="{{ request()->fullUrlWithQuery($sort->queryFor($column)) }}"
        @class([
            '-mx-1.5 inline-flex items-center gap-1 rounded-md px-1.5 py-1 hover:bg-primary-tint hover:text-primary-dark',
            'text-primary-dark' => $active,
        ])
    >
        {{ $label }}
        <x-icon :name="$active ? ($sort->descending ? 'arrow-down' : 'arrow-up') : 'arrow-up-down'" :size="13" @class(['opacity-40' => ! $active]) />
        <span class="sr-only">{{ $active ? '（'.$sort->directionLabel().'で表示中。押すと逆順）' : '（押すと並べ替え）' }}</span>
    </a>
@endif
