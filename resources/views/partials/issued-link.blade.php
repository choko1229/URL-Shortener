{{--
    発行結果パネル（design.md: color-dark-panel）
    @var \App\ViewModels\IssuedLinkData $link
    @var string $timezone
--}}
<section aria-labelledby="issued-link-heading" class="mt-6 rounded-card bg-dark-panel px-5 py-5 text-white sm:px-7 sm:py-[22px]">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h2 id="issued-link-heading" class="mb-1 text-xs font-medium text-primary">発行された短縮URL</h2>
            <p class="truncate font-rounded text-xl font-bold">
                <a href="{{ $link->shortUrl }}" target="_blank" rel="noopener noreferrer" class="text-white hover:text-primary-tint">
                    {{ $link->displayUrl }}<span class="sr-only">（新しいタブで開く）</span>
                </a>
            </p>
            <p class="mt-1 truncate text-xs text-white/75" title="{{ $link->originalUrl }}">
                <span class="sr-only">転送先: </span>{{ $link->originalUrl }}
            </p>
            <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-white/75">
                <span class="inline-flex items-center gap-1">
                    <x-icon name="clock" :size="13" />
                    {{ $link->expiresAt ? $link->expiresAt->setTimezone($timezone)->format('Y/m/d H:i').' まで有効' : '無期限' }}
                </span>
                @if ($link->isPasswordProtected)
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="lock" :size="13" />
                        パスワード保護あり
                    </span>
                @endif
            </p>
        </div>

        <div class="flex shrink-0 gap-2.5">
            <x-copy-button :text="$link->shortUrl" label="短縮URLをコピー" variant="dark" />
            <x-button variant="dark" size="icon" aria-label="QRコードを表示" title="QRコードを表示" aria-haspopup="dialog" data-dialog-open="qr-dialog">
                <x-icon name="qr-code" :size="18" />
            </x-button>
        </div>
    </div>

    @if ($link->deletionToken !== null)
        <div class="mt-5 rounded-control bg-dark-panel-button p-4">
            <p class="flex items-center gap-1.5 text-[13px] font-bold text-accent-warm">
                <x-icon name="key" :size="14" />
                削除用トークン
            </p>
            <p class="mt-1 text-xs leading-relaxed text-white/80">
                このリンクを<a href="{{ route('main.delete') }}" class="text-white underline hover:text-primary-tint">削除するとき</a>に必要です。この画面を離れると二度と表示できないため、必ず控えてください。
            </p>
            <div class="mt-3 flex items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded-control bg-dark-panel px-3 py-2.5 font-mono text-sm">{{ $link->deletionToken }}</code>
                <x-copy-button :text="$link->deletionToken" label="削除用トークンをコピー" variant="dark" />
            </div>
        </div>
    @endif
</section>

<dialog id="qr-dialog" class="dialog" aria-labelledby="qr-dialog-title">
    <div class="p-6 text-center sm:p-8">
        <h2 id="qr-dialog-title" class="font-rounded text-section font-bold">QRコード</h2>
        <p class="mt-1 text-sm text-text-secondary">{{ $link->displayUrl }}</p>
        <div class="mx-auto mt-5 flex size-60 items-center justify-center rounded-card border border-border bg-white">
            @if ($link->qrCodeDataUri !== null)
                <img src="{{ $link->qrCodeDataUri }}" alt="{{ $link->displayUrl }} のQRコード" width="224" height="224">
            @else
                <p class="px-6 text-sm text-text-secondary">QRコードは発行処理の実装後に表示されます。</p>
            @endif
        </div>
        @if ($link->qrCodeDataUri !== null)
            <p class="mt-5 text-[13px] font-medium text-text-secondary">ダウンロード</p>
            <div class="mt-2 flex flex-wrap justify-center gap-2.5">
                <x-button variant="secondary" size="sm" :href="route('main.short-link.qr', ['code' => $link->code, 'format' => 'svg'])" download>
                    <x-icon name="qr-code" :size="16" />
                    SVG
                </x-button>
                @if (\App\Services\ShortUrl\QrCodeGenerator::supportsPng())
                    <x-button variant="secondary" size="sm" :href="route('main.short-link.qr', ['code' => $link->code, 'format' => 'png'])" download>
                        <x-icon name="qr-code" :size="16" />
                        PNG
                    </x-button>
                @endif
            </div>
            <p class="mt-2 text-xs text-text-secondary">SVG は拡大しても粗くなりません。PNG は画像として貼り付けやすい形式です。</p>
        @endif

        <form method="dialog" class="mt-6">
            <x-button type="submit" variant="secondary" size="sm">閉じる</x-button>
        </form>
    </div>
</dialog>
