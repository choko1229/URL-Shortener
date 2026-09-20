<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * サイト設定で選べる書体（design.md 2. タイポグラフィ）。
 * いずれも Google Fonts の日本語フォントで、見出し（font-rounded）と本文（font-sans）の組み合わせを決める。
 */
enum FontTheme: string
{
    case Rounded = 'rounded';
    case Gothic = 'gothic';
    case ZenKaku = 'zen-kaku';
    case ZenMaru = 'zen-maru';
    case Mincho = 'mincho';

    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::Rounded : self::Rounded;
    }

    public function label(): string
    {
        return match ($this) {
            self::Rounded => '丸ゴシック（既定）',
            self::Gothic => 'ゴシック',
            self::ZenKaku => '角ゴシック',
            self::ZenMaru => 'やわらかい丸ゴシック',
            self::Mincho => '明朝',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Rounded => '親しみやすく、見出しがはっきり見えます。',
            self::Gothic => '見出しも本文も同じ書体で、事務的で読みやすい印象です。',
            self::ZenKaku => '直線的で、きちんとした印象になります。',
            self::ZenMaru => '丸みが強く、やわらかい印象になります。',
            self::Mincho => '見出しが明朝で、落ち着いた読み物らしい印象になります。',
        };
    }

    /** 見出し用（--font-rounded） */
    public function headingStack(): string
    {
        return match ($this) {
            self::Rounded => "'M PLUS Rounded 1c', 'Noto Sans JP', ui-sans-serif, system-ui, sans-serif",
            self::Gothic => "'Noto Sans JP', ui-sans-serif, system-ui, sans-serif",
            self::ZenKaku => "'Zen Kaku Gothic New', 'Noto Sans JP', ui-sans-serif, system-ui, sans-serif",
            self::ZenMaru => "'Zen Maru Gothic', 'Noto Sans JP', ui-sans-serif, system-ui, sans-serif",
            self::Mincho => "'Noto Serif JP', ui-serif, Georgia, serif",
        };
    }

    /** 本文用（--font-sans） */
    public function bodyStack(): string
    {
        return match ($this) {
            self::ZenKaku => "'Zen Kaku Gothic New', 'Noto Sans JP', ui-sans-serif, system-ui, sans-serif",
            self::ZenMaru => "'Zen Maru Gothic', 'Noto Sans JP', ui-sans-serif, system-ui, sans-serif",
            default => "'Noto Sans JP', ui-sans-serif, system-ui, sans-serif",
        };
    }

    /** Google Fonts の読み込み URL */
    public function stylesheetUrl(): string
    {
        $families = match ($this) {
            self::Rounded => ['M+PLUS+Rounded+1c:wght@500;700;800', 'Noto+Sans+JP:wght@400;500;700'],
            self::Gothic => ['Noto+Sans+JP:wght@400;500;700;800'],
            self::ZenKaku => ['Zen+Kaku+Gothic+New:wght@400;500;700;900'],
            self::ZenMaru => ['Zen+Maru+Gothic:wght@400;500;700;900'],
            self::Mincho => ['Noto+Serif+JP:wght@500;700;900', 'Noto+Sans+JP:wght@400;500;700'],
        };

        return 'https://fonts.googleapis.com/css2?family='.implode('&family=', $families).'&display=swap';
    }
}
