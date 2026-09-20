<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Services\GeoIp\GeoIpUpdateResult;
use App\Services\Update\UpdateOutcome;

/** 定期処理で実行した各処理の結果（実行しなかった処理は null） */
final readonly class PeriodicTaskReport
{
    public function __construct(
        public ?UpdateOutcome $update = null,
        public ?GeoIpUpdateResult $geoIp = null,
    ) {}

    public function hasFailure(): bool
    {
        return ($this->update?->isFailure() ?? false) || ($this->geoIp !== null && ! $this->geoIp->successful);
    }

    /** @return list<array{message: string, failed: bool}> */
    public function messages(): array
    {
        $messages = [];

        if ($this->geoIp !== null) {
            $messages[] = ['message' => $this->geoIp->message, 'failed' => ! $this->geoIp->successful];
        }

        if ($this->update !== null) {
            $messages[] = ['message' => $this->update->message, 'failed' => $this->update->isFailure()];
        }

        return $messages;
    }
}
