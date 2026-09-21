<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

/** CSV インポート 1 回分の結果 */
final readonly class CsvImportResult
{
    /**
     * @param  int  $imported  発行できた件数
     * @param  list<CsvImportError>  $errors  取り込めなかった理由（1 行に複数ある場合もある）
     * @param  bool  $rejected  ファイル全体を取り込まなかった（見出しが無い・行数が多すぎるなど）
     */
    public function __construct(
        public int $imported,
        public array $errors,
        public bool $rejected = false,
    ) {}

    /** ファイル全体を取り込めない場合 */
    public static function rejected(string $reason, int $line = 1, ?string $column = null): self
    {
        return new self(0, [new CsvImportError($line, $column, null, $reason)], rejected: true);
    }

    /** 取り込めなかった行の数（1 行に複数の問題があっても 1 と数える） */
    public function skipped(): int
    {
        return count($this->failedLines());
    }

    /** @return list<int> */
    public function failedLines(): array
    {
        return array_values(array_unique(array_map(static fn (CsvImportError $error): int => $error->line, $this->errors)));
    }

    /** @return list<CsvImportError> */
    public function errorsOn(int $line): array
    {
        return array_values(array_filter($this->errors, static fn (CsvImportError $error): bool => $error->line === $line));
    }

    public function message(): string
    {
        if ($this->rejected) {
            return 'CSV を取り込めませんでした。'.$this->errors[0]->reason;
        }

        if ($this->imported === 0 && $this->errors === []) {
            return '取り込む行がありませんでした。';
        }

        $message = "{$this->imported}件の短縮URLを発行しました。";

        return $this->errors === [] ? $message : $message."{$this->skipped()}行は取り込めませんでした（下の一覧に理由があります）。";
    }
}
