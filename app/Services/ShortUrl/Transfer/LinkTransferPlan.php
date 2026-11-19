<?php

declare(strict_types=1);

namespace App\Services\ShortUrl\Transfer;

use App\Services\ShortUrl\CsvImportError;

/**
 * CSV を読んで検証した結果（まだ取り込んでいない）。
 * 重なりがあると利用者の選択を待つため、キャッシュに保存できるよう配列に変換できるようにしている。
 */
final readonly class LinkTransferPlan
{
    /**
     * @param  list<array<string, mixed>>  $rows  取り込める行（LinkTransfer::normalize の形。line に CSV の行番号）
     * @param  array<int, LinkTransferConflict>  $conflicts  行番号ごとの重なり（rows に含まれる行のみ）
     * @param  list<CsvImportError>  $errors  取り込めない行の理由
     */
    public function __construct(
        public array $rows,
        public array $conflicts,
        public array $errors,
        public bool $rejected = false,
    ) {}

    public static function rejected(string $reason, int $line = 1): self
    {
        return new self([], [], [new CsvImportError($line, null, null, $reason)], rejected: true);
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'conflicts' => array_values(array_map(static fn (LinkTransferConflict $c): array => $c->toArray(), $this->conflicts)),
            'errors' => array_map(static fn (CsvImportError $e): array => [
                'line' => $e->line,
                'column' => $e->column,
                'value' => $e->value,
                'reason' => $e->reason,
            ], $this->errors),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $conflicts = [];
        foreach ((array) ($data['conflicts'] ?? []) as $conflict) {
            $conflict = LinkTransferConflict::fromArray((array) $conflict);
            $conflicts[$conflict->line] = $conflict;
        }

        $errors = array_values(array_map(
            static fn (array $e): CsvImportError => new CsvImportError((int) $e['line'], $e['column'] ?? null, $e['value'] ?? null, (string) $e['reason']),
            (array) ($data['errors'] ?? []),
        ));

        return new self(array_values((array) ($data['rows'] ?? [])), $conflicts, $errors);
    }
}
