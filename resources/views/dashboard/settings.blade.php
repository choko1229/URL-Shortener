{{--
    アカウント設定と退会（requirements.md 4-4）
    @var \App\ViewModels\ViewerData $viewer
    @var \App\Models\User $user
    @var int $linkCount
    @var bool $isLastAdmin
    @var string $timezone
--}}
<x-dashboard.page :viewer="$viewer" title="設定">
    <section aria-labelledby="account-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
        <h2 id="account-heading" class="font-rounded text-[15px] font-bold">アカウント</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-text-secondary">表示名</dt><dd class="mt-0.5">{{ $user->displayName() }}</dd></div>
            <div><dt class="text-xs text-text-secondary">Discord ユーザー名</dt><dd class="mt-0.5">{{ '@'.$user->username }}</dd></div>
            <div><dt class="text-xs text-text-secondary">権限</dt><dd class="mt-0.5">{{ $user->role->label() }}</dd></div>
            <div><dt class="text-xs text-text-secondary">登録日</dt><dd class="mt-0.5">{{ $user->created_at?->setTimezone($timezone)->format('Y/m/d') }}</dd></div>
        </dl>
        <p class="mt-4 text-xs text-text-secondary">表示名とアイコンは Discord の設定に合わせて、ログインのたびに更新されます。</p>
    </section>

    <section aria-labelledby="withdraw-heading" class="rounded-card border border-danger/30 bg-surface p-5 sm:p-6">
        <h2 id="withdraw-heading" class="font-rounded text-[15px] font-bold text-danger">退会</h2>
        @if ($isLastAdmin)
            <p class="mt-3 text-sm leading-relaxed text-text-secondary">
                あなたは唯一の管理者のため退会できません。先に<a href="{{ route('dashboard.admin.users') }}" class="text-primary-dark underline hover:text-primary-darker">ユーザー</a>画面で別のユーザーを管理者にしてください。
            </p>
        @else
            <p class="mt-3 text-sm leading-relaxed text-text-secondary">
                退会すると Discord の情報を削除し、ログインできなくなります。発行した短縮URL（{{ number_format($linkCount) }}件）は、削除しない限りそのまま使えます。
            </p>
            <form method="POST" action="{{ route('dashboard.account.destroy') }}" class="mt-4 space-y-3" data-confirm="本当に退会しますか？この操作は取り消せません。">
                @csrf
                @method('DELETE')
                <label class="flex cursor-pointer items-start gap-3 text-sm">
                    <input type="checkbox" name="delete_links" value="1" class="mt-1 size-4 shrink-0 accent-danger">
                    <span>発行した短縮URLもすべて削除する（コードは欠番になり、再利用されません）</span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 text-sm">
                    <input type="checkbox" name="confirm" value="1" required class="mt-1 size-4 shrink-0 accent-danger" @error('confirm') aria-invalid="true" aria-describedby="confirm-error" @enderror>
                    <span>退会すると元に戻せないことを理解しました</span>
                </label>
                <x-field-error name="confirm" id="confirm-error" />
                <x-button type="submit" variant="danger-ghost" size="sm" class="border border-danger/40">退会する</x-button>
            </form>
        @endif
    </section>
</x-dashboard.page>
