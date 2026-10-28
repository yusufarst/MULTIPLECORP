<?php

namespace App\Modules\DOC\Services;

use App\Support\Localization\Formats;
use InvalidArgumentException;

/**
 * How the paper writes its values (LOCALIZATION §3.3, §3.4; DESIGN_SYSTEM §16): the application's one value format,
 * Indonesian whatever the interface language — `Rp1.234.567`, quantities exact, dates as `28 Oktober 2026` — through
 * `Formats`, which `resources/js/lib/format.ts` mirrors for the composer; and an amount in words for a Kuitansi.
 */
final class PaperFormat
{
    private const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(string $amount): string
    {
        return Formats::money($amount);
    }

    /** A quantity without its unit, which the paper prints in its own column. */
    public static function quantity(string $quantity): string
    {
        return trim(Formats::quantity($quantity, ''));
    }

    public static function date(string $day): string
    {
        return Formats::dateLong($day);
    }

    /** An amount of money in words, as a Kuitansi states it: "Satu juta dua ratus ribu rupiah". */
    public static function words(string $amount): string
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $amount, $parts) !== 1) {
            throw new InvalidArgumentException("{$amount} is not a decimal string.");
        }

        $whole = (int) $parts[2];
        $cents = (int) str_pad(substr($parts[3] ?? '', 0, 2), 2, '0');
        $text = ($parts[1] === '-' ? 'minus ' : '').($whole === 0 ? 'nol' : self::spell($whole)).' rupiah'.($cents > 0 ? ' '.self::spell($cents).' sen' : '');

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private static function spell(int $number): string
    {
        return trim(match (true) {
            $number < 12 => self::UNITS[$number],
            $number < 20 => self::UNITS[$number - 10].' belas',
            $number < 100 => self::UNITS[intdiv($number, 10)].' puluh '.self::spell($number % 10),
            $number < 200 => 'seratus '.self::spell($number - 100),
            $number < 1_000 => self::UNITS[intdiv($number, 100)].' ratus '.self::spell($number % 100),
            $number < 2_000 => 'seribu '.self::spell($number - 1_000),
            $number < 1_000_000 => self::spell(intdiv($number, 1_000)).' ribu '.self::spell($number % 1_000),
            $number < 1_000_000_000 => self::spell(intdiv($number, 1_000_000)).' juta '.self::spell($number % 1_000_000),
            $number < 1_000_000_000_000 => self::spell(intdiv($number, 1_000_000_000)).' miliar '.self::spell($number % 1_000_000_000),
            default => self::spell(intdiv($number, 1_000_000_000_000)).' triliun '.self::spell($number % 1_000_000_000_000),
        });
    }
}
