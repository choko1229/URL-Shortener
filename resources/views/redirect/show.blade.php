{{--
    転送先の表示と悪意URLチェック（requirements.md 3: 手順 5〜6、2-7）
    @var string $destination
    @var string $ticket
--}}
<x-layouts.redirect title="リンク先を確認しています">
    <p class="text-[13px] font-medium text-text-secondary">移動先</p>
    <p class="mt-1.5 break-all rounded-control border border-border bg-primary-tint-soft px-4 py-3 text-sm font-medium">{{ $destination }}</p>

    <div
        class="mt-6"
        data-safety-check
        data-check-url="{{ route('redirect.check') }}"
        data-ticket="{{ $ticket }}"
        data-destination="{{ $destination }}"
        aria-live="polite"
    >
        <div data-state="checking" class="space-y-3">
            <p class="flex items-center gap-2.5 text-sm text-text-secondary">
                <span class="progress-spinner" aria-hidden="true"></span>
                リンク先の安全性を確認しています…
            </p>
            <div class="progress-track" aria-hidden="true"><div class="progress-bar"></div></div>
        </div>

        <div data-state="safe" class="space-y-4" hidden>
            <p class="flex items-center gap-2 text-sm font-medium text-primary-dark">
                <x-icon name="check-circle" :size="18" />
                安全を確認しました。まもなく移動します。
            </p>
            <x-button size="sm" href="#" data-destination-link rel="noopener">すぐに移動する</x-button>
        </div>

        <div data-state="unsafe" class="rounded-control border border-danger/40 bg-surface px-4 py-4 text-sm" role="alert" hidden>
            <p class="flex items-center gap-2 font-bold text-danger">
                <x-icon name="ban" :size="18" />
                危険なサイトの可能性があるため、移動を中止しました。
            </p>
            <p class="mt-2 text-text-secondary">Google Safe Browsing により次の脅威が報告されています。</p>
            <ul class="mt-2 list-disc pl-5 text-text-secondary" data-threats></ul>
        </div>

        <div data-state="unknown" class="space-y-4" hidden>
            <div class="flex items-start gap-2.5 rounded-control border border-warning/50 bg-surface px-4 py-3 text-sm" role="alert">
                <x-icon name="alert-circle" :size="18" class="mt-0.5 text-warning" />
                <div>
                    <p class="font-bold">安全性を確認できませんでした。</p>
                    <p class="mt-1 text-text-secondary" data-message></p>
                    <p class="mt-1 text-text-secondary">移動先に心当たりがある場合のみ開いてください。</p>
                </div>
            </div>
            <x-button variant="secondary" size="sm" href="#" data-destination-link rel="noopener noreferrer">それでも開く</x-button>
        </div>

        <div data-state="invalid" class="space-y-4" hidden>
            <p class="text-sm text-text-secondary" data-message></p>
            <x-button variant="secondary" size="sm" :href="route('main.home')">トップページへ</x-button>
        </div>
    </div>

    <noscript>
        <p class="mt-6 text-sm leading-relaxed text-text-secondary">
            安全性の確認には JavaScript が必要です。移動先に心当たりがある場合のみ、次のリンクから開いてください。
        </p>
        <p class="mt-3"><a href="{{ $destination }}" rel="noopener noreferrer" class="break-all text-sm text-primary-dark underline">{{ $destination }}</a></p>
    </noscript>
</x-layouts.redirect>
