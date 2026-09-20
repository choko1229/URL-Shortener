{{-- 初期セットアップ画面のレイアウト --}}
@props(['title'])

<x-layouts.base :title="'セットアップ: '.$title" robots="noindex, nofollow">
    <main id="main-content" class="px-4 py-10 sm:py-16">
        <div class="mx-auto max-w-[720px]">
            <div class="mb-8 flex items-center justify-between gap-4">
                <x-logo />
                <span class="rounded-full bg-primary-tint px-3 py-1 text-xs font-bold text-primary-dark">初期セットアップ</span>
            </div>

            <section aria-labelledby="install-heading" class="rounded-card-lg bg-surface p-6 shadow-card-lg sm:p-8">
                <h1 id="install-heading" class="font-rounded text-section font-bold">{{ $title }}</h1>
                <x-flash-messages class="mt-4" />
                <div class="mt-5">
                    {{ $slot }}
                </div>
            </section>
        </div>
    </main>
</x-layouts.base>
