<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\SlugType;
use App\Models\ReservedWord;
use App\Models\ShortUrl;
use App\Models\User;
use App\Support\ShortenerSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** 短縮URLの発行（requirements.md 2 章・4-3） */
final class ShortUrlIssuer
{
    private const MAX_RANDOM_CODE_ATTEMPTS = 10;

    private const DELETION_TOKEN_LENGTH = 40;

    public function __construct(
        private readonly ShortenerSettings $settings,
        private readonly IssuanceLimiter $limiter,
        private readonly SlugGenerator $slugGenerator,
    ) {}

    /** @throws IssuanceException */
    public function issue(ShortUrlDraft $draft, ?User $user, string $clientIp): IssuedShortUrl
    {
        $now = CarbonImmutable::now();
        $clientIpHash = self::hashClientIp($clientIp);

        $this->limiter->ensureWithinLimits($user, $clientIpHash, $now);

        $deletionToken = $user === null ? Str::random(self::DELETION_TOKEN_LENGTH) : null;

        $link = new ShortUrl;
        $link->original_url = $draft->originalUrl;
        $link->expires_at = $draft->expiresAt($now, $this->settings->displayTimezone());
        $link->password_hash = $draft->password !== null ? Hash::make($draft->password) : null;
        $link->user_id = $user?->id;
        // 未ログイン発行のみ、月間上限の判定用に IP のハッシュを残す
        $link->creator_ip_hash = $user === null ? $clientIpHash : null;
        $link->deletion_token_hash = $deletionToken !== null ? hash('sha256', $deletionToken) : null;
        $link->click_count = 0;

        $draft->customSlug !== null
            ? $this->saveWithCustomSlug($link, $draft->customSlug, $user)
            : $this->saveWithRandomCode($link);

        $this->limiter->recordIssued($user, $clientIpHash);

        Log::info('短縮URLを発行しました。', [
            'short_url_id' => $link->id,
            'user_id' => $user?->id,
            'slug_type' => $link->slug_type->value,
        ]);

        return new IssuedShortUrl($link, $deletionToken);
    }

    /** 月間上限の判定に使う IP のハッシュ（生の IP は保存しない） */
    public static function hashClientIp(string $clientIp): string
    {
        return hash_hmac('sha256', $clientIp, (string) config('app.key'));
    }

    /** @throws IssuanceException */
    private function saveWithCustomSlug(ShortUrl $link, string $slug, ?User $user): void
    {
        if (! ($user?->isAdmin() ?? false) && $this->isReserved($slug)) {
            throw new IssuanceException('このカスタムスラッグは予約されているため使えません。', 'custom_slug');
        }

        // 同じ値（削除済みを含む）と、大文字小文字だけが違うランダムコードは使えない
        $taken = ShortUrl::withTrashed()
            ->where(static function ($query) use ($slug): void {
                $query->where('slug', $slug)->orWhere(static function ($query) use ($slug): void {
                    $query->where('slug_normalized', mb_strtolower($slug))->where('slug_type', SlugType::Random->value);
                });
            })
            ->exists();

        if ($taken) {
            throw new IssuanceException('このカスタムスラッグはすでに使われています。', 'custom_slug');
        }

        $link->slug = $slug;
        $link->slug_type = SlugType::Custom;

        try {
            DB::transaction(static fn () => $link->save());
        } catch (UniqueConstraintViolationException) {
            throw new IssuanceException('このカスタムスラッグはすでに使われています。', 'custom_slug');
        }
    }

    /** @throws IssuanceException */
    private function saveWithRandomCode(ShortUrl $link): void
    {
        $length = $this->settings->randomCodeLength();

        for ($attempt = 1; $attempt <= self::MAX_RANDOM_CODE_ATTEMPTS; $attempt++) {
            $code = $this->slugGenerator->generate($length);

            // ランダムコードは大文字小文字を区別せずに重複を判定する（requirements.md 2-1）
            $collides = $this->isReserved($code)
                || ShortUrl::withTrashed()->where('slug_normalized', mb_strtolower($code))->exists();

            if ($collides) {
                continue;
            }

            $link->slug = $code;
            $link->slug_type = SlugType::Random;

            try {
                DB::transaction(static fn () => $link->save());

                return;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        Log::error('ランダムコードの生成が規定回数内に完了しませんでした。', ['attempts' => self::MAX_RANDOM_CODE_ATTEMPTS]);

        throw new IssuanceException('短縮URLを発行できませんでした。時間をおいて再度お試しください。');
    }

    private function isReserved(string $slug): bool
    {
        return ReservedWord::query()->where('word', mb_strtolower($slug))->exists();
    }
}
