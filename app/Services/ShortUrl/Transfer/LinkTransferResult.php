<?php

declare(strict_types=1);

namespace App\Services\ShortUrl\Transfer;

use App\Services\ShortUrl\CsvImportError;

/**
 * 移行用の CSV インポート 1 回分の結果。
 * 取り込めなかった理由の一覧（partials.import-errors）は CsvImportResult と同じ形で表示する
 */
final readonly class LinkTransferResult
{
    /**
     * @param  int  $imported  同じコードのまま取り込んだ件数
     * @param  int  $overwritten  自分の短縮URLを上書きした件数
     * @param  int  $reissued  重なっていたため新しいコードで発行した件数
     * @param  int  $skippedByChoice  重なっていたためスキップを選んだ件数
     * @param  list<CsvImportError>  $errors
     */
    public function __construct(
        public int $imported,
        public int $overwritten,
        public int $reissued,
        public int $skippedByChoice,
        public array $errors,
        public bool $rejected = false,
    ) {}

    public static function fromRejectedPlan(LinkTransferPlan $plan): self
    {
        return new self(0, 0, 0, 0, $plan->errors, rejected: true);
    }

    public static function rejected(string $reason): self
    {
        return new self(0, 0, 0, 0, [new CsvImportError(0, null, null, $reason)], rejected: true);
    }

    public function succeeded(): bool
    {
        return $this->imported + $this->overwritten + $this->reissued > 0;
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

    public function message(): string
    {
        if ($this->rejected) {
            return 'CSV を取り込めませんでした。'.$this->errors[0]->reason;
        }

        $parts = [];
        if ($this->imported > 0) {
            $parts[] = "{$this->imported}件を取り込みました";
        }
        if ($this->overwritten > 0) {
            $parts[] = "{$this->overwritten}件を上書きしました";
        }
        if ($this->reissued > 0) {
            $parts[] = "{$this->reissued}件を新しい短縮URLで発行しました";
        }
        if ($this->skippedByChoice > 0) {
            $parts[] = "{$this->skippedByChoice}件をスキップしました";
        }

        if ($parts === [] && $this->errors === []) {
            return '取り込む行がありませんでした。';
        }

        $message = $parts === [] ? '' : implode('・', $parts).'。';

        return $this->errors === [] ? $message : $message."{$this->skipped()}行は取り込めませんでした（下の一覧に理由があります）。";
    }
}
