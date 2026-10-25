<?php

namespace App\Support\Localization;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The value formats of LOCALIZATION §3.3 and DESIGN_SYSTEM §16, one way in both languages — the English
 * interface translates labels, never values (OQ-04 (a)) —, for what the server writes: generated documents,
 * exports and the values inside server messages. Money and quantities arrive as exact decimal strings and are
 * never rounded; dates and times are WIB (BR-DT-06). `resources/js/lib/format.ts` renders the same formats in
 * the interface, both held to `tests/Fixtures/Localization/formats.json`.
 */
final class Formats
{
    public const TIMEZONE = 'Asia/Jakarta';

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const MONTHS_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** The minus sign before "Rp" (U+2212). */
    private const MINUS = "\u{2212}";

    /** Rupiah: "Rp1.250.000", with sen "Rp1.250.000,50" — or always two decimals in a column where any value has sen. */
    public static function money(string $amount, bool $withSen = false): string
    {
        [$negative, $whole, $fraction] = self::decimal($amount);

        if (strlen(rtrim($fraction, '0')) > 2) {
            throw new InvalidArgumentException('Rupiah has at most two decimals.');
        }

        $sen = str_pad(substr($fraction, 0, 2), 2, '0');

        return ($negative ? self::MINUS : '').'Rp'.self::thousands($whole).($withSen || $sen !== '00' ? ','.$sen : '');
    }

    /** A quantity, exact, then its unit, the alternate unit as a hint: "3,5 kg", "120 rim (24 dus)". */
    public static function quantity(string $value, string $unit, ?string $alternateValue = null, ?string $alternateUnit = null): string
    {
        $text = self::exact($value).' '.$unit;

        return $alternateValue === null || $alternateUnit === null ? $text : $text.' ('.self::exact($alternateValue).' '.$alternateUnit.')';
    }

    /** A percentage or rate: "11%", "0,25%". */
    public static function percent(string $value): string
    {
        return self::exact($value).'%';
    }

    /** A date: "02 Okt 2026". */
    public static function date(string $date): string
    {
        [$year, $month, $day] = self::day($date);

        return sprintf('%02d %s %d', $day, self::MONTHS[$month - 1], $year);
    }

    /** A date in a sentence or a document: "2 Oktober 2026". */
    public static function dateLong(string $date): string
    {
        [$year, $month, $day] = self::day($date);

        return sprintf('%d %s %d', $day, self::MONTHS_LONG[$month - 1], $year);
    }

    /** A time on the 24-hour clock in WIB: "14.05 WIB". */
    public static function time(string $instant): string
    {
        return self::wib($instant)->format('H.i').' WIB';
    }

    /** A date with its time: "02 Okt 2026 14.05 WIB". */
    public static function dateTime(string $instant): string
    {
        return self::date(self::wib($instant)->format('Y-m-d')).' '.self::time($instant);
    }

    /** A period: "Okt 2026" for a month (`Y-m`), "2026" for a year. */
    public static function period(string $period): string
    {
        if (preg_match('/^\d{4}$/', $period) === 1) {
            return $period;
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $match) !== 1 || (int) $match[2] < 1 || (int) $match[2] > 12) {
            throw new InvalidArgumentException('A period is a year or a year and month.');
        }

        return self::MONTHS[(int) $match[2] - 1].' '.$match[1];
    }

    /** A decimal written exactly — trailing zeros of its fraction dropped, nothing rounded. */
    private static function exact(string $value): string
    {
        [$negative, $whole, $fraction] = self::decimal($value);
        $fraction = rtrim($fraction, '0');

        return ($negative ? self::MINUS : '').self::thousands($whole).($fraction === '' ? '' : ','.$fraction);
    }

    /**
     * @return array{0: bool, 1: string, 2: string} the sign, the whole digits and the fraction digits
     */
    private static function decimal(string $value): array
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $match) !== 1) {
            throw new InvalidArgumentException('A value is an exact decimal string.');
        }

        $whole = ltrim($match[2], '0');
        $fraction = $match[3] ?? '';

        return [$match[1] === '-' && ($whole !== '' || trim($fraction, '0') !== ''), $whole === '' ? '0' : $whole, $fraction];
    }

    private static function thousands(string $digits): string
    {
        return strrev(implode('.', str_split(strrev($digits), 3)));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function day(string $date): array
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match) !== 1 || ! checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            throw new InvalidArgumentException('A date is a Y-m-d day.');
        }

        return [(int) $match[1], (int) $match[2], (int) $match[3]];
    }

    private static function wib(string $instant): CarbonImmutable
    {
        return CarbonImmutable::parse($instant)->setTimezone(self::TIMEZONE);
    }
}
