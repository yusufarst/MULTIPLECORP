<?php

namespace App\Support\Backup;

use Illuminate\Support\Facades\Log;

/**
 * Each run, copy, cleanup and restore logs its outcome truthfully (BK-10; BKR-02): a failure, a skip or a stale state is
 * an alert the operator's failure detection reads (TASK-075), never a success. No key, password or secret is logged.
 */
final class BackupLog
{
    public const ALERT = 'Backup alert (BK-10)';

    /** @param  array<string, mixed>  $context */
    public static function alert(string $what, array $context = []): void
    {
        Log::critical(self::ALERT.': '.$what, $context);
    }

    /** @param  array<string, mixed>  $context */
    public static function outcome(string $what, array $context = []): void
    {
        Log::info('Backup tooling: '.$what, $context);
    }
}
