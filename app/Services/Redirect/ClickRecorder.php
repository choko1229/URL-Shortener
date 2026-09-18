<?php

declare(strict_types=1);

namespace App\Services\Redirect;

use App\Models\ShortUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * アクセス記録（requirements.md 2-6: クリック数・リファラ・国・デバイス種別）。
 * 記録に失敗しても転送は止めない。
 */
final class ClickRecorder
{
    public function __construct(
        private readonly DeviceDetector $devices,
        private readonly CountryResolver $countries,
    ) {}

    public function record(ShortUrl $link, RedirectTicket $ticket, ?string $clientIp, ?string $userAgent): void
    {
        try {
            DB::transaction(function () use ($link, $ticket, $clientIp, $userAgent): void {
                $link->clicks()->create([
                    'referrer_host' => $ticket->referrerHost,
                    'country_code' => $this->countries->countryCode($clientIp),
                    'device_type' => $this->devices->detect($userAgent),
                    'clicked_at' => now(),
                ]);

                ShortUrl::withTrashed()->whereKey($link->id)->increment('click_count', 1, ['last_clicked_at' => now()]);
            });
        } catch (Throwable $e) {
            Log::error('アクセスを記録できませんでした。', [
                'short_url_id' => $link->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
