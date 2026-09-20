@if (session('notice'))
    <div {{ $attributes->class('flex items-start gap-2.5 rounded-control border border-border-strong bg-surface px-4 py-3 text-sm text-text-primary shadow-card') }} role="status">
        <x-icon name="info" :size="18" class="mt-0.5 text-primary-dark" />
        <p>{{ session('notice') }}</p>
    </div>
@endif

@if (session('error'))
    <div {{ $attributes->class('flex items-start gap-2.5 rounded-control border border-danger/40 bg-surface px-4 py-3 text-sm text-danger shadow-card') }} role="alert">
        <x-icon name="alert-circle" :size="18" class="mt-0.5" />
        <p>{{ session('error') }}</p>
    </div>
@endif
