{{-- @var array{db_host: string, db_port: string, db_database: string, db_username: string} $defaults --}}
@php
    $fields = [
        'db_host' => ['label' => 'ホスト名', 'type' => 'text', 'autocomplete' => 'off', 'hint' => 'サーバーの管理画面に記載されている MySQL のホスト名'],
        'db_port' => ['label' => 'ポート番号', 'type' => 'number', 'autocomplete' => 'off', 'hint' => null],
        'db_database' => ['label' => 'データベース名', 'type' => 'text', 'autocomplete' => 'off', 'hint' => '事前に作成したデータベース（文字コード utf8mb4）'],
        'db_username' => ['label' => 'ユーザー名', 'type' => 'text', 'autocomplete' => 'off', 'hint' => null],
        'db_password' => ['label' => 'パスワード', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => '入力内容は .env に保存されます'],
    ];
@endphp

<x-layouts.install :step="2" title="データベース">
    <p class="text-sm leading-relaxed text-text-secondary">
        MySQL 8.0 の接続情報を入力してください。接続を確認できたら次へ進みます。
    </p>

    <form method="POST" action="{{ route('install.database.store') }}" class="mt-6 space-y-5">
        @csrf

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-[minmax(0,1fr)_140px]">
            @foreach ($fields as $name => $field)
                <div @class(['sm:col-span-2' => ! in_array($name, ['db_host', 'db_port'], true)])>
                    <label for="{{ $name }}" class="block text-[13px] font-medium text-text-secondary">{{ $field['label'] }}</label>
                    <input
                        id="{{ $name }}"
                        name="{{ $name }}"
                        type="{{ $field['type'] }}"
                        autocomplete="{{ $field['autocomplete'] }}"
                        @if ($name !== 'db_password')
                            required
                            value="{{ old($name, $defaults[$name]) }}"
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

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('install.requirements') }}" class="text-sm text-primary-dark hover:text-primary-darker">動作環境の確認に戻る</a>
            <x-button type="submit">接続を確認して次へ</x-button>
        </div>
    </form>
</x-layouts.install>
