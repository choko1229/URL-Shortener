<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ColorScheme;
use App\Enums\FontTheme;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

/**
 * 画面の見た目（メインカラー・ダークモード・書体）。
 * design.md のデザイントークンを CSS 変数として上書きするため、再ビルドなしで設置した人が変更できる。
 */
final class Theme
{
    use ReadsSiteSettings;

    /** 訪問者が選んだ表示（ライト／ダーク）を覚えておくキー */
    public const STORAGE_KEY = 'color-theme';

    public function color(): string
    {
        return ColorPalette::normalize($this->stored(AppSetting::SITE_THEME_COLOR));
    }

    public function scheme(): ColorScheme
    {
        return ColorScheme::fromValue($this->stored(AppSetting::SITE_COLOR_SCHEME));
    }

    public function font(): FontTheme
    {
        return FontTheme::fromValue($this->stored(AppSetting::SITE_FONT));
    }

    /** <style> に書き出す内容。design.md のトークンを上書きする */
    public function css(): string
    {
        $color = $this->color();
        $scheme = $this->scheme();
        $light = ColorPalette::light($color);
        $dark = ColorPalette::dark($color);
        $fonts = sprintf('--font-sans:%s;--font-rounded:%s;', $this->font()->bodyStack(), $this->font()->headingStack());

        if ($scheme === ColorScheme::Dark) {
            return ':root{'.$fonts.self::declarations($dark, 'dark').'}'
                .':root[data-theme="light"]{'.self::declarations($light, 'light').'}';
        }

        $css = ':root{'.$fonts.self::declarations($light, $scheme->cssValue()).'}';

        if ($scheme === ColorScheme::Auto) {
            $css .= '@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){'.self::declarations($dark, 'dark').'}}'
                .':root[data-theme="dark"]{'.self::declarations($dark, 'dark').'}'
                // 端末がダークでも「ライト表示」を選んだら、入力欄などのブラウザ標準の見た目もライトにする
                .':root[data-theme="light"]{color-scheme:light;}';
        }

        return $css;
    }

    /** ブラウザの UI（アドレスバーなど）の色 */
    public function themeColor(bool $dark = false): string
    {
        return ($dark ? ColorPalette::dark($this->color()) : ColorPalette::light($this->color()))['primary'];
    }

    /**
     * @param  array{color: string, color_scheme: string, font: string}  $values
     */
    public function save(array $values): void
    {
        DB::transaction(static function () use ($values): void {
            AppSetting::store(AppSetting::SITE_THEME_COLOR, ColorPalette::normalize($values['color']));
            AppSetting::store(AppSetting::SITE_COLOR_SCHEME, ColorScheme::fromValue($values['color_scheme'])->value);
            AppSetting::store(AppSetting::SITE_FONT, FontTheme::fromValue($values['font'])->value);
        });

        $this->forgetStoredSettings();
    }

    /** @param  array<string, string>  $palette */
    private static function declarations(array $palette, string $colorScheme): string
    {
        $css = 'color-scheme:'.$colorScheme.';';

        foreach ($palette as $token => $value) {
            $css .= '--color-'.$token.':'.$value.';';
        }

        return $css;
    }
}
