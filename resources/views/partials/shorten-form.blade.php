{{--
    短縮URL発行フォーム（トップページ・ダッシュボード共用）
    @var \App\ViewModels\ShortUrlFormData $form
    @var string $action      送信先URL
    @var string $idPrefix    同一ページ内で id が衝突しないための接頭辞
    @var string $submitLabel 送信ボタンの文言
    @var string|null $caption 入力欄の下に出す補足
--}}
@php
    $caption ??= null;
    $ids = [
        'url' => "{$idPrefix}-original-url",
        'slug' => "{$idPrefix}-custom-slug",
        'slugHint' => "{$idPrefix}-custom-slug-hint",
        'passwordPanel' => "{$idPrefix}-password-panel",
        'password' => "{$idPrefix}-password",
        'expiryPanel' => "{$idPrefix}-expiry-panel",
        'expirySummary' => "{$idPrefix}-expiry-summary",
        'expiresAt' => "{$idPrefix}-expires-at",
        'previewPanel' => "{$idPrefix}-preview-panel",
        'previewSummary' => "{$idPrefix}-preview-summary",
        'previewTitle' => "{$idPrefix}-preview-title",
        'previewDescription' => "{$idPrefix}-preview-description",
        'previewImage' => "{$idPrefix}-preview-image",
        'loginDialog' => 'login-dialog',
    ];
    $selectedExpiry = \App\Enums\ExpiryOption::tryFrom((string) old('expiry', $form->defaultExpiry->value)) ?? $form->defaultExpiry;
    $selectedPreviewMode = \App\Enums\PreviewMode::tryFrom((string) old('preview_mode', \App\Enums\PreviewMode::Destination->value)) ?? \App\Enums\PreviewMode::Destination;
    $showPasswordPanel = $errors->has('password');
    $showExpiryPanel = $errors->hasAny(['expiry', 'expires_at']);
    $showPreviewPanel = $errors->hasAny(['preview_mode', 'preview_title', 'preview_description', 'preview_image_url']);
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="space-y-4"
    @if ($form->recaptchaSiteKey) data-recaptcha-site-key="{{ $form->recaptchaSiteKey }}" data-recaptcha-action="{{ \App\Services\Security\RecaptchaVerifier::ACTION_SHORTEN }}" @endif
