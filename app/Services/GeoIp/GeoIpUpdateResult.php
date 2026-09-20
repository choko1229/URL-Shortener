<?php

declare(strict_types=1);

namespace App\Services\GeoIp;

final readonly class GeoIpUpdateResult
{
    private function __construct(
        public bool $successful,
        public string $message,
    ) {}

    public static function updated(string $release): self
    {
        return new self(true, "国判定のデータベースを {$release} 版に更新しました。");
    }

    public static function skipped(string $message): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
