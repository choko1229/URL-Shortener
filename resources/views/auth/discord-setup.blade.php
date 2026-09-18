{{--
    @var string $callbackUrl
    @var string|null $clientId
    @var bool $hasSecret
--}}
<x-layouts.install title="Discord ログインの設定">
    <p class="text-sm leading-relaxed text-text-secondary">
        chok.ooo のログインには Discord を使います。Discord 側でアプリケーションを作成し、発行された値を入力してください。
        保存するとそのまま Discord のログイン画面へ進み、<strong class="font-medium text-text-primary">最初にログインした人が管理者</strong>になります。
    </p>

    <ol class="mt-6 space-y-5 text-sm leading-relaxed">
        <li class="flex gap-3">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-tint font-rounded text-[13px] font-bold text-primary-dark">1</span>
            <div class="min-w-0">
                <a href="https://discord.com/developers/applications" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-medium text-primary-dark underline hover:text-primary-darker">
                    Discord Developer Portal<x-icon name="external-link" :size="14" /><span class="sr-only">（新しいタブで開く）</span>
                </a>
                を開き、「New Application」でアプリケーションを作成します（名前は自由です）。
            </div>
        </li>
        <li class="flex gap-3">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-tint font-rounded text-[13px] font-bold text-primary-dark">2</span>
            <div class="min-w-0 flex-1">
                <p>左のメニューの「OAuth2」→「Redirects」に、次の URL を追加して保存します。</p>
                <div class="mt-2 flex items-center gap-2 rounded-control border border-border-input bg-primary-tint-soft py-1 pr-1 pl-3">
                    <code id="discord-callback-url" class="min-w-0 flex-1 break-all text-[13px]">{{ $callbackUrl }}</code>
                    <x-copy-button :text="$callbackUrl" label="Redirect URI をコピー" />
                </div>
            </div>
        </li>
        <li class="flex gap-3">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-tint font-rounded text-[13px] font-bold text-primary-dark">3</span>
            <p class="min-w-0">同じ「OAuth2」の画面にある Client ID と Client Secret（「Reset Secret」で表示されます）を、下に入力します。</p>
        </li>
    </ol>

    <form method="POST" action="{{ route('auth.setup.store') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="discord_client_id" class="block text-[13px] font-medium text-text-secondary">Client ID</label>
            <input
                id="discord_client_id"
                name="discord_client_id"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                spellcheck="false"
                required
                value="{{ old('discord_client_id', $clientId) }}"
                class="form-control mt-2"
                @error('discord_client_id') aria-invalid="true" aria-describedby="discord_client_id-error" @enderror
            >
            <x-field-error name="discord_client_id" id="discord_client_id-error" />
        </div>

        <div>
            <label for="discord_client_secret" class="block text-[13px] font-medium text-text-secondary">Client Secret</label>
            <input
                id="discord_client_secret"
                name="discord_client_secret"
                type="password"
                autocomplete="new-password"
                spellcheck="false"
                @unless ($hasSecret) required @endunless
                class="form-control mt-2"
                aria-describedby="discord_client_secret-hint @error('discord_client_secret') discord_client_secret-error @enderror"
                @error('discord_client_secret') aria-invalid="true" @enderror
            >
            <p id="discord_client_secret-hint" class="mt-1.5 text-xs text-text-secondary">
                {{ $hasSecret ? '設定済みです。変更する場合のみ入力してください。' : '暗号化してデータベースに保存します。' }}
            </p>
            <x-field-error name="discord_client_secret" id="discord_client_secret-error" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[13px] leading-relaxed text-text-secondary">管理者が登録されると、この画面は使えなくなります。</p>
            <x-button type="submit">保存して Discord でログイン</x-button>
        </div>
    </form>
</x-layouts.install>
