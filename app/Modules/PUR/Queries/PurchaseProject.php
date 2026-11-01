<?php

namespace App\Modules\PUR\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\PRJ\Contracts\ProjectPartProvider;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * PG-011's *Pengadaan* (ROUTE-011; TASK-037): the project's purchases with their supplier, state and remaining quantities, to `cost.view` of its company (PJ-18); without it the part is absent. One query (QB-02).
 */
final class PurchaseProject implements ProjectPartProvider
{
    public function key(): string
    {
        return 'procurement';
    }

    public function part(Authority $viewer, int $projectId, int $companyId): ?array
    {
        if (! $viewer->allows('cost.view', $companyId)) {
            return null;
        }

        $rows = DB::table('purchases as x')->leftJoin('purchase_lines as l', 'l.purchase_id', '=', 'x.id')->leftJoin('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')
            ->where('x.project_id', $projectId)->groupBy('x.id', 'x.public_id', 'x.purchase_number', 'x.supplier_snapshot', 'x.state', 'x.business_date')->orderByDesc('x.business_date')->orderByDesc('x.id')->limit(50)
            ->selectRaw("x.public_id, x.purchase_number, x.supplier_snapshot, x.state, x.business_date, coalesce(sum(greatest(b.ordered_qty_base - b.normal_received_net_qty_base - b.dropship_confirmed_net_qty_base - b.closed_qty_base, 0)) filter (where l.kind = 'GOODS'), 0) as remaining")
            ->get();
        $purchases = [];

        foreach ($rows as $row) {
            $supplier = json_decode(Row::text($row, 'supplier_snapshot'), true);
            $purchases[] = ['id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => is_array($supplier) && is_string($supplier['name'] ?? null) ? $supplier['name'] : '', 'state' => Row::text($row, 'state'), 'date' => Row::text($row, 'business_date'), 'remaining' => Row::text($row, 'remaining')];
        }

        return ['purchases' => $purchases, 'can_create' => $viewer->allows('purchase.manage', $companyId)];
    }
}
