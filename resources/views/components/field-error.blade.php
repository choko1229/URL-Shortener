@props(['name', 'id'])

@error($name)
    <p id="{{ $id }}" {{ $attributes->class('mt-2 flex items-center gap-1.5 text-[13px] text-danger') }}>
        <x-icon name="alert-circle" :size="14" />
        <span>{{ $message }}</span>
    </p>
@enderror
