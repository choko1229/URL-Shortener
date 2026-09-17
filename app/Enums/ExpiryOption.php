<?php

declare(strict_types=1);

namespace App\Enums;

/** 発行フォームの有効期限の選択肢 */
enum ExpiryOption: string
{
    case OneDay = '1d';
    case SevenDays = '7d';
    case ThirtyDays = '30d';
    case Custom = 'custom';
    case Never = 'never';

    public function label(): string
    {
        return match ($this) {
            self::OneDay => '1日',
            self::SevenDays => '7日',
            self::ThirtyDays => '30日',
            self::Custom => '日時を指定',
            self::Never => '無期限',
        };
    }

    /** 固定期間の日数。期間を持たない選択肢は null */
    public function days(): ?int
    {
        return match ($this) {
            self::OneDay => 1,
            self::SevenDays => 7,
            self::ThirtyDays => 30,
            self::Custom, self::Never => null,
        };
    }

    /**
     * 画面に出す選択肢。未ログインでも「無期限」は表示し、選ぶとログインを促す（requirements.md 2-3）。
     *
     * @return list<self>
     */
    public static function selectableWithin(int $maxDays): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $option): bool => $option->days() === null || $option->days() <= $maxDays,
        ));
    }

    public static function defaultFor(bool $isMember): self
    {
        return $isMember ? self::Never : self::ThirtyDays;
    }
}
