<?php

declare(strict_types=1);

namespace App\Enums;

/** QR コードのダウンロード形式（requirements.md 6 章） */
enum QrFormat: string
{
    case Svg = 'svg';
    case Png = 'png';

    /** ルートの {format} に使うパターン */
    public const PATTERN = 'svg|png';

    public function contentType(): string
    {
        return match ($this) {
            self::Svg => 'image/svg+xml',
            self::Png => 'image/png',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Svg => 'SVG',
            self::Png => 'PNG',
        };
    }

    /** 拡大しても劣化しないか（画面での説明に使う） */
    public function description(): string
    {
        return match ($this) {
            self::Svg => '印刷など、拡大しても粗くならない形式',
            self::Png => '画像として貼り付けやすい形式',
        };
    }
}
