<?php

declare(strict_types=1);

namespace App\Enums;

/** ダークモードの扱い（サイト設定で決める既定。訪問者はヘッダーのボタンで切り替えられる） */
enum ColorScheme: string
{
    case Light = 'light';
    case Auto = 'auto';
    case Dark = 'dark';

    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::Light : self::Light;
    }

    public function label(): string
    {
        return match ($this) {
            self::Light => 'ライトのみ',
            self::Auto => '閲覧者の端末の設定に合わせる',
            self::Dark => 'ダークを既定にする',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Light => 'ダークモードを使いません。切り替えボタンも表示しません。',
            self::Auto => 'OS がダークモードならダークで表示します。',
            self::Dark => '初めての訪問者にもダークで表示します。',
        };
    }

    /** 訪問者が切り替えられるか（ライトのみの場合は切り替えない） */
    public function isSwitchable(): bool
    {
        return $this !== self::Light;
    }

    /** <html> と CSS の color-scheme に入れる値 */
    public function cssValue(): string
    {
        return match ($this) {
            self::Light => 'light',
            self::Auto => 'light dark',
            self::Dark => 'dark',
        };
    }
}
