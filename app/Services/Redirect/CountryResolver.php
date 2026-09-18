<?php

declare(strict_types=1);

namespace App\Services\Redirect;

use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use Throwable;

/**
 * IP アドレスから国コードを判定する（MaxMind GeoLite2 Country）。
 * データベースファイルが無い・読めない場合は null を返し、アクセス記録自体は続ける。
 * 訪問者の IP は外部に送らず、保存もしない。
 */
final class CountryResolver
{
    private ?Reader $reader = null;

    private bool $unavailable = false;

    public function __construct(private readonly string $databasePath) {}

    public function countryCode(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $reader = $this->reader();
        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->get($ip);
        } catch (Throwable $e) {
            Log::warning('GeoIP データベースから国を判定できませんでした。', ['exception' => $e::class]);

            return null;
        }

        $code = is_array($record) && is_array($record['country'] ?? null) ? ($record['country']['iso_code'] ?? null) : null;

        return is_string($code) && preg_match('/\A[A-Z]{2}\z/', $code) === 1 ? $code : null;
    }

    private function reader(): ?Reader
    {
        if ($this->reader !== null || $this->unavailable) {
            return $this->reader;
        }

        if (! is_file($this->databasePath)) {
            $this->unavailable = true;

            return null;
        }

        try {
            return $this->reader = new Reader($this->databasePath);
        } catch (Throwable $e) {
            $this->unavailable = true;
            Log::error('GeoIP データベースを開けませんでした。', ['path' => $this->databasePath, 'exception' => $e::class]);

            return null;
        }
    }
}
