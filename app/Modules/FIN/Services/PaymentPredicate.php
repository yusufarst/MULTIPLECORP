<?php

namespace App\Modules\FIN\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * FIN's part of the payment and audit predicates (BR-PRJ-02; L-29; WF-PRJ-03): no undisposed contra-fact — a payment correction's case still open on the project —, the project's credit
 * dispositioned — none left for it, or none arisen beyond what its hold covers (WF-FIN-06; DIR-104; TASK-055) —, no pre-payment Kuitansi left unlinked and unvoided (WF-FIN-08), and no money applied to the
 * project's invoices that stands without proof, a payment flagged until evidenced failing the audit predicate. Without `finance.view` the same state in plain steps, no count of money items (PJ-13). One set query (QB-01).
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

        // Predicate 9's credit: blocking when something is left and more has arisen than the hold covers — credit arising after a hold blocks again (DIR-104).
        $held = DB::table('projects')->whereIn('id', $projectIds)->pluck('credit_held_amount', 'id')->all();

        foreach (CreditProjects::ofProjects($projectIds) as $project => $figures) {
            $covered = Money::of(is_scalar($held[$project] ?? null) ? (string) $held[$project] : '0');

            if (Money::of($figures['left'])->isPositive() && Money::of($figures['arisen'])->compareTo($covered) > 0) {
                $states = self::with($states, $project, $money ? new JourneyItem('projects.item.credit_undisposed', ['jumlah' => Money::of($figures['left'])->toString()], 'payment', true, true) : new JourneyItem('projects.next.credit', [], 'payment', true));
            }
        }

        // WF-FIN-08: a pre-payment Kuitansi issued for one of the project's invoices, neither voided nor linked to its payment.
        $open = DB::table('documents as d')->join('invoices as i', 'i.id', '=', 'd.invoice_id')->whereIn('i.project_id', $projectIds)->where('d.document_type_code', 'DOC-05')->where('d.mode', 'PRE_PAYMENT')
            ->whereExists(fn (Builder $version) => $version->from('document_versions as v')->whereColumn('v.document_id', 'd.id')->where('v.state', 'ISSUED'))
            ->whereNotExists(fn (Builder $link) => $link->from('prepayment_kuitansi_links as k')->whereColumn('k.document_id', 'd.id')->where('k.state', 'LINKED'))
            ->groupBy('i.project_id')->selectRaw('i.project_id, count(*) as open')->get();

        foreach ($open as $row) {
            $states = self::with($states, (int) Row::text($row, 'project_id'), $money ? new JourneyItem('projects.item.kuitansi_open', ['jumlah' => Row::text($row, 'open')], 'payment', true, true) : new JourneyItem('projects.next.kuitansi', [], 'payment', true));
        }

        return $states;
    }

    /**
     * The project's payment predicate with one more blocker.
     *
     * @param  array<int, array<string, PredicateState>>  $states
     * @return array<int, array<string, PredicateState>>
     */
    private static function with(array $states, int $project, JourneyItem $item): array
    {
        $known = $states[$project][Predicates::PAYMENT] ?? null;
        $states[$project][Predicates::PAYMENT] = new PredicateState(true, [...($known === null ? [] : $known->items), $item]);

        return $states;
    }
}
