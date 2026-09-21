{{--
    アップデート管理（requirements.md 7 章）
    @var \App\ViewModels\ViewerData $viewer
    @var string|null $currentVersion
    @var string $strategy
    @var bool $enabled
    @var string $repository
    @var bool $hasToken
    @var bool $hasWebhook
    @var \Illuminate\Support\Collection<int, \App\Models\UpdateRun> $runs
    @var list<string> $backups
    @var string $timezone
    @var \Carbon\CarbonImmutable|null $lastRunAt
    @var string|null $lastTrigger
    @var bool $cronActive
    @var bool $hasPhpCli
--}}
<x-dashboard.admin-page :viewer="$viewer" title="アップデート" description="1日1回（午前4時以降の最初のアクセス時）GitHub Releases を確認し、新しいリリースがあればバックアップを取ってから自動で更新します。失敗した場合は自動で元に戻し、Discord に通知します。">
    <section aria-labelledby="status-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <h2 id="status-heading" class="font-rounded text-[15px] font-bold">現在の状態</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs text-text-secondary">バージョン</dt><dd class="mt-0.5 font-medium">{{ $currentVersion ?? '不明（開発中のコード）' }}</dd></div>
            <div><dt class="text-xs text-text-secondary">更新方法</dt><dd class="mt-0.5">{{ $strategy === 'git' ? 'Git（git checkout と composer install）' : '配布用 zip の入れ替え' }}</dd></div>
            <div><dt class="text-xs text-text-secondary">自動アップデート</dt><dd class="mt-0.5">{{ $enabled ? '有効' : '無効' }}</dd></div>
            <div>
                <dt class="text-xs text-text-secondary">前回の定期処理</dt>
                <dd class="mt-0.5">
                    @if ($lastRunAt)
                        {{ $lastRunAt->setTimezone($timezone)->format('Y/m/d H:i') }}
                        <span class="text-text-secondary">（{{ $lastTrigger === 'cron' ? 'cron' : 'アクセス時' }}）</span>
                    @else
                        まだ実行されていません
                    @endif
                </dd>
            </div>
        </dl>
        <div class="mt-5 flex flex-wrap gap-2.5">
            <form method="POST" action="{{ route('dashboard.admin.updates.check') }}">
                @csrf
                <x-button type="submit" variant="secondary" size="sm">
                    <x-icon name="refresh" :size="16" />
                    最新リリースを確認
                </x-button>
            </form>
            <form method="POST" action="{{ route('dashboard.admin.updates.run') }}" data-confirm="最新リリースがあれば今すぐ更新します。先にバックアップを取り、失敗した場合は自動で元に戻します。完了まで数分かかることがあります。実行しますか？">
                @csrf
                <x-button type="submit" size="sm" :disabled="$currentVersion === null">今すぐ更新する</x-button>
            </form>
            <form method="POST" action="{{ route('dashboard.admin.updates.test-notification') }}">
                @csrf
                <x-button type="submit" variant="ghost" size="sm" :disabled="! $hasWebhook">テスト通知を送る</x-button>
            </form>
        </div>
        <p class="mt-4 text-xs leading-relaxed text-text-secondary">
            @if ($cronActive)
                サーバーの cron から定期処理が実行されています。
            @else
                定期処理はサイトへのアクセスをきっかけに自動で実行されるため、cron の登録は不要です（アクセスが少ないサイトでは実行が遅れることがあります。登録する場合は <code class="rounded bg-primary-tint-soft px-1">* * * * * cd （設置フォルダ） &amp;&amp; php artisan schedule:run</code>）。
            @endif
            @unless ($hasPhpCli)
                サーバーで PHP（CLI）を起動できないため、更新時のマイグレーション等は Web の処理の中で実行します。
            @endunless
            「今すぐ更新する」は、自動アップデートが無効でも実行します。完了まで数分かかることがあるため、実行中はこのページを閉じないでください。
            SSH が使える場合は <code class="rounded bg-primary-tint-soft px-1">php artisan app:update --manual</code> でも実行できます。
        </p>
    </section>

    {{-- 通常は変更しないため閉じておく（入力エラーのときだけ開く） --}}
    <details class="group rounded-card border border-border bg-surface" @if ($errors->hasAny(['github_token', 'discord_webhook_url'])) open @endif>
        <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <span class="flex flex-wrap items-baseline gap-x-2">
                <span class="font-rounded text-[15px] font-bold">詳細設定</span>
                <span class="text-[13px] text-text-secondary">自動アップデートの有効・無効、GitHub のトークン、Discord の通知先（通常は変更不要）</span>
            </span>
            <x-icon name="chevron-down" :size="18" class="shrink-0 text-text-secondary transition-transform group-open:rotate-180" />
        </summary>
        <div class="border-t border-border px-5 pt-4 pb-5 sm:px-6">
            <p class="text-xs leading-relaxed text-text-secondary">
                更新元は <code class="rounded bg-primary-tint-soft px-1">{{ $repository }}</code> です。フォークして自分のリリースから更新する場合だけ、<code class="rounded bg-primary-tint-soft px-1">.env</code> の <code class="rounded bg-primary-tint-soft px-1">SHORTENER_UPDATE_REPOSITORY</code> で変更してください。
            </p>
            <form method="POST" action="{{ route('dashboard.admin.updates.settings') }}" class="mt-4 max-w-2xl space-y-5">
                @csrf
                @method('PUT')

                <label class="flex cursor-pointer items-start gap-3 text-sm">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled)) class="mt-1 size-4 shrink-0 accent-primary-dark">
                    <span>自動アップデートを有効にする</span>
                </label>

                <div>
                    <label for="github-token" class="block text-[13px] font-medium text-text-secondary">GitHub のトークン（任意・{{ $hasToken ? '設定済み。変更する場合のみ入力' : '未設定' }}）</label>
                    <input id="github-token" name="github_token" type="password" autocomplete="new-password" spellcheck="false" class="form-control mt-2" aria-describedby="github-token-hint{{ $errors->has('github_token') ? ' github-token-error' : '' }}" @error('github_token') aria-invalid="true" @enderror>
                    <p id="github-token-hint" class="mt-1.5 text-xs text-text-secondary">公開リポジトリから更新する場合は不要です。非公開リポジトリのときは、Contents を読み取れる Fine-grained トークンを設定してください（APP_KEY で暗号化して保存します）。</p>
                    <x-field-error name="github_token" id="github-token-error" />
                    @if ($hasToken)
                        <label class="mt-2 flex cursor-pointer items-center gap-2 text-xs text-text-secondary">
                            <input type="checkbox" name="clear_github_token" value="1" class="size-4 accent-danger">
                            トークンを削除する
                        </label>
                    @endif
                </div>

                <div>
                    <label for="discord-webhook-url" class="block text-[13px] font-medium text-text-secondary">通知先の Discord Webhook URL（{{ $hasWebhook ? '設定済み。変更する場合のみ入力' : '未設定' }}）</label>
                    <input id="discord-webhook-url" name="discord_webhook_url" type="password" autocomplete="off" spellcheck="false" class="form-control mt-2" @error('discord_webhook_url') aria-invalid="true" aria-describedby="discord-webhook-url-error" @enderror>
                    <x-field-error name="discord_webhook_url" id="discord-webhook-url-error" />
                    @if ($hasWebhook)
                        <label class="mt-2 flex cursor-pointer items-center gap-2 text-xs text-text-secondary">
                            <input type="checkbox" name="clear_discord_webhook_url" value="1" class="size-4 accent-danger">
                            Webhook URL を削除する
                        </label>
                    @endif
                </div>

                <x-button type="submit" size="sm">保存する</x-button>
            </form>
        </div>
    </details>

    <section aria-labelledby="runs-heading" class="overflow-hidden rounded-card border border-border bg-surface">
        <h2 id="runs-heading" class="border-b border-table-divider px-5 py-5 font-rounded text-[15px] font-bold sm:px-6">実行履歴（直近10件）</h2>
        @if ($runs->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-text-secondary">まだ更新は行われていません。</p>
        @else
            <div class="overflow-x-auto" role="region" aria-labelledby="runs-heading" tabindex="0">
                <table class="w-full min-w-[720px] border-collapse text-left text-[13px]">
                    <caption class="sr-only">自動アップデートの実行履歴</caption>
                    <thead class="bg-table-header">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">開始</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">バージョン</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">結果</th>
                            <th scope="col" class="px-6 py-3 text-xs font-medium text-text-secondary">詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            @php
                                $color = match ($run->status) {
                                    \App\Enums\UpdateRunStatus::Succeeded => 'text-primary-dark',
                                    \App\Enums\UpdateRunStatus::Running => 'text-text-secondary',
                                    default => 'text-danger',
                                };
                            @endphp
                            <tr class="border-t border-table-divider align-top">
                                <td class="whitespace-nowrap px-6 py-3.5 text-text-secondary">{{ $run->started_at->setTimezone($timezone)->format('Y/m/d H:i') }}</td>
                                <td class="whitespace-nowrap px-6 py-3.5">{{ $run->from_version }} → {{ $run->to_version }}</td>
                                <td @class(['whitespace-nowrap px-6 py-3.5 font-medium', $color])>{{ $run->status->label() }}</td>
                                <td class="px-6 py-3.5 text-text-secondary">{{ $run->message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="backups-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <h2 id="backups-heading" class="font-rounded text-[15px] font-bold">バックアップ（直近3世代）</h2>
        @if ($backups === [])
            <p class="mt-3 text-sm text-text-secondary">まだありません。</p>
        @else
            <ul class="mt-3 space-y-1 font-mono text-[13px] text-text-secondary">
                @foreach ($backups as $backup)
                    <li>storage/app/private/backups/{{ $backup }}</li>
                @endforeach
            </ul>
        @endif
    </section>
</x-dashboard.admin-page>
