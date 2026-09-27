<?php

namespace Tests\Feature;

use App\Support\ThaiPdfText;
use FontLib\Font;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * dompdf draws a tone mark on an upper vowel inside the vowel, where it cannot be seen: "ที่" printed as "ที", "ทั้งหมด" as "ทังหมด",
 * "เฉลี่ย" as "เฉลีย" — in every PDF the system made. The report now uses sarabunpdf_*.ttf, which holds a ready-made glyph for each
 * such cluster, and App\Support\ThaiPdfText swaps the cluster for the character that glyph is reached by.
 */
class ThaiPdfTextTest extends TestCase
{
    private function manifest(): array
    {
        return json_decode(file_get_contents(resource_path('data/thai-pdf-font.json')), true);
    }

    /** The private-use character standing for $cluster after $consonant, worked out from the manifest independently of the class. */
    private function puaFor(string $consonant, string $cluster): string
    {
        $m = $this->manifest();

        return mb_chr($m['base'] + mb_strpos($m['consonants'], $consonant) * $m['stride'] + array_search($cluster, $m['clusters'], true));
    }

    public function test_a_tone_mark_over_an_upper_vowel_becomes_one_character_after_its_consonant(): void
    {
        $this->assertSame('ท' . $this->puaFor('ท', 'ี่'), ThaiPdfText::compose('ที่'));
        $this->assertSame('ท' . $this->puaFor('ท', 'ั้') . 'งหมด', ThaiPdfText::compose('ทั้งหมด'));
        $this->assertSame('เฉล' . $this->puaFor('ล', 'ี่') . 'ย', ThaiPdfText::compose('เฉลี่ย'));
        $this->assertSame('ห' . $this->puaFor('ห', 'ึ้') . 'น', ThaiPdfText::compose('หึ้น'));
    }

    public function test_the_four_tone_marks_and_five_vowels_all_get_their_own_character(): void
    {
        $seen = [];
        foreach (['ั', 'ิ', 'ี', 'ึ', 'ื'] as $vowel) {
            foreach (['่', '้', '๊', '๋'] as $tone) {
                $out = ThaiPdfText::compose('ก' . $vowel . $tone);
                $this->assertSame(2, mb_strlen($out), "ก$vowel$tone");
                $seen[$out] = true;
            }
        }
        $this->assertCount(20, $seen);
    }

    public function test_a_tone_mark_over_sara_am_is_composed_too(): void
    {
        $this->assertSame('น' . $this->puaFor('น', '้ำ'), ThaiPdfText::compose('น้ำ'));
        $this->assertSame('ค' . $this->puaFor('ค', '่ำ'), ThaiPdfText::compose('ค่ำ'));
    }

    public function test_a_lone_vowel_or_tone_mark_is_only_replaced_over_the_consonants_the_font_moves_it_away_from(): void
    {
        $this->assertSame('ฝ' . $this->puaFor('ฝ', '่') . 'าย', ThaiPdfText::compose('ฝ่าย'));
        $this->assertSame('ป' . $this->puaFor('ป', 'ั') . 'ญหา', ThaiPdfText::compose('ปัญหา'));
        $this->assertSame('ก่อน', ThaiPdfText::compose('ก่อน'), 'over ก the default glyph is right');
        $this->assertSame('ผู้ป่วย', str_replace($this->puaFor('ป', '่'), '่', ThaiPdfText::compose('ผู้ป่วย')), 'only ป changed');
    }

    public function test_text_that_needs_nothing_is_left_alone(): void
    {
        foreach (['ช่วง', 'ห้อง', 'ก็', 'สำคัญ', 'ผู้', 'plain text 123', '', '<span class="x">ก</span>'] as $text) {
            $this->assertSame($text, ThaiPdfText::compose($text), $text);
        }
    }

    public function test_markup_around_the_text_is_not_touched(): void
    {
        $out = ThaiPdfText::compose('<span class="a">ที่</span> &amp; <b>ขั้น</b>');

        $this->assertStringStartsWith('<span class="a">ท', $out);
        $this->assertStringContainsString('</span> &amp; <b>ข', $out);
        $this->assertStringEndsWith('น</b>', $out);
    }

    public function test_the_words_of_the_report_come_out_without_a_bare_tone_mark_over_a_vowel(): void
    {
        $text = 'วันที่ออกรายงาน รายการงานที่เกินเวลา งานทั้งหมดในช่วง เวลาแก้ไขเฉลี่ย ฝ่ายบริหารทั่วไป ข้อสังเกต ข้อเสนอแนะ น้ำหยด';

        $this->assertDoesNotMatchRegularExpression('/[ัิ-ื][่-๋]/u', ThaiPdfText::compose($text));
    }

