{{--
    期限切れ専用ページ（requirements.md 2-3: 404 ではなく専用ページ）
    @var \Carbon\CarbonImmutable|null $expiresAt
--}}
<x-layouts.redirect title="このリンクは有効期限が切れています">
    <div class="flex items-start gap-3">
        <div class="flex size-11 shrink-0 items-center justify-center rounded-control bg-primary-tint text-primary-dark">
            <x-icon name="clock" :size="22" />
        </div>
        <div class="text-sm leading-relaxed text-text-secondary">
            <p>リンクの公開期間が終了したため、移動先を表示できません。</p>
            @if ($expiresAt)
                <p class="mt-1">有効期限: {{ $expiresAt->format('Y/m/d H:i') }}（日本時間）</p>
            @endif
        </div>
    </div>
    <div class="mt-6">
        <x-button variant="secondary" size="sm" :href="route('main.home')">{{ $site->name() }} で短縮URLを作る</x-button>
    </div>
</x-layouts.redirect>
