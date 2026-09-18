<?php

declare(strict_types=1);

namespace App\Services\Redirect;

use App\Models\ShortUrl;
use App\Support\ShortenerSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * パスワード保護リンクのブルートフォース対策（requirements.md 2-5: 5 回誤入力で 15 分ロックアウト）。
 * 第三者がわざと誤入力してリンクを使えなくすることを防ぐため、「リンク × アクセス元 IP」単位で数える。
 */
final class PasswordAttemptGuard
{
    public function __construct(private readonly ShortenerSettings $settings) {}

    /** ロック中なら残り秒数、ロックされていなければ 0 */
    public function lockedSeconds(ShortUrl $link, string $clientIp): int
    {
        $key = $this->lockKey($link, $clientIp);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    /** 誤入力を記録し、残りの試行回数を返す（0 ならロックした） */
    public function recordFailure(ShortUrl $link, string $clientIp): int
    {
        $lockoutSeconds = $this->settings->passwordLockoutMinutes() * 60;
        $maxAttempts = $this->settings->passwordMaxAttempts();
        $failures = RateLimiter::hit($this->failureKey($link, $clientIp), $lockoutSeconds);

        if ($failures < $maxAttempts) {
            return $maxAttempts - $failures;
        }

        // ロックはロックした時点から数える
        RateLimiter::hit($this->lockKey($link, $clientIp), $lockoutSeconds);
        RateLimiter::clear($this->failureKey($link, $clientIp));

        Log::notice('パスワード保護リンクへの誤入力が上限に達したためロックしました。', ['short_url_id' => $link->id]);

        return 0;
    }

    public function clear(ShortUrl $link, string $clientIp): void
    {
        RateLimiter::clear($this->failureKey($link, $clientIp));
    }

    private function failureKey(ShortUrl $link, string $clientIp): string
    {
        return 'link-password:failures:'.$link->id.':'.hash('sha256', $clientIp);
    }

    private function lockKey(ShortUrl $link, string $clientIp): string
    {
        return 'link-password:lock:'.$link->id.':'.hash('sha256', $clientIp);
    }
}
