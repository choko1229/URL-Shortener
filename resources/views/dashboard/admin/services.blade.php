{{--
    外部サービスの設定（Discord ログイン・悪意URLチェック・reCAPTCHA・国判定）
    @var \App\ViewModels\ViewerData $viewer
    @var string $callbackUrl
    @var string|null $discordClientId
    @var bool $hasDiscordSecret
    @var bool $hasSafeBrowsingKey
    @var string|null $recaptchaSiteKey
    @var bool $hasRecaptchaSecret
    @var string $geoipPath
    @var bool $hasGeoipDatabase
--}}
<x-dashboard.page :viewer="$viewer" title="外部サービス" description="ログインや安全確認に使う外部サービスのキーを設定します。機密値は APP_KEY で暗号化してデータベースに保存し、画面には表示しません。">
    <form method="POST" action="{{ route('dashboard.admin.services.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section aria-labelledby="discord-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
            <h2 id="discord-heading" class="font-rounded text-[15px] font-bold">Discord ログイン</h2>
            <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
                Discord Developer Portal のアプリケーションの「OAuth2」→「Redirects」に、次の URL を登録してください。
            </p>
            <div class="mt-3 flex max-w-2xl items-center gap-2 rounded-control border border-border-input bg-primary-tint-soft py-1 pr-1 pl-3">
                <code class="min-w-0 flex-1 break-all text-[13px]">{{ $callbackUrl }}</code>
                <x-copy-button :text="$callbackUrl" label="Redirect URI をコピー" />
            </div>
            <div class="mt-5 grid max-w-2xl grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="discord_client_id" class="block text-[13px] font-medium text-text-secondary">Client ID</label>
                    <input id="discord_client_id" name="discord_client_id" type="text" inputmode="numeric" required autocomplete="off" spellcheck="false" value="{{ old('discord_client_id', $discordClientId) }}" class="form-control mt-2" @error('discord_client_id') aria-invalid="true" aria-describedby="discord_client_id-error" @enderror>
                    <x-field-error name="discord_client_id" id="discord_client_id-error" />
                </div>
                <div>
                    <label for="discord_client_secret" class="block text-[13px] font-medium text-text-secondary">Client Secret（{{ $hasDiscordSecret ? '設定済み。変更する場合のみ入力' : '未設定' }}）</label>
                    <input id="discord_client_secret" name="discord_client_secret" type="password" autocomplete="new-password" spellcheck="false" class="form-control mt-2" @unless ($hasDiscordSecret) required @endunless @error('discord_client_secret') aria-invalid="true" aria-describedby="discord_client_secret-error" @enderror>
                    <x-field-error name="discord_client_secret" id="discord_client_secret-error" />
                </div>
            </div>
        </section>

        <section aria-labelledby="safe-browsing-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
            <h2 id="safe-browsing-heading" class="font-rounded text-[15px] font-bold">悪意URLチェック（Google Safe Browsing）</h2>
            <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
                未設定の場合、リダイレクト時に「安全性を確認できませんでした」と表示し、利用者の判断で移動します。
            </p>
            <div class="mt-5 max-w-2xl">
                <label for="safe_browsing_api_key" class="block text-[13px] font-medium text-text-secondary">API キー（{{ $hasSafeBrowsingKey ? '設定済み。変更する場合のみ入力' : '未設定' }}）</label>
                <input id="safe_browsing_api_key" name="safe_browsing_api_key" type="password" autocomplete="new-password" spellcheck="false" class="form-control mt-2" @error('safe_browsing_api_key') aria-invalid="true" aria-describedby="safe_browsing_api_key-error" @enderror>
                <x-field-error name="safe_browsing_api_key" id="safe_browsing_api_key-error" />
                @if ($hasSafeBrowsingKey)
                    <label class="mt-2 flex cursor-pointer items-center gap-2 text-xs text-text-secondary">
                        <input type="checkbox" name="clear_safe_browsing_api_key" value="1" class="size-4 accent-danger">
                        API キーを削除する
                    </label>
                @endif
            </div>
        </section>

        <section aria-labelledby="recaptcha-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
            <h2 id="recaptcha-heading" class="font-rounded text-[15px] font-bold">スパム対策（reCAPTCHA v3）</h2>
            <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
                未ログインでの発行に適用します。未設定の場合は、レート制限と月間上限のみで制限します。
            </p>
            <div class="mt-5 grid max-w-2xl grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="recaptcha_site_key" class="block text-[13px] font-medium text-text-secondary">サイトキー</label>
                    <input id="recaptcha_site_key" name="recaptcha_site_key" type="text" autocomplete="off" spellcheck="false" value="{{ old('recaptcha_site_key', $recaptchaSiteKey) }}" class="form-control mt-2" @error('recaptcha_site_key') aria-invalid="true" aria-describedby="recaptcha_site_key-error" @enderror>
                    <x-field-error name="recaptcha_site_key" id="recaptcha_site_key-error" />
                </div>
                <div>
                    <label for="recaptcha_secret_key" class="block text-[13px] font-medium text-text-secondary">シークレットキー（{{ $hasRecaptchaSecret ? '設定済み。変更する場合のみ入力' : '未設定' }}）</label>
                    <input id="recaptcha_secret_key" name="recaptcha_secret_key" type="password" autocomplete="new-password" spellcheck="false" class="form-control mt-2" @error('recaptcha_secret_key') aria-invalid="true" aria-describedby="recaptcha_secret_key-error" @enderror>
                    <x-field-error name="recaptcha_secret_key" id="recaptcha_secret_key-error" />
                </div>
            </div>
            @if ($recaptchaSiteKey !== null)
                <label class="mt-3 flex cursor-pointer items-center gap-2 text-xs text-text-secondary">
                    <input type="checkbox" name="clear_recaptcha" value="1" class="size-4 accent-danger">
                    reCAPTCHA の設定を削除する
                </label>
            @endif
        </section>

        <div>
            <x-button type="submit" size="sm">保存する</x-button>
        </div>
    </form>

    <section aria-labelledby="geoip-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
        <h2 id="geoip-heading" class="font-rounded text-[15px] font-bold">国の判定（MaxMind GeoLite2）</h2>
        <p class="mt-2 flex items-start gap-2 text-sm">
            @if ($hasGeoipDatabase)
                <x-icon name="check-circle" :size="18" class="mt-0.5 text-primary-dark" />
                データベースを読み込めます。クリック元の国を記録しています。
            @else
                <x-icon name="info" :size="18" class="mt-0.5 text-text-secondary" />
                データベースが設置されていないため、国は記録していません。
            @endif
        </p>
        <p class="mt-2 text-[13px] leading-relaxed text-text-secondary">
            MaxMind のサイトで無料アカウントを作成して GeoLite2 Country（.mmdb）をダウンロードし、
            <code class="break-all rounded bg-primary-tint-soft px-1">{{ $geoipPath }}</code> に設置してください（任意）。
        </p>
    </section>
</x-dashboard.page>
