<?php

namespace App\Modules\FIN\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\FIN\Data\SettlementEntry;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-047 *Potongan* (ROUTE-047; NAV-047; SCR-43; WF-FIN-04; J-FIN-04): by branch, the settlements newest first by keyset with their payment, invoice, evidence and state, and the payments whose claimed deductions wait for evidence —
 * QS-13's claimed − evidenced, stated per payment, never a total across companies (CS-07; CS-08). What a settlement cites shows only with `evidence.view` (PJ-08); the contra and the resolution are absent without their capability
 * (AZ-01). In at most four statements (QB-13).
 */
final class DeductionList
{
    public const PAGE = 50;

    public const TABS = ['semua', 'sisa'];

    /** QS-13's bound (PFM-07): the oldest payments waiting, each named. */
    public const WAITING = 50;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $tab, ?string $after, ?string $recorded, ?string $opening = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $tab = in_array($tab, self::TABS, true) ? $tab : 'semua';
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('settlement.record', (int) Row::text($company, 'id'))) {
                $branches[(int) Row::text($company, 'id')] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name')];
            }
        }

        $chosen = null;

        foreach ($branches as $id => $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $id;
            }
        }

        $companies = $chosen !== null ? [$chosen] : array_keys($branches);
        $cursor = is_string($after) && $after !== '';
        $page = $companies === [] || ($cursor && ! RowHandle::valid((string) $after)) ? ['rows' => [], 'more' => null] : $this->page($viewer, $companies, $tab, $cursor ? (string) $after : null, $branches);
        $failed = $cursor && $page['rows'] === [];

        return [
            'branches' => array_values($branches),
            'branch' => $chosen === null ? null : $branches[$chosen]['id'],
            'tab' => $tab,
            ...$page,
            'after' => $cursor && ! $failed ? (string) $after : null,
            'cursor_failed' => $failed,
            'waiting' => $companies === [] ? [] : self::waiting($companies, $branches, is_string($opening) && preg_match(self::UUID, $opening) === 1 ? strtolower($opening) : null),
            'recorded' => is_string($recorded) && RowHandle::valid($recorded) ? $recorded : null,
            'today' => BusinessDate::today(),
        ];
    }

    /**
     * What the form offers for one payment (ROUTE-255): its claims not yet evidenced, the client's open invoices, the evidence of the company with room.
     *
     * @return array{claims: list<array<string, mixed>>, invoices: list<array<string, mixed>>, evidence: list<array<string, mixed>>, payments: list<array<string, string|null>>, can_upload: bool}|null
     */
    public function form(int $viewerId, string $paymentPublicId): ?array
    {
        $viewer = $this->capabilities->for($viewerId);
        $payment = preg_match(self::UUID, $paymentPublicId) === 1 ? DB::table('payments')->where('public_id', strtolower($paymentPublicId))->first(['id', 'company_id', 'client_organization_id']) : null;

        if ($payment === null || ! $viewer->allows('settlement.record', (int) Row::text($payment, 'company_id'))) {
            return null;
        }

        $company = (int) Row::text($payment, 'company_id');

        return [
            'claims' => self::claims((int) Row::text($payment, 'id')),
            'invoices' => PaymentList::openInvoices($company, (int) Row::text($payment, 'client_organization_id')),
            'evidence' => $viewer->allows('evidence.view', $company) ? self::evidence($company) : [],
            'payments' => self::candidates($company, (int) Row::text($payment, 'client_organization_id')),
            'can_upload' => EvidenceTypes::mayHandle($viewer, 'FEE_ADVICE', $company),
        ];
    }

    /**
     * The client's standing payments with a claim left, what a residual may be re-recorded against (DATABASE §11.4; C-22; C-45).
     *
     * @return list<array{id: string, date: string, reference: ?string, room: string}>
     */
    private static function candidates(int $companyId, int $clientId): array
    {
        $rows = [];

        foreach (DB::table('application_source_balances as b')->join('payments as p', 'p.id', '=', 'b.payment_id')->where('b.company_id', $companyId)->where('b.client_organization_id', $clientId)->where('b.net_amount', '>', 0)->whereColumn('b.claimed_deductions_amount', '>', 'b.settled_amount')
            ->orderByDesc('p.id')->limit(self::WAITING)->selectRaw('p.public_id, p.business_date, p.reference, b.claimed_deductions_amount - b.settled_amount as room')->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'date' => substr(Row::text($row, 'business_date'), 0, 10), 'reference' => Row::nullable($row, 'reference'), 'room' => Row::text($row, 'room')];
        }

        return $rows;
    }

    /**
     * The payment's current claims — each chain's head — with what is settled of the type.
     *
     * @return list<array{handle: string, class: string, type: ?string, fee_type: ?string, claimed: string}>
     */
    private static function claims(int $paymentId): array
    {
        $rows = [];

        foreach (DB::table('payment_claimed_deductions as c')->leftJoin('tax_types as t', 't.id', '=', 'c.tax_type_id')->where('c.payment_id', $paymentId)
            ->whereNotExists(fn (Builder $next) => $next->from('payment_claimed_deductions as n')->whereColumn('n.supersedes_claimed_deduction_id', 'c.id'))
            ->orderBy('c.id')->get(['c.id', 'c.deduction_class', 'c.fee_type', 'c.claimed_amount', 't.code', 't.name']) as $row) {
            $rows[] = ['handle' => RowHandle::of('payment_claimed_deductions', (int) Row::text($row, 'id')), 'class' => Row::text($row, 'deduction_class'), 'type' => Row::nullable($row, 'name') ?? Row::nullable($row, 'code'), 'fee_type' => Row::nullable($row, 'fee_type'), 'claimed' => Row::text($row, 'claimed_amount')];
        }

        return $rows;
    }

    /**
     * The company's deduction evidence with room, by the type a settlement takes (C-18; C-58).
     *
     * @return list<array{id: string, type: string, label: string, uncited: string}>
     */
    private static function evidence(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('evidence_documents as e')->join('evidence_balances as b', 'b.evidence_document_id', '=', 'e.id')->where('e.company_id', $companyId)
            ->whereIn('e.evidence_type', ['FEE_ADVICE', 'TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF'])->whereColumn('b.cited_amount', '<', 'b.proven_amount')->orderByDesc('e.id')->limit(100)
            ->selectRaw('e.public_id, e.evidence_type, e.declared_issuer, e.issuer_reference, e.document_date, b.proven_amount - b.cited_amount as uncited')->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'label' => trim(Row::text($row, 'declared_issuer').' · '.(Row::nullable($row, 'issuer_reference') ?? Row::text($row, 'document_date'))), 'uncited' => Row::text($row, 'uncited')];
        }

        return $rows;
    }

    /**
     * QS-13: the payments whose claimed deductions exceed what is settled, oldest first, one bounded statement (PFM-07); the one a page opened with first, so the bound never hides it.
     *
     * @param  list<int>  $companies
     * @param  array<int, array{id: string, code: string, name: string}>  $branches
     * @return list<array{payment: string, company: string, client: string, date: string, reference: ?string, claimed: string, settled: string, waiting: string}>
     */
    public static function waiting(array $companies, array $branches, ?string $opening = null): array
    {
        $rows = [];

        foreach (DB::table('application_source_balances as b')->join('payments as p', 'p.id', '=', 'b.payment_id')->join('client_organizations as c', 'c.id', '=', 'p.client_organization_id')
            ->whereIn('b.company_id', $companies)->whereColumn('b.claimed_deductions_amount', '>', 'b.settled_amount')->where('b.net_amount', '>', 0)->orderByRaw('p.public_id::text = ? desc', [$opening ?? ''])->orderBy('p.business_date')->orderBy('p.id')->limit(self::WAITING)
            ->get(['p.public_id', 'p.company_id', 'p.business_date', 'p.reference', 'c.name', 'b.claimed_deductions_amount', 'b.settled_amount']) as $row) {
            $claimed = Row::text($row, 'claimed_deductions_amount');
            $settled = Row::text($row, 'settled_amount');
            $rows[] = ['payment' => Row::text($row, 'public_id'), 'company' => $branches[(int) Row::text($row, 'company_id')]['code'] ?? '', 'client' => Row::text($row, 'name'), 'date' => substr(Row::text($row, 'business_date'), 0, 10), 'reference' => Row::nullable($row, 'reference'),
                'claimed' => $claimed, 'settled' => $settled, 'waiting' => Money::of($claimed)->minus(Money::of($settled))->toString()];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $companies
     * @param  array<int, array{id: string, code: string, name: string}>  $branches
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    private function page(Authority $viewer, array $companies, string $tab, ?string $after, array $branches): array
    {
        $query = DB::table('deduction_settlements as s')->join('payments as p', 'p.id', '=', 's.payment_id')->join('client_organizations as c', 'c.id', '=', 's.client_organization_id')->join('invoices as i', 'i.id', '=', 's.invoice_id')
            ->leftJoin('tax_types as t', 't.id', '=', 's.tax_type_id')->leftJoin('evidence_documents as e', 'e.id', '=', 's.evidence_document_id')
            ->whereIn('s.company_id', $companies)->when($tab === 'sisa', fn (Builder $rows) => $rows->where('s.state', 'RESIDUAL'))
            ->selectRaw("s.id, s.company_id, s.settlement_class, s.fee_type, s.tax_direction, s.amount, s.state, s.business_date, s.correction_case_id, p.public_id as payment, p.reference, c.name as client, i.public_id as invoice, t.name as tax_name, e.public_id as evidence, e.declared_issuer,
                (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.id desc limit 1) as number")
            ->orderByDesc('s.id')->limit(self::PAGE + 1);

        if ($after !== null) {
            $anchor = null;

            foreach (DB::table('deduction_settlements')->whereIn('company_id', $companies)->select('id')->lazyByIdDesc(1000) as $candidate) {
                if (hash_equals(RowHandle::of('deduction_settlements', (int) Row::text($candidate, 'id')), $after)) {
                    $anchor = (int) Row::text($candidate, 'id');

                    break;
                }
            }

            if ($anchor === null) {
                return ['rows' => [], 'more' => null];
            }

            $query->where('s.id', '<', $anchor);
        }

        $rows = [];
        $found = $query->get();

        foreach ($found->take(self::PAGE) as $row) {
            $company = (int) Row::text($row, 'company_id');
            $state = Row::text($row, 'state');
            $seesEvidence = $viewer->allows('evidence.view', $company);
            $rows[] = [
                'handle' => RowHandle::of('deduction_settlements', (int) Row::text($row, 'id')), 'company' => $branches[$company]['code'] ?? '', 'client' => Row::text($row, 'client'), 'class' => Row::text($row, 'settlement_class'),
                'type' => Row::text($row, 'settlement_class') === SettlementEntry::FEE ? Row::nullable($row, 'fee_type') : Row::nullable($row, 'tax_name'), 'direction' => Row::nullable($row, 'tax_direction'), 'amount' => Row::text($row, 'amount'), 'state' => $state, 'date' => substr(Row::text($row, 'business_date'), 0, 10),
                'payment' => Row::text($row, 'payment'), 'reference' => Row::nullable($row, 'reference'), 'invoice' => Row::text($row, 'invoice'), 'number' => Row::nullable($row, 'number'),
                'evidence' => $seesEvidence ? Row::nullable($row, 'evidence') : null, 'issuer' => $seesEvidence ? Row::nullable($row, 'declared_issuer') : null,
                'can' => ['contra' => $state === 'ACTIVE' && $viewer->allows('finance.contra', $company), 'resolve' => $state === 'RESIDUAL' && $viewer->allows('settlement.record', $company)],
            ];
        }

        $last = $rows === [] ? null : $rows[count($rows) - 1]['handle'];

        return ['rows' => $rows, 'more' => $found->count() > self::PAGE ? $last : null];
    }
}
