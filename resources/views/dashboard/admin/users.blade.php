{{--
    ユーザー一覧と管理者への昇格（requirements.md 4-2）
    @var \App\ViewModels\ViewerData $viewer
    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\User> $users
    @var int|string|null $currentUserId
    @var string $timezone
--}}
<x-dashboard.admin-page :viewer="$viewer" title="ユーザー" description="Discord でログインしたユーザーの一覧です。管理者は予約語の使用・全URLの管理・APIキーの発行・アップデート管理ができます。">
    <section aria-labelledby="users-heading" class="overflow-hidden rounded-card border border-border bg-surface">
        <h2 id="users-heading" class="border-b border-table-divider px-5 py-5 font-rounded text-[15px] font-bold sm:px-6">ユーザー一覧</h2>
        <div class="overflow-x-auto" role="region" aria-labelledby="users-heading" tabindex="0">
            <table class="w-full min-w-[760px] border-collapse text-left text-[13px]">
                <caption class="sr-only">ユーザー一覧（管理者が先頭）</caption>
                <thead class="bg-table-header">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">ユーザー</th>
                        <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">権限</th>
                        <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">発行数</th>
                        <th scope="col" class="whitespace-nowrap px-6 py-3 text-xs font-medium text-text-secondary">最終ログイン</th>
                        <th scope="col" class="whitespace-nowrap px-6 py-3 text-right text-xs font-medium text-text-secondary">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        @php
                            $isAdmin = $user->isAdmin();
                        @endphp
                        <tr class="border-t border-table-divider">
                            <td class="px-6 py-3.5">
                                <span class="flex items-center gap-3">
                                    @if ($user->avatarUrl())
                                        <img src="{{ $user->avatarUrl() }}" alt="" width="36" height="36" class="size-9 shrink-0 rounded-full object-cover" referrerpolicy="no-referrer" loading="lazy">
                                    @else
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-warm font-rounded text-[13px] font-bold text-text-primary" aria-hidden="true">{{ mb_substr($user->displayName(), 0, 1) }}</span>
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ $user->displayName() }}</span>
                                        <span class="block text-xs text-text-secondary">{{ '@'.$user->username }}（ID: {{ $user->discord_id }}）</span>
                                    </span>
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[11px] font-bold',
                                    'bg-primary-tint text-primary-dark' => $isAdmin,
                                    'border border-border text-text-secondary' => ! $isAdmin,
                                ])>{{ $user->role->label() }}</span>
                            </td>
                            <td class="px-6 py-3.5 tabular-nums">{{ number_format($user->short_urls_count) }}</td>
                            <td class="whitespace-nowrap px-6 py-3.5 text-text-secondary">{{ $user->last_login_at?->setTimezone($timezone)->format('Y/m/d H:i') ?? '—' }}</td>
                            <td class="px-6 py-2 text-right">
                                <form
                                    method="POST"
                                    action="{{ route('dashboard.admin.users.role', ['user' => $user->id]) }}"
                                    data-confirm="{{ $user->displayName() }} を{{ $isAdmin ? 'メンバーに戻し' : '管理者にし' }}ますか？"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="role" value="{{ $isAdmin ? 'member' : 'admin' }}">
                                    <x-button type="submit" :variant="$isAdmin ? 'ghost' : 'secondary'" size="sm">
                                        {{ $isAdmin ? '管理者を解除' : '管理者にする' }}
                                        @if ((string) $user->id === (string) $currentUserId)
                                            <span class="sr-only">（あなた）</span>
                                        @endif
                                    </x-button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links('partials.pagination') }}
    </section>
</x-dashboard.admin-page>
