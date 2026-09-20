<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\PreviewMode;
use App\Enums\SlugType;
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
        private readonly SlugAvailability $availability,
    ) {}

    /**
     * @param  bool  $enforceLimits  false なら月間上限・レート制限を適用しない（管理者専用 API: requirements.md 5）
     *
     * @throws IssuanceException
     */
    public function issue(ShortUrlDraft $draft, ?User $user, string $clientIp, bool $enforceLimits = true): IssuedShortUrl
    {
        $now = CarbonImmutable::now();
        $clientIpHash = self::hashClientIp($clientIp);

        if ($enforceLimits) {
            $this->limiter->ensureWithinLimits($user, $clientIpHash, $now);
        }

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
        $link->preview_mode = $draft->previewMode;
        $link->preview_title = $draft->previewMode === PreviewMode::Custom ? $draft->previewTitle : null;
        $link->preview_description = $draft->previewMode === PreviewMode::Custom ? $draft->previewDescription : null;
        $link->preview_image_url = $draft->previewMode === PreviewMode::Custom ? $draft->previewImageUrl : null;

        $draft->customSlug !== null
            ? $this->saveWithCustomSlug($link, $draft->customSlug, $user)
            : $this->saveWithRandomCode($link);

        if ($enforceLimits) {
            $this->limiter->recordIssued($user, $clientIpHash);
        }

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
    public function ensureCustomSlugAvailable(string $slug, ?User $user): void
    {
        if (! ($user?->isAdmin() ?? false) && $this->availability->isReserved($slug)) {
            throw new IssuanceException('このカスタムスラッグは予約されているため使えません。', 'custom_slug');
        }

        if ($this->availability->isTakenForCustom($slug)) {
            throw new IssuanceException('このカスタムスラッグはすでに使われています。', 'custom_slug');
        }
    }

    /** @throws IssuanceException */
    private function saveWithCustomSlug(ShortUrl $link, string $slug, ?User $user): void
    {
        $this->ensureCustomSlugAvailable($slug, $user);

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

            if ($this->availability->isReserved($code) || $this->availability->isTakenForRandom($code)) {
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
}
