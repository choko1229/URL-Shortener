{{--
    @var list<\App\Installer\RequirementResult> $results
    @var bool $hasFailure
    @var bool $needsConfirmation
--}}
<x-layouts.install :step="1" title="動作環境の確認">
    <p class="text-sm leading-relaxed text-text-secondary">
        chok.ooo を設置したサーバーが動作条件を満たしているか確認します。
    </p>

    <div class="mt-4 flex items-start gap-2.5 rounded-control border border-border-strong bg-primary-tint-soft px-4 py-3 text-[13px] leading-relaxed">
        <x-icon name="alert-circle" :size="16" class="mt-0.5 text-primary-dark" />
        <p>セットアップが完了するまで、この画面には誰でもアクセスできます。ファイルを設置したら、すぐに最後まで進めてください。</p>
    </div>

    <ul class="mt-5 divide-y divide-table-divider rounded-card border border-border">
        @foreach ($results as $result)
            @php
                [$icon, $colorClass] = match ($result->status) {
                    \App\Installer\RequirementStatus::Passed => ['check-circle', 'text-primary-dark'],
                    \App\Installer\RequirementStatus::Warning => ['alert-circle', 'text-warning'],
                    \App\Installer\RequirementStatus::Unknown => ['info', 'text-warning'],
                    \App\Installer\RequirementStatus::Failed => ['ban', 'text-danger'],
                };
            @endphp
            <li class="flex gap-3 px-4 py-3.5">
                <x-icon :name="$icon" :size="20" @class(['mt-0.5', $colorClass]) />
                <div class="min-w-0">
                    <p class="text-sm font-medium">
                        {{ $result->label }}
                        <span @class(['ml-1 text-xs font-bold', $colorClass])>{{ $result->status->label() }}</span>
                    </p>
                    @if ($result->detail)
                        <p class="mt-1 text-[13px] leading-relaxed text-text-secondary">{{ $result->detail }}</p>
                    @endif
                    @if ($result->links !== [])
                        <ul class="mt-1.5 space-y-1 text-[13px]">
                            @foreach ($result->links as $url)
                                <li class="break-all">
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-primary-dark underline hover:text-primary-darker">
                                        {{ $url }}<span class="sr-only">（新しいタブで開く）</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if ($hasFailure)
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-danger" role="alert">NG の項目を解決してから、再度確認してください。</p>
            <x-button variant="secondary" size="sm" :href="route('install.requirements')">再確認する</x-button>
        </div>
    @else
        <form method="POST" action="{{ route('install.requirements.confirm') }}" class="mt-6 space-y-4">
            @csrf

            @if ($needsConfirmation)
                <div>
                    <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed">
                        <input
                            type="checkbox"
                            name="confirmed"
                            value="1"
                            required
                            class="mt-1 size-4 shrink-0 accent-primary-dark"
                            @error('confirmed') aria-invalid="true" aria-describedby="confirmed-error" @enderror
                        >
                        <span>上のリンクをブラウザで開き、どれもファイルの中身が表示されないことを確認しました。</span>
                    </label>
                    <x-field-error name="confirmed" id="confirmed-error" />
                </div>
            @endif

            <div class="flex justify-end">
                <x-button type="submit">次へ進む</x-button>
            </div>
        </form>
    @endif
</x-layouts.install>
