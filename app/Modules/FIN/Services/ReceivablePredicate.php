<?php

namespace App\Modules\FIN\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FIN's part of the completion predicates (BR-PRJ-02; BR-FIN-08; L-12; TASK-050), read by TASK-055's AX-25 under the project's LK-01: billing — every issued
 * invoice billed —, and the receivable — no billed invoice with anything outstanding, a dispute hold named as what blocks it; a hold lapses at zero
 * outstanding (L-17), so it is read through the outstanding. Without `finance.view` the same state in plain steps, no count of money items (PJ-13).
 */
final class ReceivablePredicate implements CompletionPredicateProvider
{
    public function predicates(): array
    {
        return [Predicates::BILLING, Predicates::RECEIVABLE];
    }

    /**
     * For each project with an issued invoice, in one set query (QB-01).
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, PredicateState>>
     */
    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('invoices as i')->join('invoice_balances as b', 'b.invoice_id', '=', 'i.id')->whereIn('i.project_id', $projectIds)->where('i.state', 'ISSUED')->groupBy('i.project_id')
            ->selectRaw('i.project_id, count(*) filter (where b.current_billing_act_id is null) as unbilled,
                count(*) filter (where b.current_billing_act_id is not null and b.outstanding > 0) as open,
                count(*) filter (where b.outstanding > 0 and exists (select 1 from receivable_dispute_holds h where h.invoice_id = i.id and h.cleared_at is null)) as disputed')->get();
        $states = [];

        foreach ($rows as $row) {
            $unbilled = (int) Row::text($row, 'unbilled');
            $open = (int) Row::text($row, 'open');
            $disputed = (int) Row::text($row, 'disputed');
            $billing = $unbilled === 0 ? [] : [$money ? new JourneyItem('projects.item.invoices_unbilled', ['jumlah' => (string) $unbilled], 'billing', false, true) : new JourneyItem('projects.next.billing', [], 'billing')];
            $receivable = [];

            if ($disputed > 0) {
                $receivable[] = $money ? new JourneyItem('projects.item.receivables_disputed', ['jumlah' => (string) $disputed], 'payment', true, true) : new JourneyItem('projects.item.receivable_held', [], 'payment', true);
            }

            if ($open > $disputed) {
                $receivable[] = $money ? new JourneyItem('projects.item.receivables_open', ['jumlah' => (string) ($open - $disputed)], 'payment', false, true) : new JourneyItem('projects.next.payment', [], 'payment');
            }

            $states[(int) Row::text($row, 'project_id')] = [Predicates::BILLING => new PredicateState(true, $billing), Predicates::RECEIVABLE => new PredicateState(true, $receivable)];
        }

        return $states;
    }
}
