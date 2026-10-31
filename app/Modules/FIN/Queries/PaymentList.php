<?php

namespace App\Modules\FIN\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-045 *Pembayaran* (ROUTE-045; NAV-045; SCR-41; WF-FIN-03; J-FIN-03): by branch, newest first by keyset (IX-11), each payment with its applications, settlements, the states *Tanpa Bukti* and *Dikontra*, its citation and credit; the
 * tabs *Semua*, *Kredit* and *Tanpa Bukti*, the client and the cursor in the address (IP-18; NV-05), the payment just recorded first (NAV-046; S2-J06). No total across companies (CS-08). `finance.view` within its company (SM-25). The
 * corrections are absent without their capability; a refund-consumed application offers no move, the row saying why (L-46). In at most four statements (QB-12).
 */
final class PaymentList
{
    public const PAGE = 50;

    public const TABS = ['semua', 'kredit', 'tanpa_bukti'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $tab, ?string $client, ?string $after, ?string $recorded): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $tab = in_array($tab, self::TABS, true) ? $tab : 'semua';
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('finance.view', (int) Row::text($company, 'id'))) {
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
        $clientKey = is_string($client) && preg_match(self::UUID, $client) === 1 ? strtolower($client) : null;
        $cursor = is_string($after) && $after !== '';
        $pinned = ! $cursor && is_string($recorded) && preg_match(self::UUID, $recorded) === 1 ? strtolower($recorded) : null;
        $page = $companies === [] || ($cursor && preg_match(self::UUID, (string) $after) !== 1) ? ['rows' => [], 'more' => null] : $this->page($viewer, $companies, $tab, $clientKey, $cursor ? strtolower((string) $after) : null, $pinned, $branches);
        $failed = $cursor && $page['rows'] === [];

        return [
            'branches' => array_values($branches),
            'branch' => $chosen === null ? null : $branches[$chosen]['id'],
            'tab' => $tab,
            'client' => $clientKey,
            'clients' => $companies === [] ? [] : $this->clients($viewer, $companies),
            ...$page,
            'after' => $cursor && ! $failed ? strtolower((string) $after) : null,
            'cursor_failed' => $failed,
            'recorded' => $pinned,
            'today' => BusinessDate::today(),
            'can' => ['record' => array_filter(array_keys($branches), fn (int $id): bool => $viewer->allows('payment.record', $id)) !== []],
        ];
    }

    /**
     * What "Terapkan" and "Pindahkan Penerapan" may target: issued invoices with something outstanding of the payment's client and company, never another's (C-14; J-FIN-03), and the refund shortfalls it may back (L-46).
     *
     * @return array{invoices: list<array<string, mixed>>, shortfalls: list<array<string, mixed>>}
     */
    public function targets(int $viewerId, ?string $paymentId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $payment = is_string($paymentId) && preg_match(self::UUID, $paymentId) === 1 ? DB::table('payments')->where('public_id', strtolower($paymentId))->first(['company_id', 'client_organization_id']) : null;

        if ($payment === null || ! $viewer->allows('finance.view', (int) Row::text($payment, 'company_id'))) {
            return ['invoices' => [], 'shortfalls' => []];
        }

        $company = (int) Row::text($payment, 'company_id');
        $client = (int) Row::text($payment, 'client_organization_id');

        return ['invoices' => self::openInvoices($company, $client), 'shortfalls' => self::shortfalls($company, $client)];
    }

    /**
     * The client's issued invoices in the company with something outstanding, billed or not (SF-APPLY), oldest due first.
     *
     * @return list<array{id: string, number: ?string, project_number: ?string, outstanding: string, billed: bool, kuitansi: bool}>
     */
    public static function openInvoices(int $companyId, int $clientId): array
    {
        $rows = [];

        foreach (DB::table('invoices as i')->where('i.company_id', $companyId)->where('i.client_organization_id', $clientId)->where('i.state', 'ISSUED')->join('invoice_balances as b', 'b.invoice_id', '=', 'i.id')->where('b.outstanding', '>', 0)
            ->leftJoin('projects as p', 'p.id', '=', 'i.project_id')->orderByRaw("coalesce(b.current_due_date, date '9999-12-31'), i.id")->limit(200)
            ->selectRaw("i.public_id, p.project_number, b.outstanding, b.current_billing_act_id,
                (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1) as number,
                exists (select 1 from documents k join document_versions kv on kv.document_id = k.id and kv.state = 'ISSUED' where k.invoice_id = i.id and k.document_type_code = 'DOC-05' and k.mode = 'PRE_PAYMENT'
                    and not exists (select 1 from prepayment_kuitansi_links l where l.document_id = k.id and l.state = 'LINKED')) as open_kuitansi")->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'number' => Row::nullable($row, 'number'), 'project_number' => Row::nullable($row, 'project_number'), 'outstanding' => Row::text($row, 'outstanding'),
                'billed' => Row::nullable($row, 'current_billing_act_id') !== null, 'kuitansi' => ($row->open_kuitansi ?? false) === true];
        }

        return $rows;
    }

    /**
     * The applications a source's contra ended whose refund still lacks backing, of the client and company (L-46; QS-07).
     *
     * @return list<array{handle: string, shortfall: string}>
     */
    private static function shortfalls(int $companyId, int $clientId): array
    {
        $rows = [];

        foreach (DB::table('payment_applications as a')->where('a.company_id', $companyId)->where('a.client_organization_id', $clientId)->where('a.end_reason', 'SOURCE_CONTRA')->whereNotNull('a.refund_disbursement_id')
            ->join('money_fact_balances as m', 'm.disbursement_id', '=', 'a.refund_disbursement_id')->where('m.shortfall_amount', '>', 0)->orderBy('a.id')->limit(50)->get(['a.id', 'm.shortfall_amount']) as $row) {
            $rows[] = ['handle' => RowHandle::of('payment_applications', (int) Row::text($row, 'id')), 'shortfall' => Row::text($row, 'shortfall_amount')];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $companies
     * @param  array<int, array{id: string, code: string, name: string}>  $branches
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    private function page(Authority $viewer, array $companies, string $tab, ?string $client, ?string $after, ?string $pinned, array $branches): array
    {
        $found = $viewer->scope->apply(DB::table('payments as p'), 'p.company_id')->whereIn('p.company_id', $companies)
            ->join('application_source_balances as b', 'b.payment_id', '=', 'p.id')
            ->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->leftJoin('company_bank_accounts as ba', 'ba.id', '=', 'p.company_bank_account_id')
            ->leftJoin('bank_statement_lines as l', 'l.id', '=', 'p.bank_statement_line_id')
            ->leftJoin('payments as r', 'r.rerecords_payment_id', '=', 'p.id')
            ->leftJoin('application_source_balances as rb', 'rb.payment_id', '=', 'r.id')
            ->leftJoin('payments as o2', 'o2.id', '=', 'p.rerecords_payment_id')
            ->when($client !== null, fn (Builder $where) => $where->where('o.public_id', $client))
            ->when($tab === 'kredit', fn (Builder $where) => $where->where('b.capacity_amount', '>', 0))
            ->when($tab === 'tanpa_bukti', fn (Builder $where) => $where->whereNotNull('p.unevidenced_reason'))
            ->when($after !== null, fn (Builder $where) => $where->whereRaw('(p.business_date, p.id) < (select c.business_date, c.id from payments c where c.public_id = ? and c.company_id = any(?::bigint[]))', [$after, '{'.implode(',', $companies).'}']))
            ->orderByRaw($pinned === null ? 'p.business_date desc, p.id desc' : 'p.public_id = ? desc, p.business_date desc, p.id desc', $pinned === null ? [] : [$pinned])
            ->limit(self::PAGE + 1)
            ->selectRaw('p.id, p.public_id, p.company_id, p.business_date, p.amount, p.method, p.reference, p.payer_intermediary, p.unevidenced_reason, p.recovery_for_invoice_id, p.bank_statement_line_id,
                o.name as client, ba.bank_name, ba.account_number, l.line_reference, b.net_amount, b.contra_amount, b.capacity_amount, b.claimed_deductions_amount, b.settled_amount, r.public_id as rerecorded_by, rb.capacity_amount as rerecorded_capacity, o2.public_id as rerecords,
                (select e.public_id from evidence_links k join evidence_documents e on e.id = k.evidence_document_id where k.payment_id = p.id and k.unlinked_at is null order by k.id limit 1) as proof')
            ->get()->all();
        $more = count($found) > self::PAGE;
        $found = array_slice($found, 0, self::PAGE);
        $dependents = self::dependents(array_map(fn (object $row): int => (int) Row::text($row, 'id'), $found));
        $rows = [];

        foreach ($found as $row) {
            $company = (int) Row::text($row, 'company_id');
            $id = (int) Row::text($row, 'id');
            $contra = Row::text($row, 'contra_amount');
            $capacity = Row::text($row, 'capacity_amount');
            $evidence = $viewer->allows('evidence.view', $company);
            $reallocate = $viewer->allows('payment.reallocate', $company);
            $rows[] = [
                'id' => Row::text($row, 'public_id'),
                'date' => Row::text($row, 'business_date'),
                'company' => $branches[$company]['code'] ?? '',
                'client' => Row::text($row, 'client'),
                'amount' => Row::text($row, 'amount'),
                'net' => Row::text($row, 'net_amount'),
                'contra' => $contra,
                'capacity' => $capacity,
                'method' => Row::text($row, 'method'),
                'account' => Row::nullable($row, 'bank_name') === null ? null : Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number'),
                'reference' => Row::nullable($row, 'reference'),
                'intermediary' => Row::nullable($row, 'payer_intermediary'),
                'unevidenced' => Row::nullable($row, 'unevidenced_reason'),
                'line' => Row::nullable($row, 'line_reference'),
                'flagged' => Row::nullable($row, 'bank_name') !== null && Row::nullable($row, 'bank_statement_line_id') === null,
                'recovery' => Row::nullable($row, 'recovery_for_invoice_id') !== null,
                'proof' => $evidence ? Row::nullable($row, 'proof') : null,
                'claimed' => Row::text($row, 'claimed_deductions_amount'),
                'settled' => Row::text($row, 'settled_amount'),
                'rerecords' => Row::nullable($row, 'rerecords'),
                'rerecorded_by' => Row::nullable($row, 'rerecorded_by'),
                'rerecorded_capacity' => Row::nullable($row, 'rerecorded_capacity'),
                'applications' => array_map(fn (array $application): array => [...$application, 'can_move' => $reallocate && $application['state'] === 'ACTIVE' && $application['target'] === 'INVOICE'], $dependents['applications'][$id] ?? []),
                'settlements' => $dependents['settlements'][$id] ?? [],
                'can' => [
                    'apply' => $viewer->allows('payment.apply', $company) && self::positive($capacity),
                    'reallocate' => $reallocate,
                    'contra' => $viewer->allows('finance.contra', $company) && self::positive(Row::text($row, 'net_amount')),
                    'kuitansi' => $viewer->allows('documents.issue', $company),
                    'link' => $viewer->allows('payment.record', $company),
                ],
            ];
        }

        $last = $rows === [] ? null : $rows[count($rows) - 1]['id'];

        return ['rows' => $rows, 'more' => $more ? $last : null];
    }

    /**
     * The page's applications and settlements, one statement over the page's payments.
     *
     * @param  list<int>  $payments
     * @return array{applications: array<int, list<array<string, mixed>>>, settlements: array<int, list<array<string, mixed>>>}
     */
    private static function dependents(array $payments): array
    {
        $found = ['applications' => [], 'settlements' => []];

        if ($payments === []) {
            return $found;
        }

        $ids = '{'.implode(',', $payments).'}';
        $rows = DB::select(<<<'SQL'
            select 'A' as kind, a.id, a.payment_id, a.target_kind as target, a.amount, a.state, a.end_reason, a.business_date, i.public_id as invoice,
                (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = a.invoice_id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1) as number,
                (select d.public_id from documents d where d.payment_id = a.payment_id and d.invoice_id = a.invoice_id and d.document_type_code = 'DOC-05' and d.mode = 'RECEIPT' limit 1) as receipt,
                (select k.public_id from documents k join document_versions kv on kv.document_id = k.id and kv.state = 'ISSUED' where k.invoice_id = a.invoice_id and k.document_type_code = 'DOC-05' and k.mode = 'PRE_PAYMENT'
                    and not exists (select 1 from prepayment_kuitansi_links l where l.document_id = k.id and l.state = 'LINKED') order by k.id limit 1) as kuitansi,
                null::text as settlement_class
            from payment_applications a left join invoices i on i.id = a.invoice_id where a.payment_id = any(?::bigint[])
            union all
            select 'S', s.id, s.payment_id, null, s.amount, s.state, null, s.business_date, i.public_id,
                (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = s.invoice_id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1),
                null, null, s.settlement_class
            from deduction_settlements s join invoices i on i.id = s.invoice_id where s.payment_id = any(?::bigint[])
            order by 1, 2
            SQL, [$ids, $ids]);

        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $payment = (int) Row::text($row, 'payment_id');
            $id = (int) Row::text($row, 'id');

            if (Row::text($row, 'kind') === 'A') {
                $found['applications'][$payment][] = ['handle' => RowHandle::of('payment_applications', $id), 'target' => Row::text($row, 'target'), 'invoice' => Row::nullable($row, 'invoice'), 'number' => Row::nullable($row, 'number'), 'amount' => Row::text($row, 'amount'), 'state' => Row::text($row, 'state'), 'end_reason' => Row::nullable($row, 'end_reason'), 'date' => Row::text($row, 'business_date'), 'receipt' => Row::nullable($row, 'receipt'), 'kuitansi' => Row::nullable($row, 'kuitansi')];
            } else {
                $found['settlements'][$payment][] = ['handle' => RowHandle::of('deduction_settlements', $id), 'invoice' => Row::nullable($row, 'invoice'), 'number' => Row::nullable($row, 'number'), 'class' => Row::text($row, 'settlement_class'), 'amount' => Row::text($row, 'amount'), 'state' => Row::text($row, 'state')];
            }
        }

        return $found;
    }

    /**
     * The clients with a payment in scope, for the filter.
     *
     * @param  list<int>  $companies
     * @return list<array{id: string, name: string}>
     */
    private function clients(Authority $viewer, array $companies): array
    {
        $clients = [];

        foreach ($viewer->scope->apply(DB::table('payments as p'), 'p.company_id')->whereIn('p.company_id', $companies)->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->distinct()->orderBy('o.name')->orderBy('o.public_id')->limit(200)->get(['o.public_id', 'o.name']) as $row) {
            $clients[] = ['id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name')];
        }

        return $clients;
    }

    private static function positive(string $amount): bool
    {
        return preg_match('/^0+(\.0+)?$/', $amount) !== 1;
    }
}
