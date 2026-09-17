<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Enums\ExpiryOption;
use App\Support\ShortenerSettings;
use App\Support\ShortUrlBuilder;
use Carbon\CarbonImmutable;

/** 短縮URL発行フォームの表示条件（ログイン状態で変わる項目をまとめる） */
final readonly class ShortUrlFormData
{
    /** datetime-local 入力の値形式 */
    public const DATETIME_LOCAL_FORMAT = 'Y-m-d\TH:i';

    /**
     * @param  list<ExpiryOption>  $expiryOptions
     */
    public function __construct(
        public bool $isMember,
        public array $expiryOptions,
        public ExpiryOption $defaultExpiry,
        public ?int $maxExpiryDays,
        public string $expiresAtMin,
        public ?string $expiresAtMax,
        public int $customSlugMinLength,
        public int $customSlugMaxLength,
        public string $shortHost,
    ) {}

    public static function build(bool $isMember, ShortenerSettings $settings, ShortUrlBuilder $urls, CarbonImmutable $now): self
    {
        $localNow = $now->setTimezone($settings->displayTimezone());
        $maxDays = $isMember ? null : $settings->guestMaxExpiryDays();

        return new self(
            isMember: $isMember,
            expiryOptions: $maxDays === null ? ExpiryOption::cases() : ExpiryOption::selectableWithin($maxDays),
            defaultExpiry: ExpiryOption::defaultFor($isMember),
            maxExpiryDays: $maxDays,
            expiresAtMin: $localNow->addMinutes(5)->format(self::DATETIME_LOCAL_FORMAT),
            expiresAtMax: $maxDays === null ? null : $localNow->addDays($maxDays)->format(self::DATETIME_LOCAL_FORMAT),
            customSlugMinLength: $settings->customSlugMinLength(),
            customSlugMaxLength: $settings->customSlugMaxLength(),
            shortHost: $urls->host(),
        );
    }

    /** 「無期限」を選んだときにログインを促す必要があるか（requirements.md 2-3） */
    public function neverRequiresLogin(): bool
    {
        return ! $this->isMember;
    }
}
