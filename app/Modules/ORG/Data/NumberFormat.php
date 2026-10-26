<?php

namespace App\Modules\ORG\Data;

use InvalidArgumentException;

/**
 * A numbering scheme's `format_template` (DATABASE §4.2; GAP-019), as the Owner decided its tokens (DIR-098 D1):
 * free text with `{nomor}` exactly once — `{nomor:N}` padding the number to N digits —, `{tahun}` (four digits),
 * `{tahun2}` (two digits), and, in a monthly scheme only, `{bulan}` (01–12) and `{bulan_romawi}` (I–XII); no other
 * brace; a year token always, and a month token too in a monthly scheme (DIR-098 D1a). A number is formatted once,
 * at its draw, and stored (NM-07).
 */
final class NumberFormat
{
    public const MAXIMUM_LENGTH = 60;

    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** The reason a template is refused, a catalogue key, or null when it is valid for the granularity. */
    public static function problem(string $template, string $granularity): ?string
    {
        if ($template === '' || mb_strlen($template) > self::MAXIMUM_LENGTH || preg_match('/[\x00-\x1F\x7F]/u', $template) === 1) {
            return 'numbering.format.length';
        }

        $tokens = preg_match_all('/\{([^{}]*)\}/', $template, $matches);
        $rest = preg_replace('/\{[^{}]*\}/', '', $template);

        if (str_contains((string) $rest, '{') || str_contains((string) $rest, '}')) {
            return 'numbering.format.brace';
        }

        $numbers = 0;
        $year = false;
        $month = false;

        foreach ($tokens > 0 ? $matches[1] : [] as $token) {
            if (preg_match('/^nomor(:[1-9])?$/', $token) === 1) {
                $numbers++;
            } elseif (in_array($token, ['bulan', 'bulan_romawi'], true)) {
                if ($granularity !== 'MONTH') {
                    return 'numbering.format.month_in_year';
                }

                $month = true;
            } elseif (in_array($token, ['tahun', 'tahun2'], true)) {
                $year = true;
            } else {
                return 'numbering.format.token';
            }
        }

        // DIR-098 D1a: each period's numbers differ from every other's — the counter restarts per period, so a format
        // without the period's tokens would repeat a number C-10 holds unique (NM-09).
        return match (true) {
            $numbers !== 1 => 'numbering.format.number',
            ! $year || ($granularity === 'MONTH' && ! $month) => 'numbering.format.period',
            default => null,
        };
    }

    /** The formatted number of a sequence number in its period. */
    public static function format(string $template, string $periodKey, int $sequenceNo): string
    {
        if (preg_match('/^(\d{4})(?:-(\d{2}))?$/', $periodKey, $period) !== 1) {
            throw new InvalidArgumentException("{$periodKey} is not a period key (YYYY or YYYY-MM).");
        }

        $month = isset($period[2]) ? (int) $period[2] : null;

        return (string) preg_replace_callback('/\{([^{}]*)\}/', function (array $token) use ($period, $month, $sequenceNo, $periodKey): string {
            return match (true) {
                $token[1] === 'nomor' => (string) $sequenceNo,
                str_starts_with($token[1], 'nomor:') => str_pad((string) $sequenceNo, (int) substr($token[1], 6), '0', STR_PAD_LEFT),
                $token[1] === 'tahun' => $period[1],
                $token[1] === 'tahun2' => substr($period[1], 2),
                $token[1] === 'bulan' && $month !== null => sprintf('%02d', $month),
                $token[1] === 'bulan_romawi' && $month !== null => self::ROMAN[$month - 1],
                default => throw new InvalidArgumentException("The token {{$token[1]}} cannot format the period {$periodKey}."),
            };
        }, $template);
    }
}
