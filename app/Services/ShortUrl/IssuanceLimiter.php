<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Models\ShortUrl;
use App\Models\User;
use App\Support\ShortenerSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 発行の利用制限（requirements.md 4-3）。
 * - 月間上限: 表示タイムゾーンの暦月で数え、論理削除分も含める（削除→再発行による回避を防ぐ）
 * - レート制限: 発行に成功した回数だけを数える（入力ミスで締め出さないため）
 */
final class IssuanceLimiter
{
    public function __construct(private readonly ShortenerSettings $settings) {}

    /** @throws IssuanceException */
    public function ensureWithinLimits(?User $user, string $clientIpHash, CarbonImmutable $now): void
    {
        $key = $this->rateLimitKey($user, $clientIpHash);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts($user))) {
            $seconds = RateLimiter::availableIn($key);
            $wait = $seconds >= 60 ? (int) ceil($seconds / 60).'分' : $seconds.'秒';

            throw new IssuanceException("短時間に発行できる回数を超えました。{$wait}ほど待ってから、もう一度お試しください。");
        }

        $limit = $user !== null ? $this->settings->memberMonthlyLimit() : $this->settings->guestMonthlyLimit();

        if ($this->issuedThisMonth($user, $clientIpHash, $now) >= $limit) {
            throw new IssuanceException($user !== null
                ? "今月の発行上限（{$limit}件）に達しました。"
                : "ログインしていない場合の今月の発行上限（{$limit}件）に達しました。ログインすると上限が増えます。");
        }
    }

    public function recordIssued(?User $user, string $clientIpHash): void
    {
        RateLimiter::hit($this->rateLimitKey($user, $clientIpHash), $this->decaySeconds($user));
    }

    public function issuedThisMonth(?User $user, string $clientIpHash, CarbonImmutable $now): int
    {
        $localNow = $now->setTimezone($this->settings->displayTimezone());
        $query = ShortUrl::withTrashed()->whereBetween('created_at', [
            $localNow->startOfMonth()->utc(),
            $localNow->endOfMonth()->utc(),
        ]);

        $user !== null
            ? $query->where('user_id', $user->id)
            : $query->whereNull('user_id')->where('creator_ip_hash', $clientIpHash);

        return $query->count();
    }

    private function rateLimitKey(?User $user, string $clientIpHash): string
    {
        return $user !== null ? "issue:user:{$user->id}" : "issue:ip:{$clientIpHash}";
    }

    private function maxAttempts(?User $user): int
    {
        return $user !== null ? $this->settings->memberRateLimitPerMinute() : 1;
    }

    private function decaySeconds(?User $user): int
    {
        return $user !== null ? 60 : $this->settings->guestRateLimitIntervalMinutes() * 60;
    }
}
