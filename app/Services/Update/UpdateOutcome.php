<?php

declare(strict_types=1);

namespace App\Services\Update;

use App\Enums\UpdateRunStatus;

/** 自動アップデート 1 回分の結果 */
final readonly class UpdateOutcome
{
    private function __construct(
        // null なら更新を試みていない（スキップ・最新）
        public ?UpdateRunStatus $status,
        public string $message,
    ) {}

    public static function skipped(string $message): self
    {
        return new self(null, $message);
    }

    public static function finished(UpdateRunStatus $status, string $message): self
    {
        return new self($status, $message);
    }

    public function isFailure(): bool
    {
        return $this->status !== null && $this->status !== UpdateRunStatus::Succeeded;
    }
}
