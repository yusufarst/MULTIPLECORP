<?php

namespace Tests\Support\Fixtures;

use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * TASK-062's browser fixture (TS-11; TEST_STRATEGY §7): `ReconcileGuards`' differences between every guard family and
 * its facts, read-only, each as one line, so a critical journey compares them before and after it runs — the journey
 * clean when it adds none. The browser database is shared by every spec of a run, whose fixtures may write guard rows
 * directly; a journey answers for its own writes only.
 */
final class GuardsFixture
{
    public static function differences(): JsonResponse
    {
        $found = [];
        $reconciler = new Reconciler;

        foreach (Families::all() as $family) {
            foreach ($reconciler->differences(DB::connection(), $family) as $difference) {
                $found[] = json_encode($difference, JSON_THROW_ON_ERROR);
            }
        }

        return response()->json(['differences' => $found]);
    }
}
