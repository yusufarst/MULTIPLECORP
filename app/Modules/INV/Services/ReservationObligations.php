<?php

namespace App\Modules\INV\Services;

use App\Modules\PRJ\Contracts\ResidualObligationProvider;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * INV's residual obligations (WF-PRJ-03; BR-PRJ-04): each active reservation of the project's lines, release-required after a Force Complete, named by its
 * product and what it still holds. One set query (QB-01).
 */
final class ReservationObligations implements ResidualObligationProvider
{
    public function obligations(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('products as p', 'p.id', '=', 'r.product_id')->whereIn('l.project_id', $projectIds)->where('r.state', 'ACTIVE')
            ->orderBy('r.id')->get(['r.id', 'l.project_id', 'p.name', 'r.remaining_qty_base']);
        $obligations = [];

        foreach ($rows as $row) {
            $obligations[(int) Row::text($row, 'project_id')][] = ['kind' => 'reservation', 'ref' => RowHandle::of('reservations', (int) Row::text($row, 'id')), 'label' => Row::text($row, 'name'), 'amount' => null, 'quantity' => Row::text($row, 'remaining_qty_base')];
        }

        return $obligations;
    }
}
