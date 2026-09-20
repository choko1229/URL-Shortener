{{--
    お問い合わせ（requirements.md には無い補助ページ。内容は管理画面で読む）
    @var \App\ViewModels\ViewerData $viewer
    @var string|null $discordContact
    @var string|null $recaptchaSiteKey
--}}
<x-layouts.main :viewer="$viewer" title="お問い合わせ">
    <div class="px-4 pt-12 pb-16 sm:px-8 sm:pt-16">
        <div class="mx-auto max-w-[560px]">
            <h1 class="font-rounded text-section font-bold">お問い合わせ</h1>
            <p class="mt-2 text-sm leading-relaxed text-text-secondary">
                不具合の報告、短縮URLの削除依頼、その他のご連絡はこちらからお送りください。個人で運営しているため、返信までに時間をいただくことがあります。
            </p>

            @if ($discordContact)
                <div class="mt-5 flex items-start gap-2.5 rounded-control border border-border-strong bg-primary-tint-soft px-4 py-3 text-[13px] leading-relaxed">
                    <x-icon name="info" :size="16" class="mt-0.5 shrink-0 text-primary-dark" />
                    <p>Discord からもご連絡いただけます: <span class="font-medium break-all">{{ $discordContact }}</span></p>
                </div>
            @endif

            <x-flash-messages class="mt-5" />

            <form
                method="POST"
                action="{{ route('main.contact.store') }}"
                class="mt-6 space-y-5 rounded-card-lg bg-surface p-6 shadow-card-lg sm:p-8"
                @if ($recaptchaSiteKey) data-recaptcha-site-key="{{ $recaptchaSiteKey }}" data-recaptcha-action="{{ \App\Services\Security\RecaptchaVerifier::ACTION_CONTACT }}" @endif
            >
                @csrf
                @if ($recaptchaSiteKey)
                    <input type="hidden" name="recaptcha_token" value="" data-recaptcha-token>
                @endif

                <div>
                    <label for="contact-name" class="block text-[13px] font-medium text-text-secondary">お名前（任意）</label>
                    <input
                        id="contact-name"
                        name="name"
                        type="text"
                        maxlength="64"
                        autocomplete="name"
                        value="{{ old('name') }}"
                        class="form-control mt-2"
                        @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror
                    >
                    <x-field-error name="name" id="contact-name-error" />
                </div>

                <div>
                    <label for="contact-reply-to" class="block text-[13px] font-medium text-text-secondary">返信先（任意）</label>
                    <input
                        id="contact-reply-to"
                        name="reply_to"
                        type="text"
                        maxlength="190"
                        autocomplete="email"
                        value="{{ old('reply_to') }}"
                        class="form-control mt-2"
                        aria-describedby="contact-reply-to-hint @error('reply_to') contact-reply-to-error @enderror"
                        @error('reply_to') aria-invalid="true" @enderror
                    >
                    <p id="contact-reply-to-hint" class="mt-1.5 text-xs text-text-secondary">メールアドレスや Discord のユーザー名など。未入力の場合、返信はできません。</p>
                    <x-field-error name="reply_to" id="contact-reply-to-error" />
                </div>

                <div>
                    <label for="contact-message" class="block text-[13px] font-medium text-text-secondary">お問い合わせの内容</label>
                    <textarea
                        id="contact-message"
                        name="message"
                        rows="8"
                        required
                        minlength="10"
                        maxlength="2000"
                        class="form-control mt-2 resize-y"
                        @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror
                    >{{ old('message') }}</textarea>
                    <x-field-error name="message" id="contact-message-error" />
                </div>

                <p class="text-xs leading-relaxed text-text-secondary">
                    送信された内容は<a href="{{ route('main.privacy') }}" class="text-primary-dark underline hover:text-primary-darker">プライバシーポリシー</a>に従って取り扱います。
                    短縮URLの削除依頼の場合は、対象の短縮URLを本文に記載してください。
                </p>

                @if ($recaptchaSiteKey)
                    <p class="text-[11px] leading-relaxed text-text-secondary">
                        このフォームは reCAPTCHA で保護されており、Google の
                        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary-dark">プライバシーポリシー</a>と
                        <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary-dark">利用規約</a>が適用されます。
                    </p>
                @endif

                <div class="flex justify-end">
                    <x-button type="submit">送信する</x-button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.main>
