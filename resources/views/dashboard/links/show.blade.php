{{--
    短縮URLの詳細（requirements.md 2-4 編集・削除、2-6 統計、6 QRコード）
    @var \App\ViewModels\ViewerData $viewer
    @var \App\ViewModels\LinkStatsData $stats
    @var bool $canEdit
    @var int $slugMinLength
    @var int $slugMaxLength
    @var bool $showGeoIpAttribution
    @var \App\ViewModels\AdminLinkEditData|null $adminEdit
--}}
@php
    $link = $stats->link;
    $dailyMax = $stats->dailyMax();
    $breakdowns = [
        'referrers' => ['title' => 'リファラ（流入元）', 'rows' => $stats->referrers],
        'countries' => ['title' => '国', 'rows' => $stats->countries],
        'devices' => ['title' => 'デバイス', 'rows' => $stats->devices],
    ];
@endphp

<x-layouts.dashboard :viewer="$viewer" :title="$link->displayUrl.' の詳細'">
    <div class="mx-auto flex max-w-[1280px] flex-col gap-6 px-4 pb-12 pt-6 sm:gap-7 sm:px-8 sm:pt-8 lg:px-12">
        <div>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard.home') }}" class="text-sm text-primary-dark hover:text-primary-darker">一覧に戻る</a>
            <h1 class="mt-2 flex flex-wrap items-center gap-2 font-rounded text-section font-bold">
                <span class="break-all">{{ $link->displayUrl }}</span>
                <x-link-status :status="$link->status" :label="$link->expiryLabel" />
            </h1>
            <p class="mt-1 break-all text-sm text-text-secondary">{{ $stats->originalUrl }}</p>
        </div>

        <x-flash-messages />

        <section aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="sr-only">概要</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5">
                <div class="rounded-card border border-border bg-surface px-6 py-[22px]">
                    <dt class="text-[13px] text-text-secondary">総クリック数</dt>
                    <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($stats->totalClicks) }}</dd>
                </div>
                <div class="rounded-card border border-border bg-surface px-6 py-[22px]">
                    <dt class="text-[13px] text-text-secondary">直近{{ $stats->recentDays }}日のクリック数</dt>
                    <dd class="mt-2.5 font-rounded text-stat font-extrabold">{{ number_format($stats->recentClicks) }}</dd>
                </div>
                <div class="rounded-card border border-border bg-surface px-6 py-[22px]">
                    <dt class="text-[13px] text-text-secondary">最終クリック</dt>
                    <dd class="mt-2.5 font-rounded text-xl font-bold">{{ $stats->lastClickedAt?->format('Y/m/d H:i') ?? 'まだありません' }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="daily-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
            <h2 id="daily-heading" class="font-rounded text-[15px] font-bold">日別クリック数（直近{{ $stats->recentDays }}日）</h2>

            {{-- グラフは視覚用。数値は下の表で確認できる --}}
            <div class="mt-5" aria-hidden="true">
                <div class="flex h-40 items-end gap-[2px] border-b border-border-strong">
                    @foreach ($stats->daily as $day)
                        <div class="group relative flex h-full min-w-0 flex-1 items-end">
                            <div
                                class="w-full rounded-t-[4px] bg-primary-dark transition-opacity group-hover:opacity-80"
                                style="height: {{ $day['count'] > 0 ? max(2, round($day['count'] / $dailyMax * 100, 1)) : 0 }}%"
                            ></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-control bg-dark-panel px-2 py-1 text-xs text-white group-hover:block">
                                {{ $day['label'] }}: {{ number_format($day['count']) }}件
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex justify-between text-[11px] text-text-secondary">
                    <span>{{ $stats->daily[0]['label'] ?? '' }}</span>
                    <span>{{ $stats->daily[array_key_last($stats->daily)]['label'] ?? '' }}</span>
                </div>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer text-primary-dark hover:text-primary-darker">表で見る</summary>
                <div class="mt-3 max-h-72 overflow-y-auto">
                    <table class="w-full border-collapse text-left text-[13px]">
                        <caption class="sr-only">日別クリック数</caption>
                        <thead class="bg-table-header">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-xs font-medium text-text-secondary">日付</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-text-secondary">クリック数</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_reverse($stats->daily) as $day)
                                <tr class="border-t border-table-divider">
                                    <td class="px-4 py-1.5">{{ $day['date'] }}</td>
                                    <td class="px-4 py-1.5 text-right tabular-nums">{{ number_format($day['count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            @foreach ($breakdowns as $key => $breakdown)
                @php
                    $max = max(1, ...array_map(static fn (array $row): int => $row['count'], $breakdown['rows'] ?: [['count' => 1]]));
                @endphp
                <section aria-labelledby="{{ $key }}-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
                    <h2 id="{{ $key }}-heading" class="font-rounded text-[15px] font-bold">{{ $breakdown['title'] }}</h2>
                    @if ($breakdown['rows'] === [])
                        <p class="mt-4 text-sm text-text-secondary">まだ記録がありません。</p>
                    @else
                        <table class="mt-4 w-full border-collapse text-left text-[13px]">
                            <caption class="sr-only">{{ $breakdown['title'] }}ごとのクリック数（上位10件）</caption>
                            <thead class="sr-only">
                                <tr><th scope="col">項目</th><th scope="col">クリック数</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($breakdown['rows'] as $row)
                                    <tr>
                                        <td class="py-1.5 pr-3">
                                            <span class="block truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                                            <span class="mt-1 block h-1.5 rounded-full bg-primary-tint" aria-hidden="true">
                                                <span class="block h-full rounded-full bg-primary-dark" style="width: {{ round($row['count'] / $max * 100, 1) }}%"></span>
                                            </span>
                                        </td>
                                        <td class="w-16 py-1.5 text-right align-top tabular-nums">{{ number_format($row['count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if ($key === 'countries' && $showGeoIpAttribution)
                        <p class="mt-4 text-[11px] text-text-secondary">
                            <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer" class="underline hover:text-primary-dark">IP Geolocation by DB-IP</a>
                        </p>
                    @endif
                </section>
            @endforeach
        </div>

        <section aria-labelledby="manage-heading" class="rounded-card border border-border bg-surface p-5 sm:p-6">
            <h2 id="manage-heading" class="font-rounded text-[15px] font-bold">管理</h2>
            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-xs text-text-secondary">発行者</dt><dd class="mt-0.5">{{ $stats->ownerLabel }}</dd></div>
                <div><dt class="text-xs text-text-secondary">発行日時</dt><dd class="mt-0.5">{{ $stats->createdAt->format('Y/m/d H:i') }}</dd></div>
                <div><dt class="text-xs text-text-secondary">パスワード保護</dt><dd class="mt-0.5">{{ $link->isPasswordProtected ? 'あり' : 'なし' }}</dd></div>
            </dl>

            @unless ($link->isDeleted())
                <div class="mt-5 flex flex-wrap gap-2.5">
                    <x-copy-button :text="$link->shortUrl" :label="$link->displayUrl.' をコピー'" variant="ghost" />
                    <x-button
                        variant="secondary"
                        size="sm"
                        aria-haspopup="dialog"
                        data-qr-open
                        :data-qr-src="route('main.short-link.qr', ['code' => $link->slug, 'format' => 'svg'])"
                        :data-qr-png="route('main.short-link.qr', ['code' => $link->slug, 'format' => 'png'])"
                        :data-qr-label="$link->displayUrl"
                    >
                        <x-icon name="qr-code" :size="16" />
                        QRコード
                    </x-button>
                </div>
            @endunless

            @if ($canEdit && ! $link->isDeleted())
                <div class="mt-6 max-w-xl" data-preview-group data-preview-summary="link-preview-summary">
                    <p class="text-[13px] font-medium text-text-secondary">
                        共有時のカード（Discord や X に貼ったときの表示）: <span id="link-preview-summary" class="font-normal"></span>
                    </p>
                    <form method="POST" action="{{ route('dashboard.links.preview', ['shortUrl' => $link->id]) }}" class="mt-2 space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="space-y-2">
                            @foreach (\App\Enums\PreviewMode::cases() as $mode)
                                <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                                    <input
                                        type="radio"
                                        name="preview_mode"
                                        value="{{ $mode->value }}"
                                        @checked(old('preview_mode', $stats->previewMode->value) === $mode->value)
                                        data-preview-mode
                                        data-label="{{ $mode->label() }}"
                                        class="mt-1 size-4 shrink-0 accent-primary-dark"
                                    >
                                    <span>
                                        <span class="font-medium">{{ $mode->label() }}</span>
                                        <span class="block text-xs text-text-secondary">{{ $mode->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-field-error name="preview_mode" id="preview-mode-error" />

                        <div class="space-y-4" data-preview-custom @unless (old('preview_mode', $stats->previewMode->value) === \App\Enums\PreviewMode::Custom->value) hidden @endunless>
                            <div>
                                <label for="preview-title" class="block text-[13px] font-medium text-text-secondary">カードのタイトル</label>
                                <input id="preview-title" name="preview_title" type="text" maxlength="120" value="{{ old('preview_title', $stats->previewTitle) }}" class="form-control mt-2" @error('preview_title') aria-invalid="true" aria-describedby="preview-title-error" @enderror>
                                <x-field-error name="preview_title" id="preview-title-error" />
                            </div>
                            <div>
                                <label for="preview-description" class="block text-[13px] font-medium text-text-secondary">カードの説明（任意）</label>
                                <input id="preview-description" name="preview_description" type="text" maxlength="300" value="{{ old('preview_description', $stats->previewDescription) }}" class="form-control mt-2" @error('preview_description') aria-invalid="true" aria-describedby="preview-description-error" @enderror>
                                <x-field-error name="preview_description" id="preview-description-error" />
                            </div>
                            <div>
                                <label for="preview-image" class="block text-[13px] font-medium text-text-secondary">カードの画像URL（任意）</label>
                                <input id="preview-image" name="preview_image_url" type="url" maxlength="2048" value="{{ old('preview_image_url', $stats->previewImageUrl) }}" class="form-control mt-2" @error('preview_image_url') aria-invalid="true" aria-describedby="preview-image-error" @enderror>
                                <x-field-error name="preview_image_url" id="preview-image-error" />
                            </div>
                        </div>

                        @if ($link->isPasswordProtected)
                            <p class="text-xs leading-relaxed text-text-secondary">パスワード保護つきのため、設定にかかわらず転送先は表示されません（サービス名のカードになります）。</p>
                        @endif

                        <x-button type="submit" size="sm" variant="secondary">カードの設定を保存</x-button>
                    </form>
                </div>
            @endif

            @if ($canEdit)
                <form method="POST" action="{{ route('dashboard.links.slug', ['shortUrl' => $link->id]) }}" class="mt-6 max-w-xl space-y-2" data-confirm="カスタムスラッグを変更しますか？以前の短縮URLは使えなくなり、再利用もできません。">
                    @csrf
                    @method('PATCH')
                    <label for="custom-slug" class="block text-[13px] font-medium text-text-secondary">カスタムスラッグを変更</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            id="custom-slug"
                            name="custom_slug"
                            type="text"
                            required
                            minlength="{{ $slugMinLength }}"
                            maxlength="{{ $slugMaxLength }}"
                            pattern="[A-Za-z0-9_\-]+"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            value="{{ old('custom_slug', $link->isCustomSlug ? $link->slug : '') }}"
                            class="form-control"
                            aria-describedby="custom-slug-hint{{ $errors->has('custom_slug') ? ' custom-slug-error' : '' }}"
                            @error('custom_slug') aria-invalid="true" @enderror
                        >
                        <x-button type="submit" size="sm">変更する</x-button>
                    </div>
                    <p id="custom-slug-hint" class="text-xs text-text-secondary">
                        変更できるのはカスタムスラッグのみです（元URLは変更できません）。以前の短縮URLは使えなくなり、再利用もできません。
                    </p>
                    <x-field-error name="custom_slug" id="custom-slug-error" />
                </form>

                <form method="POST" action="{{ route('dashboard.links.destroy', ['shortUrl' => $link->id]) }}" class="mt-6" data-confirm="{{ $link->displayUrl }} を削除しますか？削除したコードは再利用できません。">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger-ghost" size="sm" class="border border-danger/40">
                        <x-icon name="trash" :size="16" />
                        この短縮URLを削除
                    </x-button>
                </form>
            @endif
        </section>

        @if ($adminEdit)
            @php $expiryChoice = old('expiry', $adminEdit->expiresAtLocal === null ? 'never' : 'custom'); @endphp
            <section id="admin-edit" aria-labelledby="admin-edit-heading" class="scroll-mt-6 rounded-card border border-border bg-surface p-5 sm:p-6">
                <h2 id="admin-edit-heading" class="font-rounded text-[15px] font-bold">管理者による編集</h2>
                <p class="mt-1 text-[13px] text-text-secondary">元URL・有効期限・発行者を変更できます。短縮URL（{{ $link->displayUrl }}）はそのままです。</p>

                <form method="POST" action="{{ route('dashboard.admin.links.update', ['shortUrl' => $adminEdit->linkId]) }}" class="mt-4 max-w-xl space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="admin-original-url" class="block text-[13px] font-medium text-text-secondary">元URL（移動先）</label>
                        <input
                            id="admin-original-url"
                            name="original_url"
                            type="url"
                            required
                            maxlength="2048"
                            value="{{ old('original_url', $adminEdit->originalUrl) }}"
                            class="form-control mt-2"
                            @error('original_url') aria-invalid="true" aria-describedby="admin-original-url-error" @enderror
                        >
                        <x-field-error name="original_url" id="admin-original-url-error" />
                    </div>

                    <fieldset>
                        <legend class="text-[13px] font-medium text-text-secondary">有効期限</legend>
                        <div class="mt-2 space-y-2 text-sm">
                            <label class="flex cursor-pointer items-center gap-2.5">
                                <input type="radio" name="expiry" value="never" @checked($expiryChoice === 'never') class="size-4 accent-primary-dark">
                                無期限
                            </label>
                            <label class="flex cursor-pointer flex-wrap items-center gap-2.5">
                                <input type="radio" name="expiry" value="custom" @checked($expiryChoice === 'custom') class="size-4 accent-primary-dark">
                                日時を指定
                                <input
                                    type="datetime-local"
                                    name="expires_at"
                                    step="60"
                                    value="{{ old('expires_at', $adminEdit->expiresAtLocal) }}"
                                    class="form-control w-auto py-2"
                                    aria-label="有効期限の日時"
                                    aria-describedby="admin-expires-hint"
                                    @error('expires_at') aria-invalid="true" @enderror
                                >
                            </label>
                        </div>
                        <p id="admin-expires-hint" class="mt-1.5 text-xs text-text-secondary">日時は {{ $adminEdit->timezone }} です。過去の日時にすると、削除せずに期限切れにできます。</p>
                        <x-field-error name="expires_at" />
                    </fieldset>

                    <div>
                        <label for="admin-owner" class="block text-[13px] font-medium text-text-secondary">発行者</label>
                        @php $currentOwner = (string) old('user_id', (string) $adminEdit->userId); @endphp
                        <select id="admin-owner" name="user_id" class="form-control mt-2" @error('user_id') aria-invalid="true" aria-describedby="admin-owner-error" @enderror>
                            <option value="" @selected($currentOwner === '')>未ログインで発行（管理者だけが管理できます）</option>
                            @foreach ($adminEdit->users as $userId => $label)
                                <option value="{{ $userId }}" @selected($currentOwner === (string) $userId)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-text-secondary">ユーザーに移すと、その人のダッシュボードに表示され、統計の確認や削除ができるようになります（月間の発行数には数えません）。</p>
                        <x-field-error name="user_id" id="admin-owner-error" />
                    </div>

                    <x-button type="submit" size="sm">保存する</x-button>
                </form>
            </section>
        @endif
    </div>

    @include('partials.qr-dialog')
</x-layouts.dashboard>
