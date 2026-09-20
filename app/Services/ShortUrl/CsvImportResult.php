<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

/** CSV インポート 1 回分の結果 */
final readonly class CsvImportResult
{
    /**
     * @param  int  $imported  発行できた件数
     * @param  array<int, string>  $errors  行番号（CSV の見出しを 1 行目とする）=> 理由
     */
    public function __construct(
        public int $imported,
        public array $errors,
    ) {}

    public static function failed(int $line, string $message): self
    {
        return new self(0, [$line => $message]);
    }

    public function skipped(): int
    {
        return count($this->errors);
    }

    public function message(): string
    {
        if ($this->imported === 0 && $this->errors === []) {
            return '取り込む行がありませんでした。';
        }

        $message = "{$this->imported}件の短縮URLを発行しました。";

        return $this->errors === [] ? $message : $message."{$this->skipped()}行は取り込めませんでした。";
    }
}
