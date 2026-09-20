<?php

declare(strict_types=1);

namespace App\Services\GeoIp;

/**
 * 国判定に使うデータベースファイルの場所。
 * 手動で置いた MaxMind GeoLite2 を優先し、無ければ自動取得した DB-IP を使う。
 */
final readonly class GeoIpDatabase
{
    public function __construct(
        public string $manualPath,
        public string $automaticPath,
    ) {}

    /** 使えるデータベースが無ければ null */
    public function activePath(): ?string
    {
        return match (true) {
            $this->hasManualDatabase() => $this->manualPath,
            is_file($this->automaticPath) => $this->automaticPath,
            default => null,
        };
    }

    public function source(): ?GeoIpSource
    {
        return match ($this->activePath()) {
            null => null,
            $this->manualPath => GeoIpSource::MaxMind,
            default => GeoIpSource::DbIp,
        };
    }

    public function hasManualDatabase(): bool
    {
        return is_file($this->manualPath);
    }
}
