<?php

namespace Tests\Feature;

use FontLib\Font;
use Tests\TestCase;

/**
 * Thai lower vowels (ุ ู) hang further below the baseline than the font's own descent — in Sarabun ู reaches 0.332 em down while
 * the font's descent is 0.232 em (4.65px against 3px at 14px) — so a box that clips its text cuts their feet off unless it is taller
 * than the font's own ascent + descent.
 *
 * What clips a text `<input>`: its inner editor is `overflow: scroll`, and Chromium (text_control_inner_elements.cc) removes the
 * line-height of that editor — sets it to `normal` — when the input has a FIXED height taller than the line-height, or when the
 * line-height is not taller than the font size. `normal` is the font's ascent + descent (18px at 14px), so the ink is cut exactly 3px
 * under the baseline whatever line-height the CSS says: `.ui-input` was `h-11` (44px fixed), and no line-height could ever help it
 * ("สระ ู โดน input กิน"; measured on a screenshot: ู had 3 rows of ink in the field and 5 outside it, cut at baseline + 3.0).
 * An input with an automatic height keeps its line-height, and then the line has to be tall enough: the room is worked out from the
 * font file, the way Chrome lays a line out (whole-pixel ascent / descent, the leading split in two), plus half a pixel for
 * anti-aliasing.
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

        // a fixed-height input is laid out with `line-height: normal` (= ascent + descent = 18px at 14px) whatever the CSS says: cut
        // 1.65px short of the foot of ู, i.e. at baseline + 3px — what the screenshot shows
        $this->assertSame(18.0, $this->normalLine(14));
        $this->assertEqualsWithDelta(-1.65, $this->room(14, $this->normalLine(14))['below'], 0.05);
    }

    /** the line a fixed-height input actually gets: `normal` = the font's whole-pixel ascent + descent */
    private function normalLine(float $fontPx): float
    {
        $m = $this->metrics();

        return round($m['ascent'] * $fontPx / $m['upm']) + round($m['descent'] * $fontPx / $m['upm']);
    }

    public function test_the_shared_text_input_gives_thai_the_room_it_needs(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertSame(1, preg_match('/\.ui-input\s*\{\s*@apply([^;]+);/s', $css, $m), '.ui-input');

        // a fixed height would make Chromium drop the line-height of the inner editor (see the class comment): cut at baseline + 3px
        $this->assertDoesNotMatchRegularExpression('/(?<![-\w])h-(\d|\[|px\b|full\b|screen\b|fit\b)/', $m[1], 'no fixed height on .ui-input');

        [$fontPx, $linePx] = $this->metricsOfApply($m[1]);
        $this->assertRoom($fontPx, $linePx, '.ui-input');

        // the field is still 44px: line + vertical padding + the two 1px borders (min-h keeps it from ever being shorter)
        $this->assertSame(1, preg_match('/\bpy-\[(\d+)px\]/', $m[1], $p), 'vertical padding in px');
        $this->assertSame(1, preg_match('/\bmin-h-(\d+)\b/', $m[1], $minH), 'a min-height');
        $this->assertSame(44.0, $linePx + 2 * (float) $p[1] + 2, 'line + padding + borders = 44px, as the old h-11');
        $this->assertSame(44, (int) $minH[1] * 4);
    }

    /** No text input is written with a fixed height either: it would get `line-height: normal` and be cut the same way. */
    public function test_no_text_input_has_a_fixed_height(): void
    {
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('/<input\b((?:[^<>"\']|"[^"]*"|\'[^\']*\')*)>/s', file_get_contents($file->getPathname()), $tags);
            foreach ($tags[1] as $attrs) {
                if (preg_match('/\btype=["\'](hidden|checkbox|radio|submit|button|file|range|color|image|reset)["\']/', $attrs)) {
                    continue;
                }
                if (preg_match('/\bclass="([^"]*)"/s', $attrs, $class) && preg_match('/(?<![-\w])h-(\d|\[|px\b|full\b|screen\b|fit\b)/', $class[1])) {
                    $offenders[] = str_replace(resource_path('views').'/', '', $file->getPathname());
                }
                if (preg_match('/\bstyle="[^"]*(?<![-\w])height\s*:\s*[\d.]+(px|rem)/', $attrs)) {
                    $offenders[] = str_replace(resource_path('views').'/', '', $file->getPathname()).' (style)';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), 'text inputs with a fixed height');
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
