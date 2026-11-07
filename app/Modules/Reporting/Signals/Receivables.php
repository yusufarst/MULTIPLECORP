<?php

namespace App\Modules\Reporting\Signals;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The receivable signals' root (PERFORMANCE §7, QS-09–QS-11): a company's `invoice_balances` with an outstanding
 * amount, on the partial index `invoice_balances_ix11_due (company_id, current_due_date, invoice_id)` (DATABASE §20),
 * the invoice ISSUED, its number and its client for the row; RD-04 (`finance.view` in the company).
 */
final class Receivables
{
    /** The invoice's number: its issued document's formatted number (DOC-04, DOC-06), as PG-044 reads it. */
    public const NUMBER = "(select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1)";

    /** An open dispute hold on the invoice, on C-42's partial UNIQUE key. */
    public const HELD = 'exists (select 1 from receivable_dispute_holds h where h.invoice_id = i.id and h.cleared_at is null)';

    public static function outstanding(int $company): Builder
    {
        return DB::table('invoice_balances as b')->join('invoices as i', 'i.id', '=', 'b.invoice_id')
            ->join('client_organizations as o', 'o.id', '=', 'i.client_organization_id')
            ->where('b.company_id', $company)->where('b.outstanding', '>', 0)->where('i.state', 'ISSUED');
    }

    /**
     * The row's columns: the invoice, its client, its outstanding amount and the date it is aged by.
     *
     * @param  literal-string  $at  the column or subquery the row is aged and ordered by
     */
    public static function select(Builder $query, string $at): Builder
    {
        return $query->selectRaw("'i' || i.id as key, b.company_id, coalesce(".self::NUMBER.", '') as subject, o.name as ref, b.outstanding::text as value, null::text as unit, {$at}::text as at, i.public_id::text as target");
    }
}
