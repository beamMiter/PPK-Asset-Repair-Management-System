<?php

namespace App\Support;

/**
 * The avatar of someone with no photo: their initials on a coloured square, as a small SVG carried in the `src` itself
 * (`data:image/svg+xml,…`) — no request to another site, so no waiting on one from a network with no internet.
 * resources/js/avatar.js draws the same avatar for the chat lines that arrive after the page has loaded; keep the two alike.
 */
class InitialsAvatar
{
    /** the colours a name can get (the ones the avatar service used to give) */
    public const PALETTE = ['0D8ABC', '0E2B51', '16A34A', '7C3AED', 'EA580C', 'DB2777', '374151'];

    // เ แ โ ใ ไ are written before the consonant they follow in sound: the initial of เกียรติ is ก
    private const LEADING_VOWELS = ['เ', 'แ', 'โ', 'ใ', 'ไ'];

    public static function colorFor(string $name): string
    {
        return self::PALETTE[crc32(strtolower($name !== '' ? $name : 'user')) % count(self::PALETTE)];
    }

    /** one letter per word, two words at most; `?` when there is nothing to draw */
    public static function initials(string $name): string
    {
        $letters = '';

        foreach (preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $first = null;
            foreach ($chars as $char) {
                if (! in_array($char, self::LEADING_VOWELS, true) && preg_match('/[\p{L}\p{N}]/u', $char)) {
                    $first = $char;
                    break;
                }
            }
            if ($first === null) {
                continue;
            }

            $letters .= mb_strtoupper($first);
            if (mb_strlen($letters) === 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : '?';
    }

    public static function url(string $name, int $size = 128, ?string $background = null): string
    {
        $text = self::initials($name);
        $background ??= self::colorFor($name);
        $fontSize = mb_strlen($text) > 1 ? 40 : 46;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 100 100">'
            .'<rect width="100" height="100" fill="#'.$background.'"/>'
            .'<text x="50" y="50" dy=".34em" text-anchor="middle" fill="#ffffff" font-weight="700" font-size="'.$fontSize.'"'
            ." font-family=\"Tahoma,'Leelawadee UI','Noto Sans Thai',Arial,sans-serif\">"
            .htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8')
            .'</text></svg>';

        return 'data:image/svg+xml;charset=utf-8,'.rawurlencode($svg);
    }
}
