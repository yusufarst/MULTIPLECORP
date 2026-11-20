<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether the application can answer a request (INT-277): its database reached with one trivial statement, inside the
 * runtime's default statement timeout (HO-21). The reason of a failure stays out of the answer (SX-03).
 */
class HealthProbe
{
    public function answers(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
