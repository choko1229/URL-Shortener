{{-- ダッシュボード内ページの共通の枠（見出し・説明・フラッシュメッセージ） --}}
@props(['viewer', 'title', 'description' => null])

<x-layouts.dashboard :viewer="$viewer" :title="$title">
    <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 pb-12 pt-6 sm:gap-7 sm:px-8 sm:pt-8 lg:px-12">
        <div>
            <h1 class="font-rounded text-section font-bold">{{ $title }}</h1>
            @if ($description)
                <p class="mt-1 text-sm leading-relaxed text-text-secondary">{{ $description }}</p>
            @endif
        </div>

        <x-flash-messages />

        {{ $slot }}
    </div>
</x-layouts.dashboard>
