<?php

namespace App\Modules\Reporting\Reports;

use Illuminate\Support\Facades\DB;

/**
 * One read-only REPEATABLE READ snapshot (TX-10 for a report on screen, TX-05 for an export; PF-27; PF-28): every
 * statement inside sees the same committed instant, so a report's totals agree; no row lock is taken (PF-12). Inside an
 * open transaction — a test's — the reads share it.
 */
final class Snapshot
{
    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    public static function read(callable $read): mixed
    {
        if (DB::connection()->transactionLevel() > 0) {
            return $read();
        }

        return DB::transaction(function () use ($read): mixed {
            DB::statement('set transaction isolation level repeatable read read only');

            return $read();
        });
    }
}
