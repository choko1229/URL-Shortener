{{--
    外部サービスの設定（Discord ログイン・悪意URLチェック・reCAPTCHA・国判定）
    @var \App\ViewModels\ViewerData $viewer
    @var string $callbackUrl
    @var string|null $discordClientId
    @var bool $hasDiscordSecret
    @var bool $hasSafeBrowsingKey
    @var string|null $recaptchaSiteKey
    @var string|null $recaptchaProjectId
    @var bool $hasRecaptchaApiKey
    @var string $mainDomain
    @var \App\Services\GeoIp\GeoIpSource|null $geoIpSource
    @var string|null $geoIpRelease
    @var \Carbon\CarbonImmutable|null $geoIpUpdatedAt
    @var bool $geoIpAutoUpdate
    @var string $geoIpManualPath
--}}
<x-dashboard.admin-page :viewer="$viewer" title="外部サービス" description="ログインや安全確認に使う外部サービスのキーを設定します。機密値は APP_KEY で暗号化してデータベースに保存し、画面には表示しません。">
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
            <h2 id="recaptcha-heading" class="font-rounded text-[15px] font-bold">スパム対策（reCAPTCHA）</h2>
            <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
                未ログインでの発行に適用します（ボタンを押したときに自動で判定し、利用者の操作は不要です）。未設定の場合は、レート制限と月間上限のみで制限します。
            </p>
            <ol class="mt-3 max-w-2xl list-decimal space-y-1 pl-5 text-[13px] leading-relaxed text-text-secondary">
                <li>Google Cloud コンソールの「reCAPTCHA」で、ウェブサイト用のキー（スコアベース）を作成し、ドメインに <code class="rounded bg-primary-tint-soft px-1">{{ $mainDomain }}</code> を登録する</li>
                <li>同じプロジェクトで「reCAPTCHA Enterprise API」を有効にする</li>
                <li>「API とサービス」→「認証情報」で API キーを作成し、使える API を「reCAPTCHA Enterprise API」に制限する</li>
            </ol>
            <div class="mt-5 grid max-w-2xl grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="recaptcha_site_key" class="block text-[13px] font-medium text-text-secondary">キー ID（サイトキー）</label>
                    <input id="recaptcha_site_key" name="recaptcha_site_key" type="text" autocomplete="off" spellcheck="false" value="{{ old('recaptcha_site_key', $recaptchaSiteKey) }}" class="form-control mt-2" @error('recaptcha_site_key') aria-invalid="true" aria-describedby="recaptcha_site_key-error" @enderror>
                    <x-field-error name="recaptcha_site_key" id="recaptcha_site_key-error" />
                </div>
                <div>
                    <label for="recaptcha_project_id" class="block text-[13px] font-medium text-text-secondary">Google Cloud のプロジェクト ID</label>
                    <input id="recaptcha_project_id" name="recaptcha_project_id" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" value="{{ old('recaptcha_project_id', $recaptchaProjectId) }}" class="form-control mt-2" @error('recaptcha_project_id') aria-invalid="true" aria-describedby="recaptcha_project_id-error" @enderror>
                    <x-field-error name="recaptcha_project_id" id="recaptcha_project_id-error" />
                </div>
                <div class="sm:col-span-2">
                    <label for="recaptcha_api_key" class="block text-[13px] font-medium text-text-secondary">API キー（{{ $hasRecaptchaApiKey ? '設定済み。変更する場合のみ入力' : '未設定' }}）</label>
                    <input id="recaptcha_api_key" name="recaptcha_api_key" type="password" autocomplete="new-password" spellcheck="false" class="form-control mt-2" aria-describedby="recaptcha_api_key-hint{{ $errors->has('recaptcha_api_key') ? ' recaptcha_api_key-error' : '' }}" @error('recaptcha_api_key') aria-invalid="true" @enderror>
                    <p id="recaptcha_api_key-hint" class="mt-1.5 text-xs text-text-secondary">保存するときに、この API キーで判定できるか Google に確認します。</p>
                    <x-field-error name="recaptcha_api_key" id="recaptcha_api_key-error" />
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
        <h2 id="geoip-heading" class="font-rounded text-[15px] font-bold">国の判定</h2>
        <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
            クリック元の IP アドレスから国を判定します。判定に使うデータベースは自動で取得し、毎月更新します（設定は不要です）。訪問者の IP は外部に送りません。
        </p>
        <p class="mt-3 flex items-start gap-2 text-sm">
            @if ($geoIpSource !== null)
                <x-icon name="check-circle" :size="18" class="mt-0.5 shrink-0 text-primary-dark" />
                <span>
                    {{ $geoIpSource->label() }}
                    @if ($geoIpSource === \App\Services\GeoIp\GeoIpSource::DbIp && $geoIpRelease !== null)
                        <span class="text-text-secondary">— {{ $geoIpRelease }} 版{{ $geoIpUpdatedAt ? '（'.$geoIpUpdatedAt->format('Y/m/d H:i').' に取得）' : '' }}</span>
                    @endif
                </span>
            @elseif ($geoIpAutoUpdate)
                <x-icon name="info" :size="18" class="mt-0.5 shrink-0 text-text-secondary" />
                まだ取得していません。サイトへのアクセスをきっかけに自動で取得します（今すぐ取得することもできます）。
            @else
                <x-icon name="info" :size="18" class="mt-0.5 shrink-0 text-text-secondary" />
                自動取得が無効（SHORTENER_GEOIP_AUTO_UPDATE=false）のため、国は記録していません。
            @endif
        </p>
        @if ($geoIpAutoUpdate)
            <form method="POST" action="{{ route('dashboard.admin.services.geoip') }}" class="mt-4">
                @csrf
                <x-button type="submit" variant="secondary" size="sm">
                    <x-icon name="refresh" :size="16" />
                    {{ $geoIpSource === null ? '今すぐ取得する' : '今すぐ更新を確認する' }}
                </x-button>
            </form>
        @endif
        <p class="mt-4 text-xs leading-relaxed text-text-secondary">
            データは <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary-dark">DB-IP</a> の IP to Country Lite（CC BY 4.0）です。国の統計には出典のリンクを表示します。
            MaxMind GeoLite2 Country（.mmdb）を <code class="break-all rounded bg-primary-tint-soft px-1">{{ $geoIpManualPath }}</code> に置いた場合は、そちらを優先して使います。
        </p>
    </section>
</x-dashboard.admin-page>
