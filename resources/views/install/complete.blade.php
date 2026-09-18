{{-- @var \App\Installer\SiteSettings $settings --}}
<x-layouts.install title="セットアップが完了しました">
    <div class="flex items-start gap-3">
        <div class="flex size-11 shrink-0 items-center justify-center rounded-control bg-primary-tint text-primary-dark">
            <x-icon name="check-circle" :size="22" />
        </div>
        <p class="text-sm leading-relaxed text-text-secondary">
            テーブルの作成と初期データの登録が終わりました。以下の URL から chok.ooo を利用できます。
        </p>
    </div>

    <dl class="mt-6 divide-y divide-table-divider rounded-card border border-border text-sm">
        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
            <dt class="w-32 shrink-0 text-text-secondary">トップページ</dt>
            <dd class="break-all font-medium">{{ $settings->baseUrl() }}</dd>
        </div>
        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
            <dt class="w-32 shrink-0 text-text-secondary">ダッシュボード</dt>
            <dd class="break-all font-medium">{{ $settings->dashboardUrl() }}</dd>
        </div>
        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
            <dt class="w-32 shrink-0 text-text-secondary">Discord ログイン</dt>
            <dd class="font-medium">{{ $settings->hasDiscordCredentials() ? '設定済み' : '未設定' }}</dd>
        </div>
    </dl>

    <h2 class="mt-8 font-rounded text-base font-bold">この後に行うこと</h2>
    <ul class="mt-3 list-disc space-y-2 pl-5 text-[13px] leading-relaxed text-text-secondary">
        <li>設置フォルダの <code class="rounded bg-primary-tint-soft px-1">.env</code> にはデータベースのパスワードと暗号鍵（APP_KEY）が保存されています。バックアップを取り、他人に渡さないでください。APP_KEY を失うと暗号化した設定を復元できません。</li>
        <li>Discord ログイン機能の実装後、最初にログインしたユーザーが管理者になります。</li>
        <li>設定をやり直す場合は <code class="rounded bg-primary-tint-soft px-1">storage/app/private/installed.json</code> を削除すると、セットアップ画面が再び開きます。</li>
    </ul>

    <div class="mt-8 flex justify-end">
        <x-button :href="$settings->baseUrl()">トップページを開く</x-button>
    </div>
</x-layouts.install>
