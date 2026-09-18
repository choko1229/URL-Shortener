<?php

declare(strict_types=1);

namespace App\Services\Update;

/** vYY.MM.patch 形式のバージョン（requirements.md 7-1。例: v26.9.0） */
final readonly class Version
{
    private const PATTERN = '/\Av?(\d{1,4})\.(\d{1,2})\.(\d{1,6})\z/';

    private function __construct(
        public int $year,
        public int $month,
        public int $patch,
    ) {}

    public static function tryParse(?string $value): ?self
    {
        if ($value === null || preg_match(self::PATTERN, trim($value), $matches) !== 1) {
            return null;
        }

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    public function isNewerThan(self $other): bool
    {
        return [$this->year, $this->month, $this->patch] > [$other->year, $other->month, $other->patch];
    }

    public function toString(): string
    {
        return "v{$this->year}.{$this->month}.{$this->patch}";
    }
}
