{{-- ダッシュボード用レイアウト --}}
@props(['viewer', 'title' => 'ダッシュボード'])

@php
    $navItems = \App\Support\DashboardNavigation::items($viewer, request());
@endphp

<x-layouts.base :title="$title" robots="noindex, nofollow">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-border-strong bg-white">
            <div class="mx-auto flex h-[72px] max-w-[1280px] items-center justify-between gap-4 px-4 sm:px-8 lg:px-12">
                <div class="flex min-w-0 items-center gap-6 lg:gap-10">
                    <a href="{{ route('dashboard.home') }}" class="rounded-control" aria-label="{{ $site->name() }} ダッシュボード">
                        <x-logo size="sm" />
                    </a>

                    <nav aria-label="ダッシュボードメニュー" class="hidden h-[72px] xl:block">
                        @include('partials.dashboard-nav-items', ['items' => $navItems, 'layout' => 'desktop'])
                    </nav>
                </div>

                <div class="relative">
                    <button
                        type="button"
                        class="flex min-h-11 items-center gap-2.5 rounded-control px-2 hover:bg-primary-tint"
                        aria-expanded="false"
                        aria-controls="user-menu"
                        data-menu-button
                    >
                        @if ($viewer->avatarUrl)
                            <img src="{{ $viewer->avatarUrl }}" alt="" width="34" height="34" class="size-[34px] rounded-full object-cover" referrerpolicy="no-referrer" loading="lazy">
                        @else
                            <span class="flex size-[34px] items-center justify-center rounded-full bg-accent-warm font-rounded text-[13px] font-bold text-text-primary" aria-hidden="true">{{ $viewer->initial() }}</span>
                        @endif
                        <span class="sr-only">アカウントメニュー: </span>
                        <span class="max-w-[10rem] truncate text-sm font-medium max-sm:sr-only">{{ $viewer->displayName }}</span>
                        @if ($viewer->isAdmin)
                            <span class="rounded-full bg-primary-tint px-2 py-0.5 text-[11px] font-bold text-primary-dark max-sm:hidden">管理者</span>
                        @endif
                        <x-icon name="chevron-down" :size="16" class="text-text-secondary" />
                    </button>

                    <div id="user-menu" class="absolute right-0 z-20 mt-2 w-60 rounded-card border border-border bg-white p-2 shadow-card-lg" hidden>
                        <p class="border-b border-table-divider px-3 pb-3 pt-2 text-xs text-text-secondary">
                            ログイン中: <span class="font-medium text-text-primary">{{ $viewer->displayName }}</span>
                        </p>
                        <ul class="pt-2 text-sm">
                            <li>
                                <a href="{{ route('main.home') }}" class="flex min-h-11 items-center gap-2.5 rounded-control px-3 text-text-primary hover:bg-primary-tint">
                                    <x-icon name="link" :size="16" class="text-text-secondary" />
                                    トップページ
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.settings') }}" class="flex min-h-11 items-center gap-2.5 rounded-control px-3 text-text-primary hover:bg-primary-tint">
                                    <x-icon name="sliders" :size="16" class="text-text-secondary" />
                                    設定
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('dashboard.logout') }}">
                                    @csrf
                                    <button type="submit" class="flex min-h-11 w-full items-center gap-2.5 rounded-control px-3 text-left text-text-primary hover:bg-primary-tint">
                                        <x-icon name="log-out" :size="16" class="text-text-secondary" />
                                        ログアウト
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- スマホ用: 横スクロールのタブ --}}
            <nav aria-label="ダッシュボードメニュー（モバイル）" class="overflow-x-auto border-t border-table-divider px-4 xl:hidden">
                @include('partials.dashboard-nav-items', ['items' => $navItems, 'layout' => 'mobile'])
            </nav>
        </header>

        <main id="main-content" tabindex="-1" class="flex-1 focus:outline-none">
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
