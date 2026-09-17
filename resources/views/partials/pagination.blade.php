{{-- @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator --}}
@if ($paginator->hasPages())
    <nav aria-label="発行履歴のページ送り" class="flex flex-col gap-3 border-t border-table-divider px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <p class="text-xs text-text-secondary">
            {{ number_format($paginator->firstItem()) }}〜{{ number_format($paginator->lastItem()) }} 件目 / 全 {{ number_format($paginator->total()) }} 件
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="inline-flex min-h-11 items-center rounded-control border-[1.5px] border-border px-4 text-sm text-text-muted" aria-disabled="true">前へ</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center rounded-control border-[1.5px] border-primary px-4 text-sm font-medium text-text-primary hover:bg-primary-tint">前へ</a>
            @endif

            <span class="inline-flex min-h-11 items-center px-2 text-sm text-text-secondary">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center rounded-control border-[1.5px] border-primary px-4 text-sm font-medium text-text-primary hover:bg-primary-tint">次へ</a>
            @else
                <span class="inline-flex min-h-11 items-center rounded-control border-[1.5px] border-border px-4 text-sm text-text-muted" aria-disabled="true">次へ</span>
            @endif
        </div>
    </nav>
@endif
