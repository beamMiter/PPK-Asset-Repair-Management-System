<?php

namespace App\Support;

/**
 * Makes Thai tone marks show in a dompdf PDF.
 *
 * dompdf draws each character's own glyph at its default place, so a tone mark on top of an upper vowel sits inside the vowel and
 * cannot be seen: "ที่" prints as "ที", "ทั้งหมด" as "ทังหมด". The fix is in the font (SarabunPDF-*.ttf, built by
 * scripts/build-thai-pdf-fonts.py): for every "consonant + upper vowel + tone", "consonant + tone + ำ" (and a lone vowel or tone over
 * ป ฝ ฟ, whose ascender the font moves them away from) it holds one glyph with the vowel and the tone already substituted and
 * positioned as a browser would. This replaces such a cluster, after its consonant, by the private-use character that glyph is
 * reached by. The numbering is the one in resources/data/thai-pdf-font.json, which the build script writes.
 *
 * Apply it last, to the finished HTML (or the string for the canvas): the private-use characters mean nothing to a word breaker, and
 * the text must be in the SarabunPDF font — anywhere else they are empty boxes.
 */
final class ThaiPdfText
{
    /** @var array{base:int, stride:int, consonants:string, ascenders:string, clusters:array<int,string>}|null */
    private static ?array $manifest = null;

    /** @var array<string,int>|null cluster => its number */
    private static ?array $clusterIndex = null;

    public static function compose(string $text): string
    {
        // no Thai mark, no work: most of a document's text never gets to the regex
        if (! preg_match('/[\x{0E31}\x{0E34}-\x{0E37}\x{0E48}-\x{0E4B}]/u', $text)) {
            return $text;
        }

        $manifest = self::manifest();

        // a consonant, then: vowel + tone | tone + ำ | a lone vowel | a lone tone   (longest first)
        return preg_replace_callback(
            '/([\x{0E01}-\x{0E2E}])(?:([\x{0E31}\x{0E34}-\x{0E37}][\x{0E48}-\x{0E4B}])|([\x{0E48}-\x{0E4B}]\x{0E33})|([\x{0E31}\x{0E34}-\x{0E37}])|([\x{0E48}-\x{0E4B}]))/u',
            function (array $m) use ($manifest) {
                $consonant = $m[1];
                $cluster = ($m[2] ?? '') . ($m[3] ?? '') . ($m[4] ?? '') . ($m[5] ?? '');

                // a lone vowel or tone mark only differs from the plain glyph over ป ฝ ฟ
                if (mb_strlen($cluster) === 1 && ! str_contains($manifest['ascenders'], $consonant)) {
                    return $m[0];
                }

                $ci = mb_strpos($manifest['consonants'], $consonant);
                $ki = self::clusterIndex()[$cluster] ?? null;

                if ($ci === false || $ki === null) {
                    return $m[0];
                }

                return $consonant . mb_chr($manifest['base'] + $ci * $manifest['stride'] + $ki);
            },
            $text,
        ) ?? $text;
    }

    /** @return array{base:int, stride:int, consonants:string, ascenders:string, clusters:array<int,string>} */
    private static function manifest(): array
    {
        return self::$manifest ??= json_decode(file_get_contents(resource_path('data/thai-pdf-font.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string,int> */
    private static function clusterIndex(): array
    {
        return self::$clusterIndex ??= array_flip(self::manifest()['clusters']);
    }
}
