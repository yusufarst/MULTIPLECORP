<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The purchase report (RD-05; RD-03; CALC-03; CALC-04; J-INV-07; PJ-05; PJ-06): with `cost.view`, each company's
 * purchase lines of the period by the purchase's business date — ordered, received and the remaining receipt
 * (CALC-03), the line's value —, and the inter-company allocations of the period as each side may see them; with
 * `projects.view`, each company's ACTIVE projects' remaining deliveries (CALC-04). A cancelled purchase is no purchase.
 * Three statements.
 */
final class PurchaseReport implements Report
{
    public function code(): string
    {
        return 'pembelian';
    }

    public function opens(Authority $viewer): array
    {
        return $viewer->scope->isEmpty() ? [] : array_values(array_filter(['cost.view', 'projects.view'], $viewer->has(...)));
    }

    public function masking(Authority $viewer): array
    {
        return $viewer->has('stock.view') ? ['stock.view'] : [];
    }

    public function tables(Authority $viewer, Filters $filters): array
    {
        $companies = $filters->companyIds();
        $tables = [];

        if ($viewer->has('cost.view')) {
            $tables[] = $this->receipts($companies, $filters);
        }

        if ($viewer->has('projects.view')) {
            $tables[] = $this->deliveries($companies);
        }

        if ($viewer->has('cost.view')) {
            $tables[] = AllocationTable::of($viewer, $filters);
        }

        return $tables;
    }

    /**
     * CALC-03 per line: ordered less received — normally, by drop-ship confirmation — and closed, for goods only.
     *
     * @param  list<int>  $companies
     */
    private function receipts(array $companies, Filters $filters): Table
    {
        $query = DB::table('purchase_lines as l')->join('purchases as x', 'x.id', '=', 'l.purchase_id')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->whereIn('x.company_id', $companies)->where('x.state', '<>', 'CANCELLED')->whereBetween('x.business_date', [$filters->period->from, $filters->period->to])
            ->select(['l.id', 'x.company_id', 'x.business_date', 'x.purchase_number', DB::raw("x.supplier_snapshot->>'name' as supplier"), 'l.kind', 'l.description', 'p.name as product', 'u.code as unit',
                'b.ordered_qty_base', DB::raw('b.normal_received_net_qty_base + b.dropship_confirmed_net_qty_base as received'),
                DB::raw("case when l.kind = 'GOODS' then greatest(b.ordered_qty_base - b.normal_received_net_qty_base - b.dropship_confirmed_net_qty_base - b.closed_qty_base, 0) else 0 end as remaining"), 'l.line_value']);

        return new Table('penerimaan', [new Column('number'), new Column('date', 'date'), new Column('supplier'), new Column('item'), new Column('unit'),
            new Column('ordered', 'qty'), new Column('received', 'qty'), new Column('remaining', 'qty'), new Column('value', 'money', true)], $companies,
            function () use ($query): iterable {
                foreach (Keyed::rows($query, ['x.company_id' => 'company_id', 'x.business_date' => 'business_date', 'l.id' => 'id']) as $row) {
                    yield [(int) Row::text($row, 'company_id'), [Row::text($row, 'purchase_number'), Row::text($row, 'business_date'), Row::nullable($row, 'supplier'), Row::nullable($row, 'product') ?? Row::nullable($row, 'description'),
                        Row::nullable($row, 'unit'), Row::text($row, 'ordered_qty_base'), Row::text($row, 'received'), Row::text($row, 'remaining'), Row::text($row, 'line_value')]];
                }
            },
            fn (): int => (clone $query)->count());
    }

    /**
     * CALC-04 per line of an ACTIVE project: demand less delivered — or handed over — and cancelled, until formal closure.
     *
     * @param  list<int>  $companies
     */
    private function deliveries(array $companies): Table
    {
        $remaining = 'greatest(b.demand_qty_base - b.delivered_net_qty_base - b.handed_over_qty_base - b.cancelled_qty_base, 0)';
        $query = DB::table('project_lines as l')->join('projects as j', 'j.id', '=', 'l.project_id')->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->whereIn('j.company_id', $companies)->where('j.state', 'ACTIVE')->whereRaw("{$remaining} > 0")
            ->select(['l.id', 'j.company_id', 'j.project_number', 'l.description', 'p.name as product', 'u.code as unit', 'b.demand_qty_base',
                DB::raw('b.delivered_net_qty_base + b.handed_over_qty_base as delivered'), DB::raw("{$remaining} as remaining")]);

        return new Table('pengiriman', [new Column('project'), new Column('item'), new Column('unit'), new Column('demand', 'qty'), new Column('delivered', 'qty'), new Column('remaining', 'qty')], $companies,
            function () use ($query): iterable {
                foreach (Keyed::rows($query, ['j.company_id' => 'company_id', 'l.id' => 'id']) as $row) {
                    yield [(int) Row::text($row, 'company_id'), [Row::text($row, 'project_number'), Row::nullable($row, 'product') ?? Row::nullable($row, 'description'), Row::nullable($row, 'unit'),
                        Row::text($row, 'demand_qty_base'), Row::text($row, 'delivered'), Row::text($row, 'remaining')]];
                }
            },
            fn (): int => (clone $query)->count());
    }
}
