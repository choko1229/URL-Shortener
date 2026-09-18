{{-- チケットが無効・期限切れ、またはリンクが削除された場合 --}}
<x-layouts.redirect title="リンクを開き直してください">
    <p class="text-sm leading-relaxed text-text-secondary">
        移動の手続きの有効時間が過ぎたか、リンクが見つかりませんでした。共有された短縮URLをもう一度開いてください。
    </p>
    <div class="mt-6">
        <x-button variant="secondary" size="sm" :href="route('main.home')">トップページへ</x-button>
    </div>
</x-layouts.redirect>
