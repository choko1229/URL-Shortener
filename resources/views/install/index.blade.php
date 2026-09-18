{{--
    @var list<\App\Installer\RequirementResult> $results
    @var bool $hasFailure
    @var bool $needsConfirmation
    @var array{main: string, dashboard: string, api: string, redirect: string} $suggested
    @var bool $secure
    @var array{db_host: string, db_port: string, db_database: string, db_username: string} $databaseDefaults
--}}
@php
    $databaseFields = [
        'db_host' => ['label' => 'ホスト名', 'type' => 'text', 'autocomplete' => 'off', 'hint' => 'サーバーの管理画面に記載されている MySQL のホスト名'],
        'db_port' => ['label' => 'ポート番号', 'type' => 'number', 'autocomplete' => 'off', 'hint' => null],
        'db_database' => ['label' => 'データベース名', 'type' => 'text', 'autocomplete' => 'off', 'hint' => '事前に作成したデータベース（文字コード utf8mb4）'],
        'db_username' => ['label' => 'ユーザー名', 'type' => 'text', 'autocomplete' => 'off', 'hint' => null],
        'db_password' => ['label' => 'パスワード', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => '入力内容は設置フォルダの .env に保存されます'],
    ];
    $domainFields = [
        'main_domain' => ['label' => 'メイン', 'suggested' => $suggested['main']],
        'dashboard_domain' => ['label' => 'ダッシュボード', 'suggested' => $suggested['dashboard']],
        'api_domain' => ['label' => 'API', 'suggested' => $suggested['api']],
        'redirect_domain' => ['label' => 'リダイレクト確認', 'suggested' => $suggested['redirect']],
    ];
    $domainHasError = $errors->hasAny(array_keys($domainFields));
@endphp

<x-layouts.install title="chok.ooo のセットアップ">
    <p class="text-sm leading-relaxed text-text-secondary">
        データベースの接続情報を入力するだけでセットアップが完了します。cron の登録などは必要ありません。
    </p>

    <div class="mt-4 flex items-start gap-2.5 rounded-control border border-border-strong bg-primary-tint-soft px-4 py-3 text-[13px] leading-relaxed">
        <x-icon name="alert-circle" :size="16" class="mt-0.5 text-primary-dark" />
        <p>セットアップが完了するまで、この画面には誰でもアクセスできます。ファイルを設置したら、すぐに最後まで進めてください。</p>
    </div>

    <h2 class="mt-8 font-rounded text-base font-bold">動作環境</h2>
    <ul class="mt-3 divide-y divide-table-divider rounded-card border border-border">
        @foreach ($results as $result)
            @php
                [$icon, $colorClass] = match ($result->status) {
                    \App\Installer\RequirementStatus::Passed => ['check-circle', 'text-primary-dark'],
                    \App\Installer\RequirementStatus::Warning => ['alert-circle', 'text-warning'],
                    \App\Installer\RequirementStatus::Unknown => ['info', 'text-warning'],
                    \App\Installer\RequirementStatus::Failed => ['ban', 'text-danger'],
                };
            @endphp
            <li class="flex gap-3 px-4 py-3">
                <x-icon :name="$icon" :size="20" @class(['mt-0.5', $colorClass]) />
                <div class="min-w-0">
                    <p class="text-sm font-medium">
                        {{ $result->label }}
                        <span @class(['ml-1 text-xs font-bold', $colorClass])>{{ $result->status->label() }}</span>
                    </p>
                    @if ($result->detail)
                        <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">{{ $result->detail }}</p>
                    @endif
                    @if ($result->links !== [])
                        <ul class="mt-1.5 space-y-1 text-[13px]">
                            @foreach ($result->links as $url)
                                <li class="break-all">
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-primary-dark underline hover:text-primary-darker">
                                        {{ $url }}<span class="sr-only">（新しいタブで開く）</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if ($hasFailure)
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-danger" role="alert">NG の項目を解決してから、再度確認してください。</p>
            <x-button variant="secondary" size="sm" :href="route('install.show')">再確認する</x-button>
        </div>
    @else
        <form method="POST" action="{{ route('install.store') }}" class="mt-8 space-y-8">
            @csrf

            <fieldset>
                <legend class="font-rounded text-base font-bold">データベース（MySQL 8.0）</legend>
                <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">
                    サーバーの管理画面で作成したデータベースの接続情報を入力してください。テーブルは自動で作成します。
                </p>
                <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-[minmax(0,1fr)_140px]">
                    @foreach ($databaseFields as $name => $field)
                        <div @class(['sm:col-span-2' => ! in_array($name, ['db_host', 'db_port'], true)])>
                            <label for="{{ $name }}" class="block text-[13px] font-medium text-text-secondary">{{ $field['label'] }}</label>
                            <input
                                id="{{ $name }}"
                                name="{{ $name }}"
                                type="{{ $field['type'] }}"
                                autocomplete="{{ $field['autocomplete'] }}"
                                @if ($name !== 'db_password')
                                    required
                                    value="{{ old($name, $databaseDefaults[$name]) }}"
                                @endif
                                @if ($name === 'db_port') min="1" max="65535" inputmode="numeric" @endif
                                spellcheck="false"
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
            </fieldset>

            <details class="group rounded-card border border-border" @if ($domainHasError) open @endif>
                <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm">
                    <span class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-x-4 gap-y-1">
                        <span class="font-rounded font-bold">ドメイン</span>
                        <span class="text-[13px] text-text-secondary">
                            <span class="break-all">{{ old('main_domain', $suggested['main']) }}</span> ほか（自動で設定済み・変更する場合のみ開く）
                        </span>
                    </span>
                    <x-icon name="chevron-down" :size="18" class="shrink-0 text-text-secondary transition-transform group-open:rotate-180" />
                </summary>
                <div class="border-t border-border px-4 pt-4 pb-5">
                    <p class="text-[13px] leading-relaxed text-text-secondary">
                        4つのドメインは、すべてこのフォルダを公開するようにサーバーの管理画面で設定してください。https:// は付けずに入力します。
                    </p>
                    <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        @foreach ($domainFields as $name => $field)
                            <div>
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
                                    @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
                                >
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
                </div>
            </details>

            @if ($needsConfirmation)
                <div>
                    <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed">
                        <input
                            type="checkbox"
                            name="confirmed"
                            value="1"
                            required
                            class="mt-1 size-4 shrink-0 accent-primary-dark"
                            @error('confirmed') aria-invalid="true" aria-describedby="confirmed-error" @enderror
                        >
                        <span>「動作環境」のリンクをブラウザで開き、どれもファイルの中身が表示されないことを確認しました。</span>
                    </label>
                    <x-field-error name="confirmed" id="confirmed-error" />
                </div>
            @endif

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[13px] leading-relaxed text-text-secondary">完了後、この画面は使えなくなります。</p>
                <x-button type="submit">セットアップを完了する</x-button>
            </div>
        </form>
    @endif
</x-layouts.install>
