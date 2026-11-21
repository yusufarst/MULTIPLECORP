<?php

namespace App\Support\Backup;

/**
 * The disk-pressure rule of BACKUP_RECOVERY §3 (BK-11): below `<DISK_WARN_PCT>` of used space a run starts; at the
 * warning level it starts and the operator is alerted; at `<DISK_CRIT_PCT>` no new generation starts unless the space
 * for it is free beside the retained verified generations — the skipped run logged and alerted, never successful, and
 * nothing deleted to make room. The thresholds are lowered to test it, never the shared disk filled (INF-07).
 */
final readonly class DiskPressure
{
    public const OK = 'OK';

    public const WARNING = 'WARNING';

    public const CRITICAL = 'CRITICAL';

    /** @param  int  $usedPct  the used share of the file system, in whole percent */
    public function __construct(public string $level, public bool $mayStart, public int $usedPct, public int $freeBytes, public int $neededBytes) {}

    public static function assess(int $totalBytes, int $freeBytes, int $warnPct, int $criticalPct, int $neededBytes): self
    {
        $usedPct = $totalBytes > 0 ? intdiv(($totalBytes - $freeBytes) * 100 + $totalBytes - 1, $totalBytes) : 100;
        $level = $usedPct >= $criticalPct ? self::CRITICAL : ($usedPct >= $warnPct ? self::WARNING : self::OK);
        $fits = $freeBytes > $neededBytes;

        return new self($level, $level !== self::CRITICAL ? $fits : $fits && $neededBytes > 0, $usedPct, $freeBytes, $neededBytes);
    }
}