    /** @return array<string, array{0:string}> */
    public static function fonts(): array
    {
        return ['regular' => ['sarabunpdf_normal.ttf'], 'bold' => ['sarabunpdf_bold.ttf']];
    }

    #[DataProvider('fonts')]
    public function test_every_character_the_composer_can_produce_has_a_glyph_in_the_font(string $file): void
    {
        $font = Font::load(public_path('images/fonts/' . $file));
        $font->parse();
        $cmap = $font->getUnicodeCharMap();

        $m = $this->manifest();
        $missing = [];
        foreach (preg_split('//u', $m['consonants'], -1, PREG_SPLIT_NO_EMPTY) as $ci => $consonant) {
            foreach ($m['clusters'] as $ki => $cluster) {
                $lone = mb_strlen($cluster) === 1;
                if ($lone && ! str_contains($m['ascenders'], $consonant)) {
                    continue;   // the composer leaves these alone
                }
                if (! isset($cmap[$m['base'] + $ci * $m['stride'] + $ki])) {
                    $missing[] = $consonant . $cluster;
                }
            }
        }

        // ฬ is the one consonant the font itself reshapes next to a vowel, which a mark-only glyph cannot carry: those stay as they were
        $missing = array_values(array_filter($missing, fn ($c) => ! str_starts_with($c, 'ฬ')));

        $this->assertSame([], $missing, "$file has no glyph for these clusters");
    }

    /**
     * The whole point: the tone mark has to sit ABOVE the vowel. Plain, the mark's glyph (top at 903 units) lies inside the vowel's
     * (top at 952), which is why it vanished; the composed glyph is taller than the vowel alone.
     */
    #[DataProvider('fonts')]
    public function test_the_composed_glyph_puts_the_tone_mark_above_the_vowel(string $file): void
    {
        $font = Font::load(public_path('images/fonts/' . $file));
        $font->parse();
        $cmap = $font->getUnicodeCharMap();
        $glyf = $font->getData('glyf');
        $hmtx = $font->getData('hmtx');

        $top = function (int $codePoint) use ($cmap, $glyf): int {
            $glyph = $glyf[$cmap[$codePoint]];
            $glyph->parseData();

            return (int) $glyph->yMax;
        };

        $vowel = $top(0x0E35);   // ี
        foreach (['ที่' => ['ท', 'ี่'], 'ทั้ง' => ['ท', 'ั้'], 'ขึ้น' => ['ข', 'ึ้']] as $word => [$consonant, $cluster]) {
            $composed = mb_ord($this->puaFor($consonant, $cluster));
            $this->assertGreaterThan(
                $top(mb_ord(mb_substr($cluster, 0, 1))),
                $top($composed),
                "$word in $file: the tone mark should rise above its vowel",
            );
            $this->assertSame(0, $hmtx[$cmap[$composed]][0], 'a mark glyph advances nothing: the consonant keeps its width');
        }
        $this->assertGreaterThan(0, $vowel);

        // tone + ำ: the composed glyph carries the width of า, so the text after it does not move
        $am = mb_ord($this->puaFor('น', '้ำ'));
        $this->assertGreaterThan(0, $hmtx[$cmap[$am]][0]);
    }

    /**
     * dompdf finds the family by name in installed-fonts.json and reads its metrics from a .ufm. Registered by @font-face instead it
     * would copy the font under a hash of the machine's path and leave that absolute path in the tracked json — so the family is
     * registered by fixed names, and nothing is written when a PDF is made.
     */
    public function test_the_font_is_registered_by_fixed_names_with_no_machine_specific_path(): void
    {
        $dir = public_path('images/fonts');
        $installed = json_decode(file_get_contents($dir . '/installed-fonts.json'), true);

        $this->assertSame(['normal' => 'sarabunpdf_normal', 'bold' => 'sarabunpdf_bold'], $installed['sarabunpdf'] ?? null);
        foreach ($installed['sarabunpdf'] as $name) {
            $this->assertFileExists("$dir/$name.ttf");
            $this->assertFileExists("$dir/$name.ufm");
        }
        $this->assertStringNotContainsString(base_path(), file_get_contents($dir . '/installed-fonts.json'), 'no absolute path of this machine');
    }
}
