{{-- エラーページ共通。DB・セッションに依存しないこと --}}
@props(['code', 'heading', 'message'])

<x-layouts.base :title="$heading" robots="noindex">
    <main id="main-content" class="flex min-h-screen items-center justify-center px-4 py-16">
        <div class="w-full max-w-md rounded-card-lg bg-surface p-8 text-center shadow-card-lg">
            <x-logo size="sm" class="mb-6" />
            <p class="font-rounded text-stat font-extrabold text-primary-dark">{{ $code }}</p>
            <h1 class="mt-2 font-rounded text-section font-bold">{{ $heading }}</h1>
            <p class="mt-3 text-sm leading-relaxed text-text-secondary">{{ $message }}</p>
            <x-button size="sm" :href="route('main.home')" class="mt-6">トップページへ戻る</x-button>
        </div>
    </main>
</x-layouts.base>
