<?php

namespace Tests\Feature;

use FontLib\Font;
use Tests\TestCase;

/**
 * Thai lower vowels (ุ ู) hang further below the baseline than the font's own descent — in Sarabun ู reaches 0.332 em down while
 * the font's descent is 0.232 em — so a line box needs half-leading for them. A box that clips its content (a text input's editor,
 * an `overflow: hidden` element such as a TomSelect item) cuts them off when the line-height is tight: at 14px on a 20px line
 * (Tailwind `text-sm`) the foot of ู is 0.5px outside the box and is eaten ("สระ ู โดน input กิน").
 *
 * The needed ratio is read from the font file itself, and each place that clips its text has to give at least that.
 */
class ThaiTextRoomTest extends TestCase
{
    /** line-height / font-size that keeps the ink of every Thai glyph inside its line box, from Sarabun's own numbers */
    private function neededRatio(): float
    {
        $font = Font::load(public_path('images/fonts/Sarabun-Regular.ttf'));
        $font->parse();

        $upm = $font->getData('head', 'unitsPerEm');
        $ascent = $font->getData('hhea', 'ascent');
        $descent = -$font->getData('hhea', 'descent');
        $cmap = $font->getUnicodeCharMap();
        $glyf = $font->getData('glyf');

        $lowest = 0;
        $highest = 0;
        foreach (range(0x0E01, 0x0E5B) as $codePoint) {   // the Thai block
            if (! isset($cmap[$codePoint])) {
                continue;
            }
            $glyph = $glyf[$cmap[$codePoint]];
            $glyph->parseData();
            $lowest = min($lowest, $glyph->yMin);
            $highest = max($highest, $glyph->yMax);
        }

        // each side of the line box gets half of the leading: it has to cover what the ink hangs out of the font's ascent / descent
        $overhang = max(0, -$lowest - $descent, $highest - $ascent);

        return ($ascent + $descent + 2 * $overhang) / $upm;
    }

    /** Tailwind's text-* sizes (rem) and leading-* (rem or a ratio) as far as this project uses them */
    private function metricsOfApply(string $apply): array
    {
        $sizes = ['xs' => [0.75, 1.0], 'sm' => [0.875, 1.25], 'base' => [1.0, 1.5], 'lg' => [1.125, 1.75]];
        $this->assertSame(1, preg_match('/\btext-(xs|sm|base|lg)\b/', $apply, $size), 'a font size');
        [$fontSize, $lineHeight] = $sizes[$size[1]];

        if (preg_match('/\bleading-(\d+)\b/', $apply, $m)) {
            $lineHeight = (int) $m[1] * 0.25;
        } elseif (preg_match('/\bleading-(none|tight|snug|normal|relaxed|loose)\b/', $apply, $m)) {
            $lineHeight = $fontSize * ['none' => 1, 'tight' => 1.25, 'snug' => 1.375, 'normal' => 1.5, 'relaxed' => 1.625, 'loose' => 2][$m[1]];
        }

        return [$fontSize, $lineHeight];
    }

    public function test_the_font_needs_one_and_a_half_em_of_line_for_thai(): void
    {
        // a sanity check on the measurement itself — and on the font: another font may need another number
        $this->assertEqualsWithDelta(1.5, $this->neededRatio(), 0.05);
    }

    public function test_the_shared_text_input_gives_thai_the_room_it_needs(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertSame(1, preg_match('/\.ui-input\s*\{\s*@apply([^;]+);/s', $css, $m), '.ui-input');

        [$fontSize, $lineHeight] = $this->metricsOfApply($m[1]);
        $this->assertGreaterThanOrEqual($this->neededRatio(), $lineHeight / $fontSize, 'line-height / font-size of .ui-input');

        // and the box the line sits in is at least a line tall: fixed height − borders − vertical padding
        $this->assertSame(1, preg_match('/\bh-(\d+)\b/', $m[1], $h), 'a fixed height');
        $padding = preg_match('/\bpy-(\d+(?:\.\d)?)\b/', $m[1], $p) ? (float) $p[1] * 0.25 * 2 : 0.0;
        $content = (int) $h[1] * 0.25 - 2 / 16 - $padding;   // rem; the border is 1px on each side
        $this->assertGreaterThanOrEqual($lineHeight, $content, 'the content box of .ui-input is at least one line tall');
    }

    public function test_the_select_fields_give_thai_the_room_it_needs(): void
    {
        $css = file_get_contents(resource_path('css/layout.css'));

        // the item (overflow: hidden, for the "…" on a long value) and the text box, then the control that holds them
        foreach ([
            'the item and text box' => '/\.ts-wrapper\.single \.ts-control input,\s*\.ts-wrapper\.single \.ts-control \.item\s*\{([^}]*)\}/',
            'the control' => '/\.ts-wrapper\.single \.ts-control\s*\{([^}]*height: 44px[^}]*)\}/',
        ] as $what => $pattern) {
            $this->assertSame(1, preg_match($pattern, $css, $m), $what);
            $this->assertSame(1, preg_match('/font-size:\s*([\d.]+)rem/', $m[1], $size), "$what: font-size");
            $this->assertSame(1, preg_match('/line-height:\s*([\d.]+)rem/', $m[1], $line), "$what: line-height");

            $this->assertGreaterThanOrEqual($this->neededRatio(), (float) $line[1] / (float) $size[1], "$what: line-height / font-size");
        }
    }
}
