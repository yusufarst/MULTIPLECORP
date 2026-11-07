<?php

namespace App\Modules\Reporting\Exports;

/**
 * FL-09's text-cell rule (TM-16; TM-46; OWASP CSV Injection): every text cell whose first character is `=`, `+`, `-`,
 * `@`, a tab, a carriage return, a line feed or the full-width variant of one of the first four is prefixed with a
 * single quote, so a spreadsheet never reads it as a formula; characters XML cannot hold are dropped. Numbers are
 * written as numbers and never pass through here.
 */
final class CellText
{
    private const LEADING = ['=', '+', '-', '@', "\t", "\r", "\n", "\u{FF1D}", "\u{FF0B}", "\u{FF0D}", "\u{FF20}"];

    public static function neutral(string $text): string
    {
        $text = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
        $first = mb_substr($text, 0, 1);

        return $first !== '' && in_array($first, self::LEADING, true) ? "'".$text : $text;
    }
}
