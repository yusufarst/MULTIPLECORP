<?php

namespace App\Modules\FIN\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FIN's part of the payment and audit predicates (BR-PRJ-02; L-29; WF-PRJ-03): no undisposed contra-fact — a payment correction's case still open on the project —, the project's credit
 * dispositioned — none left for it, or none arisen beyond what its hold covers (WF-FIN-06; DIR-104; TASK-055) —, no pre-payment Kuitansi left unlinked and unvoided (WF-FIN-08), and no money applied to the
 * project's invoices that stands without proof, a payment flagged until evidenced failing the audit predicate. Without `finance.view` the same state in plain steps, no count of money items (PJ-13). One statement for the set of projects, beside the credit's replay (QB-01; PF-24).
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

        // One statement per set of projects: the payments applied, the unevidenced, the open payment corrections, the open pre-payment Kuitansi and the hold (QB-02; PF-24).
        $rows = DB::select(<<<'SQL'
            select pr.id as project_id, pr.credit_held_amount,
                (select count(*) from invoices i join payment_applications a on a.invoice_id = i.id and a.state = 'ACTIVE' where i.project_id = pr.id) as applied,
                (select count(distinct p.id) from invoices i join payment_applications a on a.invoice_id = i.id and a.state = 'ACTIVE' join payments p on p.id = a.payment_id
                    where i.project_id = pr.id and p.unevidenced_reason is not null and p.bank_statement_line_id is null
                        and not exists (select 1 from evidence_links k join evidence_documents e on e.id = k.evidence_document_id where k.payment_id = p.id and k.unlinked_at is null and e.evidence_type in ('PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'))) as unevidenced,
                (select count(*) from correction_cases c where c.project_id = pr.id and c.case_kind = 'PAYMENT_CORRECTION' and c.state = 'OPEN') as corrections,
                (select count(*) from documents d join invoices i on i.id = d.invoice_id where i.project_id = pr.id and d.document_type_code = 'DOC-05' and d.mode = 'PRE_PAYMENT'
                    and exists (select 1 from document_versions v where v.document_id = d.id and v.state = 'ISSUED')
                    and not exists (select 1 from prepayment_kuitansi_links k where k.document_id = d.id and k.state = 'LINKED')) as kuitansi
            from projects pr
            where pr.id = any(?::bigint[])
            SQL, ['{'.implode(',', $projectIds).'}']);
        $credit = CreditProjects::ofProjects($projectIds);
        $states = [];

        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $project = (int) Row::text($row, 'project_id');
            $unevidenced = (int) Row::text($row, 'unevidenced');
            $corrections = (int) Row::text($row, 'corrections');
            $kuitansi = (int) Row::text($row, 'kuitansi');
            $figures = $credit[$project] ?? ['arisen' => '0.00', 'left' => '0.00'];
            // Predicate 9's credit: blocking when something is left and more has arisen than the hold covers — credit arising after a hold blocks again (DIR-104).
            $undisposed = Money::of($figures['left'])->isPositive() && Money::of($figures['arisen'])->compareTo(Money::of(Row::text($row, 'credit_held_amount'))) > 0;
            $payment = [
                ...($corrections === 0 ? [] : [$money ? new JourneyItem('projects.item.payment_corrections', ['jumlah' => (string) $corrections], 'payment', true, true) : new JourneyItem('projects.next.payment_correction', [], 'payment', true)]),
                ...(! $undisposed ? [] : [$money ? new JourneyItem('projects.item.credit_undisposed', ['jumlah' => Money::of($figures['left'])->toString()], 'payment', true, true) : new JourneyItem('projects.next.credit', [], 'payment', true)]),
                // WF-FIN-08: a pre-payment Kuitansi issued for one of the project's invoices, neither voided nor linked to its payment.
                ...($kuitansi === 0 ? [] : [$money ? new JourneyItem('projects.item.kuitansi_open', ['jumlah' => (string) $kuitansi], 'payment', true, true) : new JourneyItem('projects.next.kuitansi', [], 'payment', true)]),
            ];
            $applied = (int) Row::text($row, 'applied') > 0;

            if (! $applied && $payment === []) {
                continue;
            }

            $audit = $unevidenced === 0 ? [] : [$money ? new JourneyItem('projects.item.payments_unevidenced', ['jumlah' => (string) $unevidenced], 'payment', true, true) : new JourneyItem('projects.next.payment_evidence', [], 'payment', true)];
            $states[$project] = [Predicates::PAYMENT => new PredicateState(true, $payment), Predicates::AUDIT => new PredicateState($applied, $audit)];
        }

        return $states;
    }
}
