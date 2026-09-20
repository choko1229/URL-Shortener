{{--
    届いたお問い合わせ（requirements.md 4-2 管理者の特権）
    @var \App\ViewModels\ViewerData $viewer
    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\Inquiry> $inquiries
    @var int $unhandledCount
    @var string $discordContact
    @var string $timezone
--}}
<x-dashboard.admin-page :viewer="$viewer" title="お問い合わせ" description="お問い合わせページから届いた内容です。Discord の Webhook を設定していると、届いたときに通知します。">
    <section aria-labelledby="contact-setting-heading" class="rounded-card border border-border bg-white p-5 sm:p-6">
        <h2 id="contact-setting-heading" class="font-rounded text-[15px] font-bold">お問い合わせページに載せる Discord の連絡先</h2>
        <form method="POST" action="{{ route('dashboard.admin.inquiries.contact') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            @method('PUT')
            <div class="min-w-0 flex-1">
                <label for="discord-contact" class="sr-only">Discord の連絡先</label>
                <input
                    id="discord-contact"
                    name="discord_contact"
                    type="text"
                    maxlength="100"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="@your_name または サーバーの招待リンク"
                    value="{{ old('discord_contact', $discordContact) }}"
                    class="form-control"
                    aria-describedby="discord-contact-hint{{ $errors->has('discord_contact') ? ' discord-contact-error' : '' }}"
                    @error('discord_contact') aria-invalid="true" @enderror
                >
                <p id="discord-contact-hint" class="mt-1.5 text-xs text-text-secondary">入力すると、お問い合わせページに「Discord からもご連絡いただけます」として表示します。空にすると表示しません。</p>
                <x-field-error name="discord_contact" id="discord-contact-error" />
            </div>
            <x-button type="submit" size="sm" class="min-h-[52px]">保存する</x-button>
        </form>
    </section>

    <section aria-labelledby="inquiries-heading" class="overflow-hidden rounded-card border border-border bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-table-divider px-5 py-5 sm:px-6">
            <h2 id="inquiries-heading" class="font-rounded text-[15px] font-bold">届いたお問い合わせ</h2>
            <p class="text-[13px] text-text-secondary">未対応 {{ number_format($unhandledCount) }} 件</p>
        </div>

        @if ($inquiries->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-text-secondary">まだお問い合わせはありません。</p>
        @else
            <ul class="divide-y divide-table-divider">
                @foreach ($inquiries as $inquiry)
                    <li class="px-5 py-5 sm:px-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    #{{ $inquiry->id }} {{ $inquiry->senderLabel() }}
                                    @if ($inquiry->user)
                                        <span class="ml-1 rounded-full bg-primary-tint px-2 py-0.5 text-[11px] font-bold text-primary-dark">ログイン中の送信</span>
                                    @endif
                                    @unless ($inquiry->isHandled())
                                        <span class="ml-1 rounded-full bg-accent-warm/30 px-2 py-0.5 text-[11px] font-bold text-warning">未対応</span>
                                    @endunless
                                </p>
                                <p class="mt-1 text-xs text-text-secondary">
                                    {{ $inquiry->created_at?->setTimezone($timezone)->format('Y/m/d H:i') }}
                                    @if ($inquiry->reply_to)
                                        ・返信先: <span class="break-all">{{ $inquiry->reply_to }}</span>
                                    @else
                                        ・返信先の指定なし
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('dashboard.admin.inquiries.status', ['inquiry' => $inquiry->id]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="handled" value="{{ $inquiry->isHandled() ? '0' : '1' }}">
                                <x-button type="submit" :variant="$inquiry->isHandled() ? 'ghost' : 'secondary'" size="sm">
                                    {{ $inquiry->isHandled() ? '未対応に戻す' : '対応済みにする' }}
                                </x-button>
                            </form>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed whitespace-pre-wrap">{{ $inquiry->message }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{ $inquiries->links('partials.pagination') }}
</x-dashboard.admin-page>
