<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LinkSortColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** 短縮URL一覧の並び順（?sort=clicks&dir=desc）。未指定・不正な値は発行日の新しい順 */
final readonly class LinkSort
{
    public function __construct(
        public LinkSortColumn $column = LinkSortColumn::Created,
        public bool $descending = true,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $column = LinkSortColumn::tryFrom((string) $request->query('sort', '')) ?? LinkSortColumn::Created;
        $direction = (string) $request->query('dir', '');

        return new self($column, match ($direction) {
            'asc' => false,
            'desc' => true,
            default => $column->defaultDescending(),
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        $direction = $this->descending ? 'desc' : 'asc';

        match ($this->column) {
            LinkSortColumn::Created => null,
            LinkSortColumn::Slug => $query->orderBy('slug', $direction),
            LinkSortColumn::Clicks => $query->orderBy('click_count', $direction),
            // 無期限（NULL）は「いちばん先の期限」として扱う（期限の近い順では最後、遠い順では最初）
            LinkSortColumn::Expires => $query
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END '.$direction)
                ->orderBy('expires_at', $direction),
        };

        // 同じ値の中では発行日の順に並べ、ページをまたいでも順番が揺れないようにする
        return $query->orderBy('id', $this->column === LinkSortColumn::Created ? $direction : 'desc');
    }

    /**
     * 列見出しのリンクに付けるクエリ。表示中の列ならば向きを反転し、ページは 1 ページ目に戻す
     *
     * @return array{sort: string, dir: string, page: null}
     */
    public function queryFor(LinkSortColumn $column): array
    {
        $descending = $column === $this->column ? ! $this->descending : $column->defaultDescending();

        return ['sort' => $column->value, 'dir' => $descending ? 'desc' : 'asc', 'page' => null];
    }

    /** th の aria-sort の値 */
    public function ariaSort(LinkSortColumn $column): string
    {
        if ($column !== $this->column) {
            return 'none';
        }

        return $this->descending ? 'descending' : 'ascending';
    }

    /** 読み上げ用の並び順の説明（例: クリック数の多い順） */
    public function directionLabel(): string
    {
        return match ($this->column) {
            LinkSortColumn::Created => $this->descending ? '発行日の新しい順' : '発行日の古い順',
            LinkSortColumn::Slug => $this->descending ? '短縮URLの Z→A 順' : '短縮URLの A→Z 順',
            LinkSortColumn::Clicks => $this->descending ? 'クリック数の多い順' : 'クリック数の少ない順',
            LinkSortColumn::Expires => $this->descending ? '有効期限の遠い順' : '有効期限の近い順',
        };
    }
}
