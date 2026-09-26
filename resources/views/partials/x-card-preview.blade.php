{{--
    X（旧 Twitter）に短縮URLを貼ったときの見え方（イメージ）。中身は app.js（initXCardPreviews）が
    同じフォームの入力（転送先URL・パスワード・カードの出し方・指定した内容）から組み立てる。
    @var string $fetchUrl        転送先のカード情報を取得する URL
    @var string $shortHost       短縮URLのホスト
    @var string|null $slug       短縮コード（発行済みのリンクの場合）
    @var string|null $destination 転送先URL（発行済みのリンクの場合。発行フォームでは入力欄から読む）
    @var bool $passwordProtected パスワード保護つきか（発行済みのリンクの場合）
--}}
@php
    $slug ??= null;
    $destination ??= null;
    $passwordProtected ??= false;
@endphp

<div
    class="mt-5"
    data-x-card
    data-fetch-url="{{ $fetchUrl }}"
    data-site-name="{{ $site->name() }}"
    data-short-host="{{ $shortHost }}"
    @if ($slug !== null) data-slug="{{ $slug }}" @endif
    @if ($destination !== null) data-destination="{{ $destination }}" @endif
    @if ($passwordProtected) data-password-protected="true" @endif
>
    <p class="flex items-center gap-1.5 text-[13px] font-medium text-text-secondary">
        <x-icon name="share" :size="14" />
        X に貼ったときの見え方（イメージ）
    </p>

    <div class="mt-2 max-w-[520px] rounded-card border border-border bg-surface p-4" aria-hidden="true">
        <div class="flex gap-3">
            <span class="size-10 shrink-0 rounded-full bg-primary-tint"></span>
            <div class="min-w-0 flex-1">
                <p class="flex items-baseline gap-1.5 text-[13px]">
                    <span class="font-bold text-text-primary">あなた</span>
                    <span class="text-text-muted">@you · たった今</span>
                </p>
                <p class="mt-0.5 break-all text-sm text-primary-dark" data-x-card-post></p>

                {{-- summary_large_image: 大きな画像の上にタイトル、下にドメイン --}}
                <div class="mt-3" data-x-card-large hidden>
                    <div class="relative aspect-[1.91/1] overflow-hidden rounded-2xl border border-border bg-primary-tint-soft">
                        <img alt="" class="size-full object-cover" referrerpolicy="no-referrer" loading="lazy" data-x-card-image>
                        <span class="absolute bottom-2.5 left-2.5 max-w-[calc(100%-20px)] truncate rounded-md bg-black/65 px-2 py-0.5 text-[13px] text-white" data-x-card-title></span>
                    </div>
                    <p class="mt-1 text-xs text-text-muted" data-x-card-from></p>
                </div>

                {{-- summary: 左に正方形の画像、右にドメイン・タイトル・説明 --}}
                <div class="mt-3 flex overflow-hidden rounded-2xl border border-border" data-x-card-small hidden>
                    <div class="flex w-[104px] shrink-0 items-center justify-center border-r border-border bg-primary-tint-soft text-text-muted sm:w-[128px]">
                        <img alt="" class="aspect-square size-full object-cover" referrerpolicy="no-referrer" loading="lazy" data-x-card-image>
                        <x-icon name="link" :size="28" data-x-card-placeholder />
                    </div>
                    <div class="min-w-0 flex-1 px-3 py-2.5 text-[13px] leading-snug">
                        <p class="truncate text-text-muted" data-x-card-domain></p>
                        <p class="truncate font-medium text-text-primary" data-x-card-title></p>
                        <p class="line-clamp-2 text-text-secondary" data-x-card-description></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p class="mt-2 text-xs leading-relaxed text-text-secondary" data-x-card-status aria-live="polite"></p>
</div>
