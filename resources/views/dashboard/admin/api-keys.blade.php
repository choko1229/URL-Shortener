{{--
    API キーの管理（requirements.md 5）
    @var \App\ViewModels\ViewerData $viewer
    @var \Illuminate\Support\Collection<int, \App\Models\ApiKey> $keys
    @var string|null $newToken
    @var string $apiBaseUrl
    @var list<string> $nameExamples
    @var string $docsUrl
    @var string $timezone
--}}
<x-dashboard.admin-page :viewer="$viewer" title="APIキー" description="管理者専用の API（自分が管理する他プロジェクトからの発行用）で使うキーです。第三者には公開しないでください。">
    @if ($newToken)
        <section aria-labelledby="new-key-heading" class="rounded-card bg-dark-panel p-5 text-white sm:p-6" role="status">
            <h2 id="new-key-heading" class="flex items-center gap-1.5 font-rounded text-[15px] font-bold text-accent-warm">
                <x-icon name="key" :size="16" />
                発行した API キー
            </h2>
            <p class="mt-1 text-xs leading-relaxed text-white/80">このキーは今しか表示されません。必ず控えてください（失くした場合は無効にして発行し直してください）。</p>
            <div class="mt-3 flex items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded-control bg-dark-panel-button px-3 py-2.5 font-mono text-sm">{{ $newToken }}</code>
                <x-copy-button :text="$newToken" label="API キーをコピー" variant="dark" />
            </div>
        </section>
    @endif

    <section aria-labelledby="create-key-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
        <h2 id="create-key-heading" class="font-rounded text-[15px] font-bold">API キーを発行</h2>
        <form method="POST" action="{{ route('dashboard.admin.api-keys.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            <div class="min-w-0 flex-1">
                <label for="key-name" class="block text-[13px] font-medium text-text-secondary">名前（用途）</label>
                <input id="key-name" name="name" type="text" required maxlength="64" placeholder="例: {{ $nameExamples[0] }}" value="{{ old('name') }}" class="form-control mt-2" aria-describedby="key-name-hint{{ $errors->has('name') ? ' key-name-error' : '' }}" @error('name') aria-invalid="true" @enderror>
                <p id="key-name-hint" class="mt-1.5 text-xs text-text-secondary">どこから使うキーかが後で分かる名前にします（例: {{ implode('、', $nameExamples) }}）。</p>
                <x-field-error name="name" id="key-name-error" />
            </div>
            <x-button type="submit" size="sm" class="min-h-[52px] sm:mt-[30px]">
                <x-icon name="plus" :size="16" />
                発行する
            </x-button>
        </form>
    </section>

    <section aria-labelledby="keys-heading" class="overflow-hidden rounded-card border border-border bg-white">
        <h2 id="keys-heading" class="border-b border-table-divider px-5 py-5 font-rounded text-[15px] font-bold sm:px-6">発行済みのキー</h2>
        @if ($keys->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-text-secondary">まだ発行されていません。</p>
        @else
            <div class="overflow-x-auto" role="region" aria-labelledby="keys-heading" tabindex="0">
                <table class="w-full min-w-[720px] border-collapse text-left text-[13px]">
                    <caption class="sr-only">発行済みの API キー</caption>
                    <thead class="bg-table-header">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">名前</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">キー</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">発行者</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">最終使用</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-right text-xs font-medium text-text-secondary">状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keys as $key)
                            <tr class="border-t border-table-divider">
                                <td class="px-6 py-3.5 font-medium">{{ $key->name }}</td>
                                <td class="px-6 py-3.5 font-mono text-text-secondary">{{ $key->key_prefix }}…</td>
                                <td class="px-6 py-3.5 text-text-secondary">{{ $key->user?->displayName() ?? '退会済みユーザー' }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5 text-text-secondary">{{ $key->last_used_at?->setTimezone($timezone)->format('Y/m/d H:i') ?? '未使用' }}</td>
                                <td class="px-6 py-2 text-right">
                                    @if ($key->isRevoked())
                                        <span class="text-text-secondary">無効（{{ $key->revoked_at?->setTimezone($timezone)->format('Y/m/d') }}）</span>
                                    @else
                                        <form method="POST" action="{{ route('dashboard.admin.api-keys.revoke', ['apiKey' => $key->id]) }}" data-confirm="API キー「{{ $key->name }}」を無効にしますか？このキーを使っている連携は動かなくなります。">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger-ghost" size="sm">無効にする</x-button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="api-usage-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
        <h2 id="api-usage-heading" class="font-rounded text-[15px] font-bold">使い方</h2>
        <p class="mt-2 text-sm text-text-secondary">リクエストヘッダー <code class="rounded bg-primary-tint-soft px-1">Authorization: Bearer （APIキー）</code> を付けて呼び出します。</p>
        <ul class="mt-3 space-y-1.5 font-mono text-[13px]">
            <li>POST {{ $apiBaseUrl }}/links</li>
            <li>GET {{ $apiBaseUrl }}/links</li>
            <li>GET {{ $apiBaseUrl }}/links/{コード}</li>
            <li>DELETE {{ $apiBaseUrl }}/links/{コード}</li>
        </ul>
        <p class="mt-3 text-xs text-text-secondary">
            詳しくは
            <a href="{{ $docsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-medium text-primary-dark hover:underline">
                ドキュメントの「API」
                <x-icon name="external-link" :size="13" />
            </a>
            を参照してください。
        </p>
    </section>
</x-dashboard.admin-page>
