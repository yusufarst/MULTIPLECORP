<?php

namespace Tests\Support\Fixtures;

use App\Modules\Reporting\Exports\GenerateExport;
use App\Support\Search\Row;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TASK-060's browser fixture (TEST_STRATEGY §7): the local stack runs no queue worker for the browser suite, so the
 * signed-in account's PENDING export requests are generated here by the job itself, synchronously, as a worker would —
 * the same claim, re-authorization, snapshot and file (JB-03). Only the account's own requests.
 */
final class ExportFixture
{
    public static function run(Request $request): JsonResponse
    {
        $user = $request->user()?->getAuthIdentifier();
        $ran = 0;

        foreach (DB::table('export_requests')->where('requested_by', is_int($user) ? $user : 0)->where('state', 'PENDING')->orderBy('id')->get(['id']) as $export) {
            dispatch_sync(new GenerateExport(Row::text($export, 'id')));
            $ran++;
        }

        return response()->json(['ran' => $ran]);
    }
}
