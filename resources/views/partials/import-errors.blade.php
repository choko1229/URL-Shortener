{{--
    CSV インポートで取り込めなかった理由の一覧（管理者の一括発行・発行履歴の移行で共用）
    @var \App\Services\ShortUrl\CsvImportResult|\App\Services\ShortUrl\Transfer\LinkTransferResult|null $result
--}}
@if ($result && $result->errors !== [])
    @php $shownErrors = array_slice($result->errors, 0, 100); @endphp
    <div class="mt-4 overflow-hidden rounded-control border border-danger/40" role="alert">
        <p class="bg-primary-tint-soft px-4 py-3 text-[13px] font-medium text-danger">
            @if ($result->rejected)
                ファイルを取り込めませんでした
            @else
                取り込めなかった行: {{ $result->skipped() }}行（{{ implode('・', array_map(static fn (int $line): string => $line.'行目', array_slice($result->failedLines(), 0, 30))) }}{{ $result->skipped() > 30 ? ' ほか' : '' }}）。直してから、その行だけを取り込み直してください。
            @endif
        </p>
        <div class="overflow-x-auto" role="region" aria-label="取り込めなかった理由" tabindex="0">
            <table class="w-full min-w-[640px] border-collapse text-left text-[13px]">
                <caption class="sr-only">取り込めなかった理由（行・列・入力された値・理由）</caption>
                <thead class="bg-table-header">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-xs font-medium text-text-secondary">行</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-xs font-medium text-text-secondary">列</th>
                        <th scope="col" class="px-4 py-2 text-xs font-medium text-text-secondary">入力された値</th>
                        <th scope="col" class="px-4 py-2 text-xs font-medium text-text-secondary">理由</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shownErrors as $error)
                        <tr class="border-t border-table-divider align-top">
                            <td class="whitespace-nowrap px-4 py-2.5 font-medium">{{ $error->line > 0 ? $error->line.'行目' : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 font-mono">{{ $error->column ?? '—' }}</td>
                            <td class="break-all px-4 py-2.5 font-mono text-text-secondary">{{ $error->displayValue() ?? '（空欄）' }}</td>
                            <td class="px-4 py-2.5">{{ $error->reason }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($result->errors) > count($shownErrors))
            <p class="border-t border-table-divider px-4 py-2 text-xs text-text-secondary">ほか {{ count($result->errors) - count($shownErrors) }}件</p>
        @endif
    </div>
@endif
