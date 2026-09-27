<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Thai is written without spaces, and dompdf can only break a line at one — so a long phrase in a narrow table cell is cut wherever it
 * runs out ("คอมพิวเต|อร์"). It does break between two inline boxes, so each word is put in its own <span>: the line then breaks
 * between words, where a reader expects it. Words come from ICU's Thai dictionary (the intl extension); without it the text goes
 * out as it is.
 */
final class ThaiText
{
    /** The text, HTML-escaped, with every Thai word in a <span> of its own. */
    public static function words(?string $text): HtmlString
    {
        $text = (string) $text;

        if ($text === '' || ! class_exists(\IntlBreakIterator::class) || ! preg_match('/\p{Thai}/u', $text)) {
            return new HtmlString(e($text));
        }

        $iterator = \IntlBreakIterator::createWordInstance('th_TH');
        $iterator->setText($text);

        $html = '';
        $start = 0;
        foreach ($iterator as $end) {
            if ($end === 0) {
                continue;
            }
            $word = substr($text, $start, $end - $start);
            $start = $end;

            // spaces stay plain text: a span holding only a space would lose it
            $html .= trim($word) === '' ? e($word) : '<span>' . e($word) . '</span>';
        }

        return new HtmlString($html);
    }
}
