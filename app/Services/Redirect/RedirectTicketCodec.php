<?php

declare(strict_types=1);

namespace App\Services\Redirect;

use App\Models\ShortUrl;
use App\Support\ShortenerSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;

final class RedirectTicketCodec
{
    public function __construct(
        private readonly Encrypter $encrypter,
        private readonly ShortenerSettings $settings,
    ) {}

    public function encode(ShortUrl $link, ?string $referrerHost, bool $passwordVerified, CarbonImmutable $now): string
    {
        return $this->encrypter->encryptString(json_encode([
            'id' => $link->id,
            'at' => $now->getTimestamp(),
            'ref' => $referrerHost,
            'pw' => $passwordVerified,
            'nonce' => bin2hex(random_bytes(16)),
        ], JSON_THROW_ON_ERROR));
    }

    /** 改ざん・期限切れ・形式不正なら null */
    public function decode(mixed $token, CarbonImmutable $now): ?RedirectTicket
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode($this->encrypter->decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            Log::info('リダイレクト用チケットを復号できませんでした。');

            return null;
        }

        if (! is_array($payload)
            || ! is_int($payload['id'] ?? null)
            || ! is_int($payload['at'] ?? null)
            || ! is_bool($payload['pw'] ?? null)
            || ! is_string($payload['nonce'] ?? null)
            || ! (is_string($payload['ref'] ?? null) || ($payload['ref'] ?? null) === null)) {
            Log::warning('リダイレクト用チケットの形式が不正です。');

            return null;
        }

        $ageSeconds = $now->getTimestamp() - $payload['at'];
        if ($ageSeconds < 0 || $ageSeconds > $this->settings->redirectTicketTtlMinutes() * 60) {
            return null;
        }

        return new RedirectTicket($payload['id'], $payload['at'], $payload['ref'], $payload['pw'], $payload['nonce']);
    }

    /** 初回だけ true を返す（再読み込み等でアクセス数を重複して数えないため） */
    public function markUsed(RedirectTicket $ticket): bool
    {
        return Cache::add(
            'redirect-ticket:'.hash('sha256', $ticket->nonce),
            true,
            now()->addMinutes($this->settings->redirectTicketTtlMinutes() + 1),
        );
    }
}
