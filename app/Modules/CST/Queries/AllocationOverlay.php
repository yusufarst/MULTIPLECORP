<?php

namespace App\Modules\CST\Queries;

use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * C-28's data-quality check over the inter-company allocation overlay (DATABASE §18; §14; BR-INV-05; L-44): a cost row
 * whose source differs from its bearer without its reason or apart from its consumption's bearer — a RETURN_SETTLEMENT
 * re-attribution excepted —, a cross-company consumption without its reason, and a cross-company consumption with no
 * unit still out whose cost rows do not net to zero, so net allocation = net cross-company consumption. A system check
 * for the reconciliation suite, read by no viewer: empty while the overlay holds.
 */
final class AllocationOverlay
{
    private const VIOLATIONS = <<<'SQL'
        select 'cost_attributions:' || a.id as violation
        from cost_attributions a join stock_consumptions c on c.id = a.stock_consumption_id
        where a.source_company_id <> a.bearer_company_id and a.cause <> 'RETURN_SETTLEMENT'
            and (a.allocation_reason is null or a.bearer_company_id <> c.bearer_company_id or a.bearer_project_id is distinct from c.bearer_project_id)
        union all
        select 'stock_consumptions:' || c.id from stock_consumptions c where c.source_company_id <> c.bearer_company_id and c.allocation_reason is null
        union all
        select 'stock_consumptions:' || c.id
        from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id
        where c.source_company_id <> c.bearer_company_id and b.qty_base - b.restored_qty_base - b.substituted_qty_base = 0
            and coalesce((select sum(a.amount) from cost_attributions a where a.stock_consumption_id = c.id), 0) <> 0
        order by 1
        SQL;

    /** @return list<string> each violating row as "table:id" */
    public static function violations(): array
    {
        $found = [];

        foreach (DB::select(self::VIOLATIONS) as $row) {
            if (is_object($row)) {
                $found[] = Row::text($row, 'violation');
            }
        }

        return $found;
    }
}
