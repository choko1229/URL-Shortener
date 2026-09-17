{{-- 初期セットアップ画面のレイアウト。$step が null なら進行状況を出さない（完了画面） --}}
@props(['step' => null, 'title'])

@php
    $steps = [1 => '動作環境の確認', 2 => 'データベース', 3 => 'サイト設定'];
@endphp

<x-layouts.base :title="'セットアップ: '.$title" robots="noindex, nofollow">
    <main id="main-content" class="px-4 py-10 sm:py-16">
        <div class="mx-auto max-w-[720px]">
            <div class="mb-8 flex items-center justify-between gap-4">
                <x-logo />
                <span class="rounded-full bg-primary-tint px-3 py-1 text-xs font-bold text-primary-dark">初期セットアップ</span>
            </div>

            @if ($step !== null)
                <ol class="mb-6 grid grid-cols-3 gap-2 text-center text-xs sm:text-[13px]" aria-label="セットアップの進行状況">
                    @foreach ($steps as $number => $label)
                        <li
                            @if ($number === $step) aria-current="step" @endif
                            @class([
                                'rounded-control border px-2 py-2.5',
                                'border-primary bg-primary-tint font-bold text-primary-dark' => $number === $step,
                                'border-border bg-white text-primary-dark' => $number < $step,
                                'border-border bg-white text-text-secondary' => $number > $step,
                            ])
                        >
                            <span class="block font-rounded">STEP {{ $number }}</span>
                            <span class="block">{{ $label }}</span>
                            @if ($number < $step)
                                <span class="sr-only">（完了）</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif

            <section aria-labelledby="install-heading" class="rounded-card-lg bg-white p-6 shadow-card-lg sm:p-8">
                <h1 id="install-heading" class="font-rounded text-section font-bold">{{ $title }}</h1>
                <x-flash-messages class="mt-4" />
                <div class="mt-5">
                    {{ $slot }}
                </div>
            </section>
        </div>
    </main>
</x-layouts.base>
