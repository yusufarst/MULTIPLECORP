<?php

namespace App\Modules\FIN\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\FIN\Data\PaymentEntry;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-046 *Catat Pembayaran* (ROUTE-046; NAV-046; SCR-42; J-FIN-03; CS-03): the company chosen here, in scope and active — never prefilled from the working filter for an account with several, a deactivated one reached only through its own invoice,
 * whose receivable collection continues (BR-ACC-03) —; for it and the client the issued invoices with something outstanding, never another company's (C-14), the company's own accounts, the statement lines with room, the proofs the actor may see (PJ-08), the
 * invoices with a standing write-off, the payments a re-recording may name and another company's transfer-outs with both in scope (XL-5). `payment.record` with `finance.view`.
 */
final class PaymentForm
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $company, ?string $client, ?string $invoice, ?string $project = null): array
    {
        $viewer = $this->capabilities->for($viewerId);

        if ($invoice === null && is_string($project) && preg_match(self::UUID, $project) === 1) {
            $named = DB::table('projects as p')->join('companies as c', 'c.id', '=', 'p.company_id')->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->where('p.public_id', strtolower($project))->first(['p.company_id', 'c.public_id as company', 'o.public_id as client']);

            if ($named !== null && $viewer->allows('payment.record', (int) Row::text($named, 'company_id'))) {
                $company = Row::text($named, 'company');
                $client = Row::text($named, 'client');
            }
        }

        $origin = is_string($invoice) && preg_match(self::UUID, $invoice) === 1 ? DB::table('invoices as i')->join('companies as c', 'c.id', '=', 'i.company_id')->join('client_organizations as o', 'o.id', '=', 'i.client_organization_id')
            ->where('i.public_id', strtolower($invoice))->first(['i.company_id', 'c.public_id as company', 'o.public_id as client', 'o.name as client_name']) : null;
        $origin = $origin !== null && $viewer->allows('payment.record', (int) Row::text($origin, 'company_id')) ? $origin : null;
        $own = $origin === null ? null : (int) Row::text($origin, 'company_id');
        $companies = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->where(fn (Builder $where) => $where->where('is_active', true)->when($own !== null, fn (Builder $or) => $or->orWhere('id', $own)))->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $row) {
            if ($viewer->allows('payment.record', (int) Row::text($row, 'id'))) {
                $companies[(int) Row::text($row, 'id')] = ['id' => Row::text($row, 'public_id'), 'code' => Row::text($row, 'code'), 'name' => Row::text($row, 'legal_name')];
            }
        }

        $wanted = $origin !== null ? Row::text($origin, 'company') : $company;
        $chosen = null;

        foreach ($companies as $id => $candidate) {
            if ($candidate['id'] === $wanted) {
                $chosen = $id;
            }
        }

        // One company in scope is no choice to make; several are never prefilled (CS-03).
        if ($chosen === null && count($companies) === 1) {
            $chosen = array_key_first($companies);
        }

        $clientKey = $origin !== null ? Row::text($origin, 'client') : (is_string($client) && preg_match(self::UUID, $client) === 1 ? strtolower($client) : null);
        $clientRow = $clientKey === null ? null : DB::table('client_organizations')->where('public_id', $clientKey)->first(['id', 'public_id', 'name']);
        $clientId = $clientRow === null ? null : (int) Row::text($clientRow, 'id');
        $evidence = $chosen !== null && $viewer->allows('evidence.view', $chosen) ? self::proofs($chosen) : [];

        return [
            'companies' => array_values($companies),
            'company' => $chosen === null ? null : $companies[$chosen]['id'],
            'client' => $clientRow === null ? null : ['id' => Row::text($clientRow, 'public_id'), 'name' => Row::text($clientRow, 'name')],
            'origin' => $origin === null ? null : strtolower((string) $invoice),
            'invoices' => $chosen === null || $clientId === null ? [] : PaymentList::openInvoices($chosen, $clientId),
            'accounts' => $chosen === null ? [] : self::accounts($chosen),
            'lines' => $chosen === null ? [] : self::lines($chosen),
            'evidence' => $evidence,
            'tax_types' => $chosen === null ? [] : self::taxTypes(),
            'recoveries' => $chosen === null || $clientId === null ? [] : self::recoveries($chosen, $clientId),
            'rerecordable' => $chosen === null || $clientId === null ? [] : self::rerecordable($chosen, $clientId),
            'transfers' => $chosen === null ? [] : self::transfers($viewer->scope->all ? null : $viewer->scope->companyIds, $chosen),
            'today' => BusinessDate::today(),
            'can' => ['upload' => $chosen !== null && EvidenceTypes::mayHandle($viewer, 'PAYMENT_PROOF', $chosen), 'override' => $chosen !== null && $viewer->allows('warnings.override', $chosen)],
        ];
    }

    /** @return list<array{handle: string, label: string}> */
    public static function accounts(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('company_bank_accounts')->where('company_id', $companyId)->where('is_active', true)->orderBy('id')->get(['id', 'bank_name', 'account_number', 'account_holder']) as $row) {
            $rows[] = ['handle' => RowHandle::of('company_bank_accounts', (int) Row::text($row, 'id')), 'label' => Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number').' · '.Row::text($row, 'account_holder')];
        }

        return $rows;
    }

    /**
     * The company's ACTIVE statement lines with room, newest first (BD-03).
     *
     * @return list<array{handle: string, account: string, date: string, amount: string, uncited: string, reference: string}>
     */
    public static function lines(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('bank_statement_lines as l')->where('l.company_id', $companyId)->where('l.state', 'ACTIVE')->join('evidence_balances as g', 'g.bank_statement_line_id', '=', 'l.id')->whereColumn('g.cited_amount', '<', 'g.proven_amount')
            ->orderByDesc('l.business_date')->orderByDesc('l.id')->limit(200)->selectRaw('l.id, l.company_bank_account_id, l.business_date, l.amount, l.line_reference, g.proven_amount - g.cited_amount as uncited')->get() as $row) {
            $rows[] = ['handle' => RowHandle::of('bank_statement_lines', (int) Row::text($row, 'id')), 'account' => RowHandle::of('company_bank_accounts', (int) Row::text($row, 'company_bank_account_id')), 'date' => Row::text($row, 'business_date'), 'amount' => Row::text($row, 'amount'), 'uncited' => Row::text($row, 'uncited'), 'reference' => Row::text($row, 'line_reference')];
        }

        return $rows;
    }

    /** @return list<array{id: string, type: string, issuer: string, reference: ?string, date: string}> */
    private static function proofs(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('evidence_documents')->where('company_id', $companyId)->whereIn('evidence_type', PaymentEntry::PROOF)->orderByDesc('id')->limit(100)->get(['public_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date']) as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'issuer' => Row::text($row, 'declared_issuer'), 'reference' => Row::nullable($row, 'issuer_reference'), 'date' => Row::text($row, 'document_date')];
        }

        return $rows;
    }

    /** @return list<array{code: string}> */
    private static function taxTypes(): array
    {
        $rows = [];

        foreach (DB::table('tax_types')->where('is_active', true)->orderBy('code')->get(['code']) as $row) {
            $rows[] = ['code' => Row::text($row, 'code')];
        }

        return $rows;
    }

    /**
     * Written-off invoices of the client: money received for one carries it, listed for the Owner (WF-FIN-05; QS-12; QS-14).
     *
     * @return list<array{id: string, number: ?string}>
     */
    private static function recoveries(int $companyId, int $clientId): array
    {
        $rows = [];

        foreach (DB::table('invoices as i')->where('i.company_id', $companyId)->where('i.client_organization_id', $clientId)->whereExists(fn (Builder $written) => $written->from('receivable_writeoffs as w')->whereColumn('w.invoice_id', 'i.id'))->orderByDesc('i.id')->limit(50)
            ->selectRaw("i.public_id, (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1) as number")->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'number' => Row::nullable($row, 'number')];
        }

        return $rows;
    }

    /**
     * Payments a re-recording may name: of the company, re-recorded by none yet, the contra'd ones first (CM-19).
     *
     * @return list<array{id: string, date: string, amount: string, contra: string}>
     */
    private static function rerecordable(int $companyId, int $clientId): array
    {
        $rows = [];

        foreach (DB::table('payments as p')->where('p.company_id', $companyId)->where('p.client_organization_id', $clientId)->join('application_source_balances as b', 'b.payment_id', '=', 'p.id')
            ->whereNotExists(fn (Builder $taken) => $taken->from('payments as r')->whereColumn('r.rerecords_payment_id', 'p.id'))->orderByRaw('b.contra_amount > 0 desc, p.id desc')->limit(50)->get(['p.public_id', 'p.business_date', 'p.amount', 'b.contra_amount']) as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'amount' => Row::text($row, 'amount'), 'contra' => Row::text($row, 'contra_amount')];
        }

        return $rows;
    }

    /**
     * Another company's transfer-outs with room, both companies in scope (XL-5), that company's reference masked: the amount and date are all this side sees.
     *
     * @param  list<int>|null  $scope  the companies in scope, null for all
     * @return list<array{id: string, date: string, left: string}>
     */
    private static function transfers(?array $scope, int $companyId): array
    {
        $rows = [];

        foreach (DB::table('disbursements as d')->where('d.kind', 'INTERCOMPANY_TRANSFER_OUT')->where('d.company_id', '<>', $companyId)->when($scope !== null, fn ($query) => $query->whereIn('d.company_id', $scope ?? []))
            ->join('money_fact_balances as m', 'm.disbursement_id', '=', 'd.id')->whereRaw('m.amount - m.contra_amount - m.transfer_cited_amount > 0')->orderByDesc('d.id')->limit(50)->selectRaw('d.public_id, d.business_date, m.amount - m.contra_amount - m.transfer_cited_amount as room')->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'left' => Row::text($row, 'room')];
        }

        return $rows;
    }
}
