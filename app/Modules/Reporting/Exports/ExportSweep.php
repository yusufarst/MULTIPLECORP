<?php

namespace App\Modules\Reporting\Exports;

use App\Support\Jobs\OwedWork;
use App\Support\Search\Row;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * JB-09 for the export (CONCURRENCY_IDEMPOTENCY §15; HO-18): `GenerateExport` re-dispatched for a request PENDING longer
 * than 2 minutes while no request is RUNNING — a lost dispatch —, and a request RUNNING for more than 15 minutes since
 * its claim marked FAILED by a conditional update, visible to its requester, who may ask again. The job's claim and
 * the overlap lock make a second dispatch harmless.
 */
final class ExportSweep implements OwedWork
{
    public const PENDING_MINUTES = 2;

    public const STALE_MINUTES = 15;

    public function sweep(): int
    {
        $now = CarbonImmutable::now();
        $acted = DB::table('export_requests')->where('state', 'RUNNING')->where('claimed_at', '<=', $now->subMinutes(self::STALE_MINUTES))->update(['state' => 'FAILED']);

        if (DB::table('export_requests')->where('state', 'RUNNING')->exists()) {
            return $acted;
        }

        foreach (DB::table('export_requests')->where('state', 'PENDING')->where('recorded_at', '<=', $now->subMinutes(self::PENDING_MINUTES))->orderBy('id')->limit(50)->get(['id']) as $request) {
            GenerateExport::dispatch(Row::text($request, 'id'));
            $acted++;
        }

        return $acted;
    }
}
