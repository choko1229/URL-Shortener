<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Support\ExternalServiceKeys;
use App\Support\ShortenerSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * reCAPTCHA v3 の検証（requirements.md 4-4）。未ログインの発行にのみ使う。
 * キーが未設定なら検証しない。Google 側の障害時は発行を止めない（レート制限・月間上限で抑止する）。
 */
final class RecaptchaVerifier
{
    public const ACTION = 'shorten';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly ExternalServiceKeys $keys,
        private readonly ShortenerSettings $settings,
    ) {}

    public function isEnabled(): bool
    {
        return $this->keys->recaptchaSiteKey() !== null && $this->keys->recaptchaSecretKey() !== null;
    }

    /** フォームに埋め込むサイトキー。無効なら null */
    public function siteKey(): ?string
    {
        return $this->isEnabled() ? $this->keys->recaptchaSiteKey() : null;
    }

    public function verify(?string $token, ?string $clientIp): bool
    {
        $secret = $this->keys->recaptchaSecretKey();

        if (! $this->isEnabled() || $secret === null) {
            return true;
        }

        if ($token === null || $token === '') {
            Log::notice('reCAPTCHA のトークンが送信されませんでした。');

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $clientIp,
                ]));
        } catch (ConnectionException $e) {
            Log::warning('reCAPTCHA の検証サーバーに接続できないため、検証せずに続行します。', ['exception' => $e::class]);

            return true;
        }

        if (! $response->successful()) {
            Log::warning('reCAPTCHA の検証サーバーがエラーを返したため、検証せずに続行します。', ['status' => $response->status()]);

            return true;
        }

        $score = (float) $response->json('score', 0);
        $passed = $response->json('success') === true
            && $response->json('action') === self::ACTION
            && $score >= $this->settings->recaptchaMinimumScore();

        if (! $passed) {
            Log::notice('reCAPTCHA の判定により発行を拒否しました。', [
                'score' => $score,
                'action' => $response->json('action'),
                'error_codes' => $response->json('error-codes'),
            ]);
        }

        return $passed;
    }
}
