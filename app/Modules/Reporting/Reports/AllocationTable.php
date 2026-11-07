<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The inter-company allocation report (J-INV-07; RD-07; DATABASE §6.5; PJ-05; PJ-06): every cost row of the period
 * drawn from one company's lot for another company, of a side among the report's companies, each side seeing what it
 * may — the source side the draw with the bearer, its project, reason and actor masked; the bearer side the cost in
 * full with the source masked —, unless the other company is among them too; the lot's code to holders of
 * `stock.view` only. The Owner, every company in view, sees both sides. A row stands in its bearer's section when the
 * bearer is among the companies, else in its source's; on PG-052 the report is one section of every company's rows.
 */
final class AllocationTable
{
    public static function of(Authority $viewer, Filters $filters, bool $pooled = false): Table
    {
        $companies = $filters->companyIds();
        $stock = $viewer->has('stock.view');
        $inner = DB::table('cost_attributions as a')
            ->where(fn (Builder $sides) => $sides->whereIn('a.source_company_id', $companies)->orWhereIn('a.bearer_company_id', $companies))
            ->whereColumn('a.source_company_id', '<>', 'a.bearer_company_id')
            ->whereBetween('a.business_date', [$filters->period->from, $filters->period->to])
            ->join('stock_consumptions as c', 'c.id', '=', 'a.stock_consumption_id')->join('inventory_lots as l', 'l.id', '=', 'a.lot_id')
            ->join('products as p', 'p.id', '=', 'c.product_id')->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->leftJoin('projects as j', 'j.id', '=', 'a.bearer_project_id')->leftJoin('users as w', 'w.id', '=', 'c.allocation_authorized_by')
            ->select(['a.id', 'a.cost_class', 'a.amount', 'a.business_date', 'a.source_company_id', 'a.bearer_company_id', 'a.allocation_reason', 'c.qty_base',
                'p.name as product', 'u.code as unit', 'l.lot_code', 'j.project_number', 'w.name as authorized_by',
                DB::raw($pooled ? '0 as section' : 'case when a.bearer_company_id = any (?::bigint[]) then a.bearer_company_id else a.source_company_id end as section')]);

        if (! $pooled) {
            $inner->addBinding('{'.implode(',', $companies).'}', 'select');
        }

        $query = DB::query()->fromSub($inner, 'q')->select('q.*');

        $columns = [new Column('date', 'date'), new Column('product'), new Column('unit'), new Column('quantity', 'qty'), new Column('lot'), new Column('source'), new Column('bearer'),
            new Column('project'), new Column('class', 'label'), new Column('amount', 'money', true), new Column('reason'), new Column('authorized_by')];

        return new Table('alokasi', $columns, $pooled ? null : $companies,
            function () use ($query, $filters, $stock, $pooled): iterable {
                foreach (Keyed::rows($query, $pooled ? ['q.business_date' => 'business_date', 'q.id' => 'id'] : ['q.section' => 'section', 'q.business_date' => 'business_date', 'q.id' => 'id']) as $row) {
                    $source = $filters->code((int) Row::text($row, 'source_company_id'));
                    $bearer = $filters->code((int) Row::text($row, 'bearer_company_id'));
                    yield [$pooled ? null : (int) Row::text($row, 'section'), [
                        Row::text($row, 'business_date'), Row::text($row, 'product'), Row::text($row, 'unit'), Row::text($row, 'qty_base'),
                        $stock ? Row::text($row, 'lot_code') : null,
                        // PJ-06: the source company only when it is among the report's companies too.
                        $source,
                        // PJ-05: the bearer, its project, reason and actor only when the bearer company is among them too.
                        $bearer,
                        $bearer === null ? null : Row::nullable($row, 'project_number'),
                        'pages.pg_051.class.'.strtolower(Row::text($row, 'cost_class')),
                        Row::text($row, 'amount'),
                        $bearer === null ? null : Row::nullable($row, 'allocation_reason'),
                        $bearer === null ? null : Row::nullable($row, 'authorized_by'),
                    ]];
                }
            },
            fn (): int => (clone $query)->count());
    }
}
