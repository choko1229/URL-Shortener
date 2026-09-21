<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

/** CSV インポートで取り込めなかった理由 1 件（どの行の、どの列の、どの値が、なぜ） */
final readonly class CsvImportError
{
    private const MAX_VALUE_LENGTH = 80;

    public function __construct(
        // 見出しを 1 行目とした行番号
        public int $line,
        // 問題のある列（url・slug など）。行やファイル全体の問題なら null
        public ?string $column,
        public ?string $value,
        public string $reason,
    ) {}

    /** 画面に出す入力値（長い URL は途中を省く） */
    public function displayValue(): ?string
    {
        if ($this->value === null || $this->value === '') {
            return null;
        }

        return mb_strlen($this->value) > self::MAX_VALUE_LENGTH
            ? mb_substr($this->value, 0, self::MAX_VALUE_LENGTH - 1).'…'
            : $this->value;
    }
}
