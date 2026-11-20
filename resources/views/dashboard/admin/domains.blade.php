{{--
    短縮URLに使うドメインの管理
    @var \App\ViewModels\ViewerData $viewer
    @var string $primaryHost
    @var list<\App\Models\ShortDomain> $domains
    @var array<string, int> $counts ドメイン（空文字 = メインドメイン）ごとの短縮URLの件数（削除済みを含む）
    @var list<\App\Enums\ShortDomainMode> $modes
--}}
<x-dashboard.admin-page :viewer="$viewer" title="ドメイン" description="短縮URLに使うドメインを追加できます。短縮コードはドメインごとに別に数えるので、go.example.com/sale と {{ $primaryHost }}/sale は別のリンクにできます。">
    <section aria-labelledby="add-domain-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <h2 id="add-domain-heading" class="font-rounded text-[15px] font-bold">ドメインを追加</h2>
        <form method="POST" action="{{ route('dashboard.admin.domains.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            <div class="min-w-0 flex-1">
                <label for="host" class="block text-[13px] font-medium text-text-secondary">ドメイン</label>
                <input
                    id="host"
                    name="host"
                    type="text"
                    required
                    maxlength="253"
                    placeholder="go.example.com"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    value="{{ old('host') }}"
                    class="form-control mt-2"
                    aria-describedby="host-hint{{ $errors->has('host') ? ' host-error' : '' }}"
                    @error('host') aria-invalid="true" @enderror
                >
                <p id="host-hint" class="mt-1.5 text-xs text-text-secondary">https:// やパスは付けずに入力します。</p>
                <x-field-error name="host" id="host-error" />
            </div>
            <div>
                <label for="mode" class="block text-[13px] font-medium text-text-secondary">発行できる場所</label>
                <select id="mode" name="mode" class="form-control mt-2">
                    @foreach ($modes as $mode)
                        <option value="{{ $mode->value }}" @selected(old('mode', 'page') === $mode->value)>{{ $mode->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" size="sm" class="min-h-[52px] sm:mt-[30px]">
                <x-icon name="plus" :size="16" />
                追加する
            </x-button>
        </form>
        <ul class="mt-4 list-disc space-y-1 pl-5 text-xs leading-relaxed text-text-secondary">
            <li>「ページと API」: 発行フォーム（トップページ・ダッシュボード）でドメインを選べるようになり、API でも使えます。</li>
            <li>「API のみ」: 発行フォームには出さず、API で <code class="font-mono">domain</code> を指定したときだけ発行できます。</li>
            <li>追加したあと、DNS と Web サーバー（バーチャルホスト・SSL 証明書）で、このドメインをこのアプリに向けてください。</li>
        </ul>
    </section>

    <section aria-labelledby="domains-heading" class="overflow-hidden rounded-card border border-border bg-surface">
        <h2 id="domains-heading" class="border-b border-table-divider px-5 py-4 font-rounded text-[15px] font-bold sm:px-6">使えるドメイン</h2>
        <ul class="divide-y divide-table-divider">
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <p class="break-all font-mono text-sm font-bold">{{ $primaryHost }}</p>
                    <p class="mt-0.5 text-xs text-text-secondary">メインドメイン・ページと API・短縮URL {{ number_format($counts[''] ?? 0) }} 件</p>
                </div>
                <p class="text-xs text-text-secondary">.env の SHORTENER_SHORT_URL_BASE で設定</p>
            </li>
            @foreach ($domains as $domain)
                @php $count = (int) ($counts[$domain->host] ?? 0); @endphp
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="break-all font-mono text-sm font-bold">{{ $domain->host }}</p>
                        <p class="mt-0.5 text-xs text-text-secondary">短縮URL {{ number_format($count) }} 件</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('dashboard.admin.domains.update', ['shortDomain' => $domain->id]) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <label for="mode-{{ $domain->id }}" class="sr-only">{{ $domain->host }} の発行できる場所</label>
                            <select id="mode-{{ $domain->id }}" name="mode" class="form-control min-h-11 py-2 text-sm">
                                @foreach ($modes as $mode)
                                    <option value="{{ $mode->value }}" @selected($domain->mode === $mode)>{{ $mode->label() }}</option>
                                @endforeach
                            </select>
                            <x-button type="submit" variant="secondary" size="sm">変更</x-button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.admin.domains.destroy', ['shortDomain' => $domain->id]) }}" data-confirm="{{ $domain->host }} を削除しますか？">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger-ghost" size="sm" :disabled="$count > 0" :title="$count > 0 ? '短縮URLがあるため削除できません' : null">
                                <x-icon name="trash" :size="16" />
                                削除
                            </x-button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($domains === [])
            <p class="border-t border-table-divider px-5 py-4 text-sm text-text-secondary sm:px-6">追加したドメインはまだありません。</p>
        @endif
    </section>
</x-dashboard.admin-page>
