{{--
    @var array{main: string, dashboard: string, api: string, redirect: string} $suggested
    @var bool $secure
--}}
@php
    $domainFields = [
        'main_domain' => ['label' => 'メインドメイン', 'suggested' => $suggested['main'], 'hint' => '短縮URL（例: chok.ooo/abc1234）とトップページに使います'],
        'dashboard_domain' => ['label' => 'ダッシュボード', 'suggested' => $suggested['dashboard'], 'hint' => null],
        'api_domain' => ['label' => 'API', 'suggested' => $suggested['api'], 'hint' => null],
        'redirect_domain' => ['label' => 'リダイレクト確認', 'suggested' => $suggested['redirect'], 'hint' => null],
    ];
@endphp

<x-layouts.install :step="3" title="サイト設定">
    <form method="POST" action="{{ route('install.site.store') }}" class="space-y-8">
        @csrf

        <fieldset>
            <legend class="font-rounded text-base font-bold">ドメイン</legend>
            <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
                4つのドメインは、すべてこのフォルダを公開するように設定してください。https:// は付けずに入力します。
            </p>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                @foreach ($domainFields as $name => $field)
                    <div @class(['sm:col-span-2' => $name === 'main_domain'])>
                        <label for="{{ $name }}" class="block text-[13px] font-medium text-text-secondary">{{ $field['label'] }}</label>
                        <input
                            id="{{ $name }}"
                            name="{{ $name }}"
                            type="text"
                            inputmode="url"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            required
                            value="{{ old($name, $field['suggested']) }}"
                            class="form-control mt-2"
                            @php
                                $describedBy = array_filter([
                                    $field['hint'] ? "{$name}-hint" : null,
                                    $errors->has($name) ? "{$name}-error" : null,
                                ]);
                            @endphp
                            @if ($describedBy !== []) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
                            @error($name) aria-invalid="true" @enderror
                        >
                        @if ($field['hint'])
                            <p id="{{ $name }}-hint" class="mt-1.5 text-xs text-text-secondary">{{ $field['hint'] }}</p>
                        @endif
                        <x-field-error :name="$name" :id="$name.'-error'" />
                    </div>
                @endforeach
            </div>
            @unless ($secure)
                <p class="mt-4 flex items-start gap-2 text-[13px] text-warning">
                    <x-icon name="alert-circle" :size="16" class="mt-0.5" />
                    HTTP で接続しているため、URL は http:// で登録されます。SSL を設定済みなら https:// で開き直してください。
                </p>
            @endunless
        </fieldset>

        <fieldset>
            <legend class="font-rounded text-base font-bold">Discord ログイン（任意）</legend>
            <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
                Discord Developer Portal で作成したアプリケーションの OAuth2 情報です。Client Secret は暗号化してデータベースに保存します。
                ログイン機能は未実装のため、今は空欄のままでも構いません。
            </p>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="discord_client_id" class="block text-[13px] font-medium text-text-secondary">Client ID</label>
                    <input
                        id="discord_client_id"
                        name="discord_client_id"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        spellcheck="false"
                        value="{{ old('discord_client_id') }}"
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
                        class="form-control mt-2"
                        @error('discord_client_secret') aria-invalid="true" aria-describedby="discord_client_secret-error" @enderror
                    >
                    <x-field-error name="discord_client_secret" id="discord_client_secret-error" />
                </div>
            </div>
        </fieldset>

        <div class="rounded-control border border-border-strong bg-primary-tint-soft px-4 py-3 text-[13px] leading-relaxed">
            「セットアップを完了する」を押すと、テーブルの作成と初期データの登録を行います。完了後、このセットアップ画面は使えなくなります。
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('install.database') }}" class="text-sm text-primary-dark hover:text-primary-darker">データベース設定に戻る</a>
            <x-button type="submit">セットアップを完了する</x-button>
        </div>
    </form>
</x-layouts.install>
