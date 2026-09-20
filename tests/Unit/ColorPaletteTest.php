<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ColorPalette;
use PHPUnit\Framework\TestCase;

final class ColorPaletteTest extends TestCase
{
    /** 文字に使う色は、背景に対して読める明るさであること（WCAG AA: 4.5:1） */
    public function test_text_colors_are_readable_on_their_background(): void
    {
        foreach ([...array_keys(ColorPalette::PRESETS), '#000000', '#ffffff', '#808080', '#ffe600'] as $base) {
            $light = ColorPalette::light($base);
            foreach (['primary-dark', 'primary-darker', 'text-primary', 'text-secondary'] as $token) {
                $this->assertGreaterThanOrEqual(
                    4.5,
                    ColorPalette::contrast($light[$token], $light['surface']),
                    "{$base} の {$token} が明るい配色で読みにくい",
                );
            }

            $dark = ColorPalette::dark($base);
            foreach (['primary-dark', 'primary-darker', 'text-primary'] as $token) {
                $this->assertGreaterThanOrEqual(
                    4.5,
                    ColorPalette::contrast($dark[$token], $dark['surface']),
                    "{$base} の {$token} が暗い配色で読みにくい",
                );
            }
        }
    }

    public function test_light_palette_keeps_the_current_design_for_the_default_color(): void
    {
        $palette = ColorPalette::light(ColorPalette::DEFAULT_COLOR);

        $this->assertSame('#2ec5e0', $palette['primary']);
        $this->assertSame('#ffffff', $palette['surface']);

        // 薄い色は既存のトークン（#e3f7fc / #e7f5fa）とほぼ同じ
        $this->assertLessThan(1.05, ColorPalette::contrast($palette['primary-tint'], '#e3f7fc'));
        $this->assertLessThan(1.05, ColorPalette::contrast($palette['border'], '#e7f5fa'));

        // 文字に使う濃い色は、既存の #0891b2（白地で 3.7:1 と AA 未満）より少し暗くして基準を満たす
        $this->assertLessThan(1.4, ColorPalette::contrast($palette['primary-dark'], '#0891b2'));
        $this->assertGreaterThan(
            ColorPalette::contrast('#0891b2', '#ffffff'),
            ColorPalette::contrast($palette['primary-dark'], '#ffffff'),
        );
    }

    public function test_dark_palette_is_dark_and_light_palette_is_light(): void
    {
        $light = ColorPalette::light('#3b82f6');
        $dark = ColorPalette::dark('#3b82f6');

        $this->assertGreaterThan(4.5, ColorPalette::contrast($light['surface'], $dark['surface']));
        // 面と背景はわずかに違う明るさにして、カードの境目が分かるようにする
        $this->assertNotSame($dark['surface'], $dark['primary-tint-soft']);
    }

    /** ダークでも選んだ色をそのまま使う（暗すぎて見えない色だけ明るくする） */
    public function test_dark_palette_keeps_the_chosen_colour_when_it_is_visible(): void
    {
        foreach (array_keys(ColorPalette::PRESETS) as $base) {
            $this->assertSame($base, ColorPalette::dark($base)['primary'], "{$base} がダークで変わっている");
        }

        $darkBase = '#101820';
        $lifted = ColorPalette::dark($darkBase);
        $this->assertNotSame($darkBase, $lifted['primary']);
        $this->assertGreaterThanOrEqual(3.0, ColorPalette::contrast($lifted['primary'], $lifted['surface']));
    }

    public function test_invalid_values_fall_back_to_the_default_color(): void
    {
        $this->assertSame('#2ec5e0', ColorPalette::normalize(null));
        $this->assertSame('#2ec5e0', ColorPalette::normalize('red'));
        $this->assertSame('#2ec5e0', ColorPalette::normalize('#12345'));
        $this->assertSame('#abcdef', ColorPalette::normalize('#ABCDEF'));
        $this->assertTrue(ColorPalette::isValid('#000000'));
        $this->assertFalse(ColorPalette::isValid('#00000'));
    }

    public function test_grey_base_still_produces_a_visible_palette(): void
    {
        $palette = ColorPalette::light('#808080');

        $this->assertNotSame($palette['surface'], $palette['primary-tint']);
        $this->assertNotSame($palette['border'], $palette['surface']);
    }
}