>
    @csrf
    @if ($form->recaptchaSiteKey)
        <input type="hidden" name="recaptcha_token" value="" data-recaptcha-token>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
        <div class="min-w-0 flex-1">
            <label for="{{ $ids['url'] }}" class="sr-only">短縮したいURL</label>
            <input
                id="{{ $ids['url'] }}"
                name="original_url"
                type="url"
                inputmode="url"
                autocomplete="url"
                required
                maxlength="2048"
                placeholder="https://example.com/very/long/path/to/shorten"
                value="{{ old('original_url') }}"
                class="form-control"
                @error('original_url') aria-invalid="true" aria-describedby="{{ $ids['url'] }}-error" @enderror
            >
            <x-field-error name="original_url" :id="$ids['url'].'-error'" />
        </div>

        @if ($form->isMember)
            <div class="sm:w-[220px]">
                <label for="{{ $ids['slug'] }}" class="sr-only">カスタムスラッグ（任意）</label>
                <input
                    id="{{ $ids['slug'] }}"
                    name="custom_slug"
                    type="text"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    minlength="{{ $form->customSlugMinLength }}"
                    maxlength="{{ $form->customSlugMaxLength }}"
                    pattern="[A-Za-z0-9_\-]+"
                    placeholder="カスタムスラッグ（任意）"
                    value="{{ old('custom_slug') }}"
                    class="form-control"
                    aria-describedby="{{ $ids['slugHint'] }}{{ $errors->has('custom_slug') ? ' '.$ids['slug'].'-error' : '' }}"
                    @error('custom_slug') aria-invalid="true" @enderror
                >
                <p id="{{ $ids['slugHint'] }}" class="sr-only">
                    {{ $form->shortHost }}/ の後ろに続く文字列です。半角英数字・ハイフン・アンダースコアで{{ $form->customSlugMinLength }}〜{{ $form->customSlugMaxLength }}文字。大文字と小文字は区別されます。
                </p>
                <x-field-error name="custom_slug" :id="$ids['slug'].'-error'" />
            </div>
        @endif

        <x-button type="submit" class="w-full sm:w-auto">{{ $submitLabel }}</x-button>
    </div>

    @if ($caption)
        <p class="text-xs text-text-secondary">{{ $caption }}</p>
    @endif

    <div class="flex flex-wrap gap-2.5">
        <button type="button" class="chip" aria-controls="{{ $ids['passwordPanel'] }}" aria-expanded="{{ $showPasswordPanel ? 'true' : 'false' }}" data-disclosure>
            <x-icon name="lock" :size="14" />
            パスワード保護
        </button>
        <button type="button" class="chip" aria-controls="{{ $ids['expiryPanel'] }}" aria-expanded="{{ $showExpiryPanel ? 'true' : 'false' }}" data-disclosure>
            <x-icon name="clock" :size="14" />
            有効期限: <span id="{{ $ids['expirySummary'] }}">{{ $selectedExpiry->label() }}</span>
        </button>
        <button type="button" class="chip" aria-controls="{{ $ids['previewPanel'] }}" aria-expanded="{{ $showPreviewPanel ? 'true' : 'false' }}" data-disclosure>
            <x-icon name="share" :size="14" />
            共有時のカード: <span id="{{ $ids['previewSummary'] }}">{{ $selectedPreviewMode->label() }}</span>
        </button>
        @unless ($form->isMember)
            <a href="{{ route('auth.login') }}" class="chip">
                <x-icon name="pencil" :size="14" />
                カスタムスラッグ（ログインで利用可）
            </a>
        @endunless
    </div>

    <div
        id="{{ $ids['previewPanel'] }}"
        class="rounded-card border border-border bg-primary-tint-soft p-4 sm:p-5"
        data-preview-group
        data-preview-summary="{{ $ids['previewSummary'] }}"
        @unless ($showPreviewPanel) hidden @endunless
    >
        <fieldset>
            <legend class="text-[13px] font-medium text-text-secondary">Discord や X に貼ったときの表示</legend>
            <div class="mt-2 space-y-2">
                @foreach (\App\Enums\PreviewMode::cases() as $mode)
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                        <input
                            type="radio"
                            name="preview_mode"
                            value="{{ $mode->value }}"
                            @checked($selectedPreviewMode === $mode)
                            data-preview-mode
                            data-label="{{ $mode->label() }}"
                            class="mt-1 size-4 shrink-0 accent-primary-dark"
                        >
                        <span>
                            <span class="font-medium">{{ $mode->label() }}</span>
                            <span class="block text-xs text-text-secondary">{{ $mode->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="mt-4 space-y-4" data-preview-custom @unless ($selectedPreviewMode === \App\Enums\PreviewMode::Custom) hidden @endunless>
            <div>
                <label for="{{ $ids['previewTitle'] }}" class="block text-[13px] font-medium text-text-secondary">カードのタイトル</label>
                <input id="{{ $ids['previewTitle'] }}" name="preview_title" type="text" maxlength="120" value="{{ old('preview_title') }}" class="form-control mt-2" @error('preview_title') aria-invalid="true" aria-describedby="{{ $ids['previewTitle'] }}-error" @enderror>
                <x-field-error name="preview_title" :id="$ids['previewTitle'].'-error'" />
            </div>
            <div>
                <label for="{{ $ids['previewDescription'] }}" class="block text-[13px] font-medium text-text-secondary">カードの説明（任意）</label>
                <input id="{{ $ids['previewDescription'] }}" name="preview_description" type="text" maxlength="300" value="{{ old('preview_description') }}" class="form-control mt-2" @error('preview_description') aria-invalid="true" aria-describedby="{{ $ids['previewDescription'] }}-error" @enderror>
                <x-field-error name="preview_description" :id="$ids['previewDescription'].'-error'" />
            </div>
            <div>
                <label for="{{ $ids['previewImage'] }}" class="block text-[13px] font-medium text-text-secondary">カードの画像URL（任意）</label>
                <input id="{{ $ids['previewImage'] }}" name="preview_image_url" type="url" maxlength="2048" value="{{ old('preview_image_url') }}" class="form-control mt-2" @error('preview_image_url') aria-invalid="true" aria-describedby="{{ $ids['previewImage'] }}-error" @enderror>
                <x-field-error name="preview_image_url" :id="$ids['previewImage'].'-error'" />
            </div>
        </div>

        <p class="mt-4 text-xs leading-relaxed text-text-secondary">
            パスワード保護をつけたリンクは、転送先が分からないよう常に「カードを隠す」になります。
        </p>
    </div>

    <div id="{{ $ids['passwordPanel'] }}" class="rounded-card border border-border bg-primary-tint-soft p-4 sm:p-5" @unless ($showPasswordPanel) hidden @endunless>
        <label for="{{ $ids['password'] }}" class="block text-[13px] font-medium text-text-secondary">アクセス用パスワード（任意）</label>
        <input
            id="{{ $ids['password'] }}"
            name="password"
            type="password"
            autocomplete="new-password"
            minlength="4"
            maxlength="72"
            class="form-control mt-2 sm:max-w-sm"
            aria-describedby="{{ $ids['password'] }}-hint{{ $errors->has('password') ? ' '.$ids['password'].'-error' : '' }}"
            @error('password') aria-invalid="true" @enderror
        >
        <p id="{{ $ids['password'] }}-hint" class="mt-2 text-xs text-text-secondary">設定すると、リンクを開く前にパスワードの入力が必要になります（4〜72文字）。</p>
        <x-field-error name="password" :id="$ids['password'].'-error'" />
    </div>

    <div
        id="{{ $ids['expiryPanel'] }}"
        class="rounded-card border border-border bg-primary-tint-soft p-4 sm:p-5"
        data-expiry-group
        data-expiry-summary="{{ $ids['expirySummary'] }}"
        @unless ($showExpiryPanel) hidden @endunless
    >
        <fieldset>
            <legend class="text-[13px] font-medium text-text-secondary">
                有効期限@if ($form->maxExpiryDays !== null)（ログインしていない場合は最大{{ $form->maxExpiryDays }}日）@endif
            </legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($form->expiryOptions as $option)
                    @php
                        $requiresLogin = $option === \App\Enums\ExpiryOption::Never && $form->neverRequiresLogin();
                    @endphp
                    <label class="cursor-pointer">
                        <input
                            type="radio"
                            name="expiry"
                            value="{{ $option->value }}"
                            class="peer sr-only"
                            data-label="{{ $option->label() }}"
                            @checked($selectedExpiry === $option)
                            @if ($requiresLogin) data-requires-login="{{ $ids['loginDialog'] }}" aria-describedby="{{ $ids['expiryPanel'] }}-never-hint" @endif
                        >
                        <span class="radio-pill">
                            @if ($requiresLogin)
                                <x-icon name="lock" :size="13" />
                            @endif
                            {{ $option->label() }}
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($form->neverRequiresLogin())
                <p id="{{ $ids['expiryPanel'] }}-never-hint" class="mt-2 text-xs text-text-secondary">無期限にするにはログインが必要です。</p>
            @endif
        </fieldset>

        <div class="mt-4" data-expiry-custom @unless ($selectedExpiry === \App\Enums\ExpiryOption::Custom) hidden @endunless>
            <label for="{{ $ids['expiresAt'] }}" class="block text-[13px] font-medium text-text-secondary">有効期限の日時（日本時間）</label>
            <input
                id="{{ $ids['expiresAt'] }}"
                name="expires_at"
                type="datetime-local"
                min="{{ $form->expiresAtMin }}"
                @if ($form->expiresAtMax) max="{{ $form->expiresAtMax }}" @endif
                value="{{ old('expires_at') }}"
                class="form-control mt-2 sm:max-w-xs"
                @unless ($selectedExpiry === \App\Enums\ExpiryOption::Custom) disabled @endunless
                @error('expires_at') aria-invalid="true" aria-describedby="{{ $ids['expiresAt'] }}-error" @enderror
            >
            <x-field-error name="expires_at" :id="$ids['expiresAt'].'-error'" />
        </div>
        <x-field-error name="expiry" :id="$ids['expiryPanel'].'-error'" />
    </div>

    @if ($form->recaptchaSiteKey)
        <p class="text-[11px] leading-relaxed text-text-secondary">
            このフォームは reCAPTCHA で保護されており、Google の
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary-dark">プライバシーポリシー</a>と
            <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary-dark">利用規約</a>が適用されます。
        </p>
    @endif
</form>
