<?php

namespace App\Modules\FIN\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FIN's part of the payment and audit predicates (BR-PRJ-02; L-29; WF-PRJ-03): no undisposed contra-fact — a payment correction's case still open on the project —, and no money applied to the project's invoices that stands without proof, a payment flagged until evidenced
 * failing the audit predicate. Without `finance.view` the same state in plain steps, no count of money items (PJ-13). One set query (QB-01).
 */
final class PaymentPredicate implements CompletionPredicateProvider
{
    public function predicates(): array
    {
        return [Predicates::PAYMENT, Predicates::AUDIT];
    }

    /**
     * @param  list<int>  $projectIds
     * @return array<int, array<string, PredicateState>>
     */
    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::select(<<<'SQL'
            select i.project_id, count(distinct p.id) as paid,
                count(distinct p.id) filter (where p.unevidenced_reason is not null and p.bank_statement_line_id is null and not exists (select 1 from evidence_links k join evidence_documents e on e.id = k.evidence_document_id where k.payment_id = p.id and k.unlinked_at is null and e.evidence_type in ('PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'))) as unevidenced,
                (select count(*) from correction_cases c where c.project_id = i.project_id and c.case_kind = 'PAYMENT_CORRECTION' and c.state = 'OPEN') as corrections
            from invoices i join payment_applications a on a.invoice_id = i.id and a.state = 'ACTIVE' join payments p on p.id = a.payment_id
            where i.project_id = any(?::bigint[])
            group by i.project_id
            SQL, ['{'.implode(',', $projectIds).'}']);
        $states = [];

        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $unevidenced = (int) Row::text($row, 'unevidenced');
            $corrections = (int) Row::text($row, 'corrections');
            $payment = $corrections === 0 ? [] : [$money ? new JourneyItem('projects.item.payment_corrections', ['jumlah' => (string) $corrections], 'payment', true, true) : new JourneyItem('projects.next.payment_correction', [], 'payment', true)];
            $audit = $unevidenced === 0 ? [] : [$money ? new JourneyItem('projects.item.payments_unevidenced', ['jumlah' => (string) $unevidenced], 'payment', true, true) : new JourneyItem('projects.next.payment_evidence', [], 'payment', true)];
            $states[(int) Row::text($row, 'project_id')] = [Predicates::PAYMENT => new PredicateState(true, $payment), Predicates::AUDIT => new PredicateState(true, $audit)];
        }

        return $states;
    }
}
