{{-- リダイレクトの各画面（中間ページ・パスワード入力・安全性確認・期限切れ）の共通レイアウト --}}
@props(['title'])

<x-layouts.base :title="$title" robots="noindex, nofollow">
    <main id="main-content" class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-[560px]">
            <a href="{{ route('main.home') }}" class="mb-6 inline-block rounded-control" aria-label="{{ $site->name() }} トップページ">
                <x-logo size="sm" />
            </a>
            <section aria-labelledby="redirect-heading" class="rounded-card-lg bg-white p-6 shadow-card-lg sm:p-8">
                <h1 id="redirect-heading" class="font-rounded text-section font-bold">{{ $title }}</h1>
                <div class="mt-4">
                    {{ $slot }}
                </div>
            </section>
        </div>
    </main>
</x-layouts.base>
