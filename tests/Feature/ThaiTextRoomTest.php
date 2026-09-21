<?php

namespace Tests\Feature;

use FontLib\Font;
use Tests\TestCase;

/**
 * Thai lower vowels (ุ ู) hang further below the baseline than the font's own descent — in Sarabun ู reaches 0.332 em down while
 * the font's descent is 0.232 em — so a line box needs half-leading for them. A box that clips its content (a text input's editor,
 * an `overflow: hidden` element such as a TomSelect item) cuts them off when the line is tight: at 14px on a 20px line
 * (Tailwind `text-sm`) the foot of ู is 0.65px outside the box, and at 16px on the 24px line that @tailwindcss/forms gives every
 * plain text input there is 0.19px left — no room for anti-aliasing — so the foot is eaten ("สระ ู โดน input กิน").
 *
 * The room is worked out from the font file itself, the way Chrome lays a line out (whole-pixel ascent / descent, the leading split
 * in two), and each place that clips its text has to give at least that, plus half a pixel for anti-aliasing.
 */
class ThaiTextRoomTest extends TestCase
{
    private const SLACK_PX = 0.5;

    /** @var array{upm:int, ascent:int, descent:int, lowest:int, highest:int}|null */
    private ?array $metrics = null;

    private function metrics(): array
    {
        if ($this->metrics) {
            return $this->metrics;
        }

        $font = Font::load(public_path('images/fonts/Sarabun-Regular.ttf'));
        $font->parse();

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

        return $this->metrics = [
            'upm' => $font->getData('head', 'unitsPerEm'),
            'ascent' => $font->getData('hhea', 'ascent'),
            'descent' => -$font->getData('hhea', 'descent'),
            'lowest' => $lowest,
            'highest' => $highest,
        ];
    }

    /** How far (px) the ink of the deepest / highest Thai glyph is inside its line box; negative = outside = cut. */
    private function room(float $fontPx, float $linePx): array
    {
        $m = $this->metrics();
        $ascent = round($m['ascent'] * $fontPx / $m['upm']);     // Chrome lays a line out with whole-pixel ascent / descent
        $descent = round($m['descent'] * $fontPx / $m['upm']);
        $baseline = ($linePx - ($ascent + $descent)) / 2 + $ascent;   // px from the top of the line box

        return [
            'below' => $linePx - ($baseline + (-$m['lowest']) * $fontPx / $m['upm']),
            'above' => $baseline - $m['highest'] * $fontPx / $m['upm'],
        ];
    }

    private function assertRoom(float $fontPx, float $linePx, string $what): void
    {
        $room = $this->room($fontPx, $linePx);

        $this->assertGreaterThanOrEqual(self::SLACK_PX, $room['below'], "$what: room under ู at {$fontPx}px on a {$linePx}px line");
        $this->assertGreaterThanOrEqual(self::SLACK_PX, $room['above'], "$what: room over the tone marks at {$fontPx}px on a {$linePx}px line");
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

        return [$fontSize * 16, $lineHeight * 16];   // px
    }

    public function test_the_measurement_itself(): void
    {
        // the deepest Thai glyph is ู, 0.332 em below the baseline, 0.1 em below the font's descent — the reason for all of this
        $this->assertSame(-332, $this->metrics()['lowest']);
        $this->assertSame(232, $this->metrics()['descent']);
        $this->assertSame(1068, $this->metrics()['ascent']);

        // ...and it shows the two lines that were cut: text-sm's 20px, and the 16px field on the 24px line of @tailwindcss/forms
        $this->assertLessThan(0, $this->room(14, 20)['below'], '14px on 20px is cut');
        $this->assertLessThan(self::SLACK_PX, $this->room(16, 24)['below'], '16px on 24px has no room for anti-aliasing');
        $this->assertGreaterThanOrEqual(self::SLACK_PX, $this->room(13, 24)['below'], '13px on 24px is fine');
    }

    public function test_the_shared_text_input_gives_thai_the_room_it_needs(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertSame(1, preg_match('/\.ui-input\s*\{\s*@apply([^;]+);/s', $css, $m), '.ui-input');

        [$fontPx, $linePx] = $this->metricsOfApply($m[1]);
        $this->assertRoom($fontPx, $linePx, '.ui-input');

        // and the box the line sits in is at least a line tall: fixed height − borders − vertical padding
        $this->assertSame(1, preg_match('/\bh-(\d+)\b/', $m[1], $h), 'a fixed height');
        $padding = preg_match('/\bpy-(\d+(?:\.\d)?)\b/', $m[1], $p) ? (float) $p[1] * 4 * 2 : 0.0;
        $this->assertGreaterThanOrEqual($linePx, (int) $h[1] * 4 - 2 - $padding, 'the content box of .ui-input is at least one line tall');
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

            $this->assertRoom((float) $size[1] * 16, (float) $line[1] * 16, $what);
        }
    }

    /**
     * Every other text input and select — the filter bars, the sign-in form, the profile page — takes its line from
     * @tailwindcss/forms (`line-height: 1.5rem`, and `font-size: 1rem` unless a class says otherwise). One rule in the base layer
     * raises that to 1.625 em when the font is big enough that 24px is too tight (16px: 26px), and never lowers it (13px stays 24px).
     */
    public function test_every_plain_text_field_keeps_the_room_thai_needs_at_any_font_size(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertSame(1, preg_match('/@layer base\s*\{.*?line-height:\s*max\(([\d.]+)rem,\s*([\d.]+)em\)/s', $css, $m), 'a base rule with max(rem, em)');
        [$floorRem, $ratio] = [(float) $m[1], (float) $m[2]];

        $this->assertStringContainsString('input:where(:not(', $css, 'text inputs');
        $this->assertMatchesRegularExpression('/@layer base\s*\{[^}]*\bselect\b[^}]*line-height:\s*max/s', $css, 'and selects');

        foreach ([12, 13, 14, 15, 16, 17, 18, 20] as $fontPx) {
            $linePx = max($floorRem * 16, $ratio * $fontPx);
            $this->assertGreaterThanOrEqual(24.0, $linePx, "never below the 24px it had ($fontPx px)");
            $this->assertRoom($fontPx, $linePx, "a plain field at {$fontPx}px");
        }
    }

    /** A field that sets `text-sm` / `text-xs` takes Tailwind's 1.25rem / 1rem line with it: too tight, unless it says `leading-*` too. */
    public function test_no_input_or_select_uses_a_tight_text_size_without_a_leading(): void
    {
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $offenders = array_merge($offenders, $this->tightFields($file->getPathname()));
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), 'inputs / selects with text-xs / text-sm and no leading-*');
    }

    /** @return list<string> */
    private function tightFields(string $file): array
    {
        $html = file_get_contents($file);
        $found = [];
        preg_match_all('/<(input|select)\b((?:[^<>"\']|"[^"]*"|\'[^\']*\')*)>/s', $html, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            if (preg_match('/\btype=["\'](hidden|checkbox|radio|submit|button|file|range|color|image|reset)["\']/', $tag[2])) {
                continue;
            }
            if (preg_match('/\bclass="([^"]*)"/s', $tag[2], $class) && preg_match('/\btext-(xs|sm)\b/', $class[1]) && ! preg_match('/\bleading-|ui-input/', $class[1])) {
                $found[] = str_replace(resource_path('views').'/', '', $file).': '.preg_replace('/\s+/', ' ', mb_substr($tag[0], 0, 90));
            }
        }

        return $found;
    }
}
