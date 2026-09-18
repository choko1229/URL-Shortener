<?php

declare(strict_types=1);

namespace App\Services\ShortUrl;

use App\Enums\ExpiryOption;
use App\ViewModels\ShortUrlFormData;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** 発行フォームの入力（検証済み） */
final readonly class ShortUrlDraft
{
    public function __construct(
        public string $originalUrl,
        public ?string $customSlug,
        public ExpiryOption $expiry,
        // 利用者のローカル時刻（表示タイムゾーン）の datetime-local 値。expiry が Custom のときのみ
        public ?string $expiresAtLocal,
        public ?string $password,
    ) {}

    /** 有効期限（UTC）。無期限なら null */
    public function expiresAt(CarbonImmutable $now, string $timezone): ?CarbonImmutable
    {
        if ($this->expiry === ExpiryOption::Never) {
            return null;
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
