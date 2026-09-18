{{--
    @var \App\Installer\SiteSettings $settings
    @var string|null $databaseWarning
--}}
<x-layouts.install title="セットアップが完了しました">
    <div class="flex items-start gap-3">
        <div class="flex size-11 shrink-0 items-center justify-center rounded-control bg-primary-tint text-primary-dark">
            <x-icon name="check-circle" :size="22" />
        </div>
        <p class="text-sm leading-relaxed text-text-secondary">
            テーブルの作成と初期データの登録が終わりました。最後に、ダッシュボードで Discord ログインを設定して管理者としてログインしてください。
        </p>
    </div>

    @if ($databaseWarning)
        <p class="mt-4 flex items-start gap-2 text-[13px] text-warning">
            <x-icon name="alert-circle" :size="16" class="mt-0.5" />
            {{ $databaseWarning }}
        </p>
    @endif

    <dl class="mt-6 divide-y divide-table-divider rounded-card border border-border text-sm">
        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
            <dt class="w-32 shrink-0 text-text-secondary">トップページ</dt>
            <dd class="break-all font-medium">{{ $settings->baseUrl() }}</dd>
        </div>
        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
            <dt class="w-32 shrink-0 text-text-secondary">ダッシュボード</dt>
            <dd class="break-all font-medium">{{ $settings->dashboardUrl() }}</dd>
        </div>
    </dl>

    <h2 class="mt-8 font-rounded text-base font-bold">この後に行うこと</h2>
    <ol class="mt-3 list-decimal space-y-2 pl-5 text-[13px] leading-relaxed text-text-secondary">
        <li>下のボタンからダッシュボードを開くと、Discord ログインの設定画面が表示されます。画面の案内に従って Client ID と Client Secret を入力し、そのままログインしてください。<strong class="font-medium text-text-primary">最初にログインした人が管理者</strong>になります。</li>
        <li>悪意URLチェック（Google Safe Browsing）と reCAPTCHA は、管理者メニューの「外部サービス」で後から設定できます（任意）。</li>
        <li>自動アップデートなどの定期処理は、サイトへのアクセスをきっかけに 1 日 1 回自動で実行されます。cron の登録は不要です。</li>
    </ol>

    <h2 class="mt-8 font-rounded text-base font-bold">大切な情報</h2>
    <ul class="mt-3 list-disc space-y-2 pl-5 text-[13px] leading-relaxed text-text-secondary">
        <li>設置フォルダの <code class="rounded bg-primary-tint-soft px-1">.env</code> にはデータベースのパスワードと暗号鍵（APP_KEY）が保存されています。バックアップを取り、他人に渡さないでください。APP_KEY を失うと暗号化した設定を復元できません。</li>
        <li>設定をやり直す場合は <code class="rounded bg-primary-tint-soft px-1">storage/app/private/installed.json</code> を削除すると、セットアップ画面が再び開きます。</li>
    </ul>

    <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
        <x-button variant="secondary" :href="$settings->baseUrl()">トップページを開く</x-button>
        <x-button :href="$settings->dashboardUrl().'/login'">ダッシュボードで Discord ログインを設定する</x-button>
    </div>
</x-layouts.install>
