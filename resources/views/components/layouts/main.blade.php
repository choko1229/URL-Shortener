{{-- chok.ooo（トップページ）用レイアウト --}}
@props(['viewer', 'title' => null])

<x-layouts.base :title="$title">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-border-strong bg-white">
            <div class="mx-auto flex h-[72px] max-w-[1280px] items-center justify-between gap-4 px-4 sm:h-[88px] sm:px-8 lg:px-16">
                <a href="{{ route('main.home') }}" class="rounded-control" aria-label="chok.ooo トップページ">
                    <x-logo />
                </a>

                <nav aria-label="メインメニュー" class="flex items-center gap-2 sm:gap-4">
                    <a href="#features" class="hidden rounded-control px-2 py-2 text-sm font-medium text-primary-dark hover:text-primary-darker sm:inline-block">
                        使い方
                    </a>
                    @if ($viewer->isAuthenticated)
                        <x-button variant="secondary" size="sm" :href="route('dashboard.home')">
                            <x-icon name="layout-grid" :size="16" />
                            ダッシュボード
                        </x-button>
                    @else
                        <x-button variant="secondary" size="sm" :href="route('auth.login')">
                            <x-icon name="log-in" :size="16" />
                            <span>Discord<span class="max-[399px]:sr-only">でログイン</span></span>
                        </x-button>
                    @endif
                </nav>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="flex-1 focus:outline-none">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-border-strong bg-white lg:mt-24">
            <div class="mx-auto flex max-w-[1280px] flex-col gap-5 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-16">
                <x-logo size="sm" />
                <nav aria-label="フッターメニュー">
                    <ul class="flex flex-wrap gap-x-6 gap-y-2 text-[13px]">
                        <li><a href="#features" class="text-text-secondary hover:text-primary-dark">使い方</a></li>
                        {{-- 以下のページは未作成のためリンクにしない --}}
                        @foreach (['利用規約', 'プライバシーポリシー', 'お問い合わせ'] as $label)
                            <li class="text-text-muted">{{ $label }}<span class="sr-only">（準備中）</span></li>
                        @endforeach
                    </ul>
                </nav>
            </div>
        </footer>
    </div>
</x-layouts.base>
