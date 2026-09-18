<?php

declare(strict_types=1);

namespace App\Installer;

final readonly class DatabaseTestResult
{
    private function __construct(
        public bool $successful,
        public ?string $serverVersion,
        public ?string $errorMessage,
        public ?string $warning,
    ) {}

    public static function succeeded(string $serverVersion, ?string $warning = null): self
    {
        return new self(true, $serverVersion, null, $warning);
    }

    public static function failed(string $errorMessage): self
    {
        return new self(false, null, $errorMessage, null);
    }
}
