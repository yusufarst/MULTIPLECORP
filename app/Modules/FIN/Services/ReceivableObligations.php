<?php

namespace App\Modules\FIN\Services;

use App\Modules\PRJ\Contracts\ResidualObligationProvider;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FIN's residual obligations (WF-PRJ-03; BR-PRJ-04; AC-21): each issued invoice of the project with something outstanding, which stays active and ageing
 * after a Force Complete, named by its number and its outstanding. One set query (QB-01).
 */
final class ReceivableObligations implements ResidualObligationProvider
{
    public function obligations(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('invoices as i')->join('invoice_balances as b', 'b.invoice_id', '=', 'i.id')->whereIn('i.project_id', $projectIds)->where('i.state', 'ISSUED')->where('b.outstanding', '>', 0)
            ->orderBy('i.id')->selectRaw("i.project_id, i.public_id, b.outstanding,
                (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.id desc limit 1) as number")->get();
        $obligations = [];

        foreach ($rows as $row) {
            $obligations[(int) Row::text($row, 'project_id')][] = ['kind' => 'receivable', 'ref' => Row::text($row, 'public_id'), 'label' => Row::nullable($row, 'number') ?? '', 'amount' => Row::text($row, 'outstanding'), 'quantity' => null];
        }

        return $obligations;
    }
}
