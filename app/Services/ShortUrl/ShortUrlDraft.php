<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\ExpiryOption;
use App\ViewModels\ShortUrlFormData;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** 発行の入力（検証済み）。フォームと API で共用する */
final readonly class ShortUrlDraft
{
    public function __construct(
        public string $originalUrl,
        public ?string $customSlug,
        public ExpiryOption $expiry,
        // 利用者のローカル時刻（表示タイムゾーン）の datetime-local 値。フォームで expiry が Custom のときのみ
        public ?string $expiresAtLocal,
        public ?string $password,
        // API など、日時をタイムゾーン付きで受け取った場合の有効期限
        public ?CarbonImmutable $explicitExpiresAt = null,
    ) {}

    /** API 用: 有効期限を日時で直接指定する（null なら無期限） */
    public static function withExpiresAt(string $originalUrl, ?string $customSlug, ?CarbonImmutable $expiresAt, ?string $password): self
    {
        return new self(
            originalUrl: $originalUrl,
            customSlug: $customSlug,
            expiry: $expiresAt === null ? ExpiryOption::Never : ExpiryOption::Custom,
            expiresAtLocal: null,
            password: $password,
            explicitExpiresAt: $expiresAt,
        );
    }

    /** 有効期限（UTC）。無期限なら null */
    public function expiresAt(CarbonImmutable $now, string $timezone): ?CarbonImmutable
    {
        if ($this->expiry === ExpiryOption::Never) {
            return null;
        }

        if ($this->explicitExpiresAt !== null) {
            return $this->explicitExpiresAt->utc();
        }

        $days = $this->expiry->days();
        if ($days !== null) {
            return $now->addDays($days)->utc();
        }

        $expiresAt = $this->expiresAtLocal === null
            ? false
            : CarbonImmutable::createFromFormat(ShortUrlFormData::DATETIME_LOCAL_FORMAT, $this->expiresAtLocal, $timezone);

        if (! $expiresAt instanceof CarbonImmutable) {
            throw new InvalidArgumentException('有効期限の日時が正しくありません。');
        }

        return $expiresAt->utc();
    }
}
