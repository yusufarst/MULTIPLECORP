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

        // An open purchase's goods lines still to come, each with its product's base unit — never one figure across products or
        // units —, nothing for a closed or cancelled purchase (CALC-03; DESIGN_SYSTEM §16; TASK-096); still one statement (QB-02).
        $remainder = 'greatest(b.ordered_qty_base - b.normal_received_net_qty_base - b.dropship_confirmed_net_qty_base - b.closed_qty_base, 0)';
        $rows = DB::table('purchases as x')->leftJoin('purchase_lines as l', 'l.purchase_id', '=', 'x.id')->leftJoin('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')
            ->leftJoin('products as pr', 'pr.id', '=', 'l.product_id')->leftJoin('units as u', 'u.id', '=', 'pr.base_unit_id')
            ->where('x.project_id', $projectId)->groupBy('x.id', 'x.public_id', 'x.purchase_number', 'x.supplier_snapshot', 'x.state', 'x.business_date')->orderByDesc('x.business_date')->orderByDesc('x.id')->limit(50)
            ->selectRaw("x.public_id, x.purchase_number, x.supplier_snapshot, x.state, x.business_date, coalesce(json_agg(json_build_object('description', l.description, 'quantity', ({$remainder})::text, 'unit', u.code) order by l.line_no) filter (where x.state = 'OPEN' and l.kind = 'GOODS' and {$remainder} > 0), '[]'::json) as remaining")
            ->get();
        $purchases = [];

        foreach ($rows as $row) {
            $supplier = json_decode(Row::text($row, 'supplier_snapshot'), true);
            $purchases[] = ['id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => is_array($supplier) && is_string($supplier['name'] ?? null) ? $supplier['name'] : '', 'state' => Row::text($row, 'state'), 'date' => Row::text($row, 'business_date'), 'remaining' => self::remainders(Row::text($row, 'remaining'))];
        }

        return ['purchases' => $purchases, 'can_create' => $viewer->allows('purchase.manage', $companyId)];
    }

    /**
     * The lines the query aggregated, each a quantity at its unit as text — never a float.
     *
     * @return list<array{description: string, quantity: string, unit: string}>
     */
    private static function remainders(string $json): array
    {
        $decoded = json_decode($json, true);
        $lines = [];

        foreach (is_array($decoded) ? $decoded : [] as $line) {
            if (is_array($line) && is_string($line['description'] ?? null) && is_string($line['quantity'] ?? null) && is_string($line['unit'] ?? null)) {
                $lines[] = ['description' => $line['description'], 'quantity' => $line['quantity'], 'unit' => $line['unit']];
            }
        }

        return $lines;
    }
}
