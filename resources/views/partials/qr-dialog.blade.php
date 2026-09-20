{{-- 一覧・詳細の QR ボタン（data-qr-open）から開く共通ダイアログ。画像とダウンロード先は JavaScript で差し替える --}}
<dialog id="link-qr-dialog" class="dialog" aria-labelledby="link-qr-dialog-title">
    <div class="p-6 text-center sm:p-8">
        <h2 id="link-qr-dialog-title" class="font-rounded text-section font-bold">QRコード</h2>
        <p class="mt-1 text-sm text-text-secondary" data-qr-caption></p>
        <div class="mx-auto mt-5 flex size-60 items-center justify-center rounded-card border border-border bg-white">
            <img src="data:," alt="" width="224" height="224" data-qr-image>
        </div>

        <p class="mt-5 text-[13px] font-medium text-text-secondary">ダウンロード</p>
        <div class="mt-2 flex flex-wrap justify-center gap-2.5">
            <x-button variant="secondary" size="sm" href="#" download data-qr-download="svg">
                <x-icon name="qr-code" :size="16" />
                SVG
            </x-button>
            @if (\App\Services\ShortUrl\QrCodeGenerator::supportsPng())
                <x-button variant="secondary" size="sm" href="#" download data-qr-download="png">
                    <x-icon name="qr-code" :size="16" />
                    PNG
                </x-button>
            @endif
        </div>
        <p class="mt-2 text-xs text-text-secondary">SVG は拡大しても粗くなりません。PNG は画像として貼り付けやすい形式です。</p>

        <form method="dialog" class="mt-6">
            <x-button type="submit" variant="secondary" size="sm">閉じる</x-button>
        </form>
    </div>
</dialog>
