{{-- 効果音（発行・コピー）のオン／オフ。選択はこのブラウザーにだけ記憶する（app.js: initSoundToggle） --}}
<button
    type="button"
    data-sound-toggle
    class="flex size-10 shrink-0 items-center justify-center rounded-control text-text-secondary transition-colors hover:bg-primary-tint hover:text-primary-dark"
    aria-pressed="false"
    aria-label="効果音をオフにする"
    title="効果音をオフにする"
>
    <span data-sound-icon="on"><x-icon name="volume" :size="18" /></span>
    <span data-sound-icon="off" hidden><x-icon name="volume-off" :size="18" /></span>
</button>
