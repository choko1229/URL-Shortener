{{-- @var \App\ViewModels\HomePageData $page --}}
@php
    $features = [
        ['icon' => 'shield', 'title' => '毎回ウイルスチェック', 'body' => 'リダイレクト時に安全性を自動確認します'],
        ['icon' => 'clock', 'title' => '有効期限も設定OK', 'body' => '必要な期間だけ公開できます'],
        ['icon' => 'qr-code', 'title' => 'QRコードも自動生成', 'body' => 'スマホでの共有もかんたんに'],
        ['icon' => 'bar-chart', 'title' => 'アクセス状況を確認', 'body' => 'ログインすればクリック数や流入元も見られます'],
    ];
@endphp

<x-layouts.main :viewer="$page->viewer">
    <section aria-labelledby="hero-heading" class="px-4 pb-10 pt-12 text-center sm:px-8 sm:pb-14 sm:pt-[72px]">
        <p class="mb-5 inline-flex rounded-full bg-primary-tint px-[18px] py-2 text-[13px] font-bold text-primary-dark">
            シンプルなURL短縮サービス
        </p>
        <h1 id="hero-heading" class="font-rounded text-[34px] font-extrabold leading-[1.35] sm:text-[44px] lg:text-hero">
            長いURLを、<br class="sm:hidden">ぎゅっと短く。
        </h1>
        <p class="mx-auto mt-[18px] max-w-[520px] text-[15px] leading-[1.8] text-text-secondary sm:text-base">
            ログイン不要でその場で発行。パスワード保護や有効期限、QRコードもまとめて使えます。
        </p>
    </section>

    <div class="px-4 sm:px-8">
        <div class="mx-auto max-w-[720px]">
            <x-flash-messages class="mb-4" />

            <section aria-labelledby="shorten-heading" class="rounded-card-lg bg-surface p-5 shadow-card-lg sm:p-8">
                <h2 id="shorten-heading" class="sr-only">短縮URLを発行する</h2>
                @include('partials.shorten-form', [
                    'form' => $page->form,
                    'action' => route('main.short-urls.store'),
                    'idPrefix' => 'home',
                    'submitLabel' => '短縮する',
                ])
            </section>

            @if ($page->issuedLink)
                @include('partials.issued-link', ['link' => $page->issuedLink, 'timezone' => $page->displayTimezone])
            @endif
        </div>
    </div>

    <section id="features" aria-labelledby="features-heading" class="mx-auto max-w-[1280px] scroll-mt-6 px-4 pt-16 sm:px-8 lg:px-16 lg:pt-24">
        <h2 id="features-heading" class="sr-only">{{ $site->name() }} でできること</h2>
        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-4">
            @foreach ($features as $feature)
                <li class="rounded-card border border-border bg-surface p-6 sm:p-[26px]">
                    <div class="mb-3.5 flex size-11 items-center justify-center rounded-control bg-primary-tint text-primary-dark">
                        <x-icon :name="$feature['icon']" :size="22" />
                    </div>
                    <h3 class="mb-1.5 font-rounded text-base font-bold">{{ $feature['title'] }}</h3>
                    <p class="text-[13px] leading-[1.7] text-text-secondary">{{ $feature['body'] }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($page->form->neverRequiresLogin() && $page->form->maxExpiryDays !== null)
        @include('partials.login-dialog', ['maxExpiryDays' => $page->form->maxExpiryDays])
    @endif
</x-layouts.main>
