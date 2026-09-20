<?php

declare(strict_types=1);

namespace App\Support;

/**
 * メインカラー 1 色から、画面全体の配色（design.md 1. カラー）を作る。
 * 文字色として使う色は、背景との明度差（コントラスト比 4.5:1 以上）を満たすまで自動で調整する。
 */
final class ColorPalette
{
    public const DEFAULT_COLOR = '#2ec5e0';

    /** サイト設定で選べる色（設置した人が迷わないよう、読みやすさを確認した色を並べる） */
    public const PRESETS = [
        '#2ec5e0' => 'ターコイズ',
        '#3b82f6' => 'ブルー',
        '#8b5cf6' => 'パープル',
        '#ec4899' => 'ピンク',
        '#f97316' => 'オレンジ',
        '#10b981' => 'グリーン',
    ];

    /** 文字色に求めるコントラスト比（WCAG AA） */
    private const MIN_CONTRAST = 4.5;

    private const WHITE = '#ffffff';

    public static function isValid(string $hex): bool
    {
        return preg_match('/\A#[0-9a-fA-F]{6}\z/', $hex) === 1;
    }

    public static function normalize(?string $hex): string
    {
        return is_string($hex) && self::isValid($hex) ? strtolower($hex) : self::DEFAULT_COLOR;
    }

    /**
     * 明るい配色。
     *
     * @return array<string, string> CSS 変数名（--color-* を除く）=> 色
     */
    public static function light(string $hex): array
    {
        [$h, $s] = self::hue($hex);

        $primaryDark = self::readableOn(self::WHITE, $h, min($s + 16, 100), 38, darker: true);

        return [
            'primary' => strtolower($hex),
            'primary-dark' => $primaryDark,
            'primary-darker' => self::shift($primaryDark, -5),
            'primary-tint' => self::hsl($h, min($s, 80), 94),
            'primary-tint-soft' => self::hsl($h, min($s, 80), 98),
            'surface' => self::WHITE,
            'border' => self::hsl($h, min($s, 62), 94),
            'border-strong' => self::hsl($h, min($s, 70), 93),
            'border-input' => self::hsl($h, min($s, 62), 91),
            'text-primary' => self::hsl($h, min($s, 60), 15),
            'text-secondary' => self::readableOn(self::WHITE, $h, min($s, 20), 45, darker: true),
            'text-muted' => self::hsl($h, min($s, 16), 62),
            'dark-panel' => self::hsl($h, min($s, 60), 15),
            'dark-panel-button' => self::hsl($h, min($s, 50), 22),
            'table-header' => self::hsl($h, min($s, 75), 98),
            'table-divider' => self::hsl($h, min($s, 60), 96),
            'success' => $primaryDark,
        ];
    }

    /**
     * 暗い配色（ダークモード）。
     *
     * @return array<string, string>
     */
    public static function dark(string $hex): array
    {
        [$h, $s, $l] = self::toHsl($hex);
        $s = max($s, 8);

        $surface = self::hsl($h, min($s, 22), 13);
        // 選んだ色をそのまま使い、暗い面に埋もれる場合だけ明るくする
        $primary = self::readableOn($surface, $h, $s, $l, darker: false, minContrast: 3.0);

        return [
            'primary' => $primary,
            // 暗い面の上の文字・アイコンに使うため、明るくしてコントラストを確保する
            'primary-dark' => self::readableOn($surface, $h, min($s, 85), 62, darker: false),
            'primary-darker' => self::readableOn($surface, $h, min($s, 85), 70, darker: false),
            'primary-tint' => self::hsl($h, min($s, 40), 22),
            'primary-tint-soft' => self::hsl($h, min($s, 30), 10),
            'surface' => $surface,
            'border' => self::hsl($h, min($s, 20), 22),
            'border-strong' => self::hsl($h, min($s, 22), 26),
            'border-input' => self::hsl($h, min($s, 22), 28),
            'text-primary' => self::hsl($h, min($s, 25), 95),
            'text-secondary' => self::hsl($h, min($s, 14), 74),
            'text-muted' => self::hsl($h, min($s, 12), 58),
            'dark-panel' => self::hsl($h, min($s, 30), 19),
            'dark-panel-button' => self::hsl($h, min($s, 30), 27),
            'table-header' => self::hsl($h, min($s, 22), 17),
            'table-divider' => self::hsl($h, min($s, 20), 22),
            'success' => self::readableOn($surface, $h, min($s, 85), 62, darker: false),
        ];
    }

    /** 2 色のコントラスト比（1〜21）。テストと配色の自動調整で使う */
    public static function contrast(string $foreground, string $background): float
    {
        $light = self::luminance($foreground);
        $dark = self::luminance($background);

        if ($light < $dark) {
            [$light, $dark] = [$dark, $light];
        }

        return ($light + 0.05) / ($dark + 0.05);
    }

    /** 指定の背景で見えるようになるまで、明度を 1% ずつ動かす */
    private static function readableOn(string $background, float $h, float $s, float $startL, bool $darker, float $minContrast = self::MIN_CONTRAST): string
    {
        $step = $darker ? -1 : 1;

        for ($l = $startL; $l >= 10 && $l <= 92; $l += $step) {
            $color = self::hsl($h, $s, $l);

            if (self::contrast($color, $background) >= $minContrast) {
                return $color;
            }
        }

        return self::hsl($h, $s, $darker ? 10 : 92);
    }

    private static function shift(string $hex, float $lightness): string
    {
        [$h, $s, $l] = self::toHsl($hex);

        return self::hsl($h, $s, $l + $lightness);
    }

    /** @return array{0: float, 1: float} 色相と彩度 */
    private static function hue(string $hex): array
    {
        [$h, $s] = self::toHsl($hex);

        // 彩度が極端に低い色（ほぼ灰色）は、薄い色を作ると色味が消えるため最低限の彩度を与える
        return [$h, max($s, 8)];
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(static fn (int $value): float => $value / 255, self::rgb($hex));

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $l = ($max + $min) / 2;

        if ($delta === 0.0) {
            return [0.0, 0.0, $l * 100];
        }

        $s = $delta / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => fmod(($g - $b) / $delta, 6),
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };

        return [fmod($h * 60 + 360, 360), $s * 100, $l * 100];
    }

    private static function hsl(float $h, float $s, float $l): string
    {
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (int $value): float {
            $channel = $value / 255;

            return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 255, ($value >> 8) & 255, $value & 255];
    }
}
