{{-- ライト／ダークの切り替え。サイト設定で「ライトのみ」のときは出さない（$theme はビューコンポーザーで渡している） --}}
@if ($theme->scheme()->isSwitchable())
    <button
        type="button"
        data-theme-toggle
        class="flex size-10 shrink-0 items-center justify-center rounded-control text-text-secondary transition-colors hover:bg-primary-tint hover:text-primary-dark"
        aria-label="ダーク表示に切り替える"
        title="表示を切り替える"
    >
        <span data-theme-icon="to-dark"><x-icon name="moon" :size="18" /></span>
        <span data-theme-icon="to-light" hidden><x-icon name="sun" :size="18" /></span>
    </button>
@endif
