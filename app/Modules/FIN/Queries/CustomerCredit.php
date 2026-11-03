<?php

namespace App\Modules\FIN\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\FIN\Services\CreditProjects;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-048 *Kredit Pelanggan* (ROUTE-048; NAV-048; SCR-44; J-FIN-06; CALC-06; WF-FIN-06): credit per client and company, derived and never stored — the capacity of the
 * client's payments and opening credits in that company, zero on a source carrying a refund shortfall —, each source with its trail; the refunds with their backing and any
 * shortfall on its case; per project what has arisen, what is left and what is held (`DIR-104`); "Terapkan ke Invoice" (ROUTE-252), "Kembalikan Dana" (ROUTE-256) and
 * "Tahan Kredit" (ROUTE-264) by capability. Everything with `finance.view` of the company (SM-25); in a constant number of statements whatever the rows (QB-02).
 */
final class CustomerCredit
{
    /** The clients one page shows. */
    public const PAGE = 50;

    /** The refunds and open invoices one client shows. */
    public const PER_CLIENT = 50;

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $after = null): array
    {
        $viewer = $this->capabilities->for($viewerId);
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
        [$groups, $more] = $companies === [] ? [[], null] : $this->groups($companies, $after);

        return [
            'branches' => array_values($branches),
            'branch' => $chosen === null ? null : $branches[$chosen]['id'],
            'groups' => array_map(function (array $group) use ($branches, $viewer): array {
                $company = is_int($group['company_id'] ?? null) ? $group['company_id'] : 0;
                $refund = $viewer->allows('refund.record', $company);

                // The transfer proofs and accounts only to whom may refund, and the proofs only within their family (PJ-08).
                return [...$group, 'company' => $branches[$company] ?? null, 'accounts' => $refund ? $group['accounts'] : [],
                    'proofs' => $refund && EvidenceTypes::mayView($viewer, 'TRANSFER_PROOF', $company) ? $group['proofs'] : [],
                    'can' => ['apply' => $viewer->allows('payment.apply', $company), 'refund' => $refund, 'hold' => $refund]];
            }, $groups),
            'more' => $more,
            'today' => BusinessDate::today(),
        ];
    }

    /** The company's active account a refund leaves from, by its handle. */
    public function accountOf(int $companyId, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach (DB::table('company_bank_accounts')->where('company_id', $companyId)->whereNull('deactivated_at')->pluck('id') as $id) {
            if (is_numeric($id) && hash_equals(RowHandle::of('company_bank_accounts', (int) $id), $handle)) {
                return (int) $id;
            }
        }

        return null;
    }

    /** The company's evidence a refund cites, by its public id; the action checks it is transfer evidence (L-29). */
    public function evidenceOf(int $companyId, string $publicId): ?int
    {
        $id = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('evidence_documents')->where('public_id', strtolower($publicId))->where('company_id', $companyId)->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The project whose credit is held, when the viewer sees finance in its company; otherwise unknown (CS-03).
     *
     * @return array{id: int, company: int}|null
     */
    public function projectOf(int $viewerId, string $publicId): ?array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1 ? DB::table('projects')->where('public_id', strtolower($publicId))->first(['id', 'company_id']) : null;

        if ($row === null || ! $this->capabilities->for($viewerId)->allows('finance.view', (int) Row::text($row, 'company_id'))) {
            return null;
        }

        return ['id' => (int) Row::text($row, 'id'), 'company' => (int) Row::text($row, 'company_id')];
    }

    /**
     * The clients with credit left or a refund short of backing, a page at a time by client name, each with its sources, refunds and projects; and the next page's cursor.
     *
     * @param  list<int>  $companies
     * @return array{list<array<string, mixed>>, ?string}
     */
    private function groups(array $companies, ?string $after): array
    {
        $cursor = null;

        if (is_string($after) && preg_match('/^([0-9a-f-]{36})\.([0-9a-f-]{36})$/i', $after, $match) === 1) {
            $cursor = DB::table('client_organizations as o')->crossJoin('companies as c')->where('o.public_id', strtolower($match[1]))->where('c.public_id', strtolower($match[2]))->first(['o.name', 'o.id as client', 'c.id as company']);
        }

        $found = DB::table('application_source_balances as b')->whereIn('b.company_id', $companies)
            ->where(fn (Builder $either) => $either->where('b.capacity_amount', '>', 0)->orWhere('b.refund_shortfall_amount', '>', 0))
            ->join('client_organizations as o', 'o.id', '=', 'b.client_organization_id')->join('companies as co', 'co.id', '=', 'b.company_id')
            ->when($cursor !== null, fn (Builder $later) => $later->whereRaw('(o.name, b.client_organization_id, b.company_id) > (?, ?, ?)', [Row::text($cursor ?? (object) [], 'name'), (int) Row::text($cursor ?? (object) [], 'client'), (int) Row::text($cursor ?? (object) [], 'company')]))
            ->groupBy('b.company_id', 'b.client_organization_id', 'o.public_id', 'o.name', 'co.public_id')->orderBy('o.name')->orderBy('b.client_organization_id')->orderBy('b.company_id')->limit(self::PAGE + 1)
            ->get(['b.company_id', 'b.client_organization_id', 'o.public_id', 'o.name', 'co.public_id as company_public', DB::raw('sum(b.capacity_amount) as credit')])->all();
        $keys = array_slice($found, 0, self::PAGE);
        $last = $keys === [] ? null : $keys[count($keys) - 1];
        $more = count($found) > self::PAGE && $last !== null ? Row::text($last, 'public_id').'.'.Row::text($last, 'company_public') : null;

        if ($keys === []) {
            return [[], null];
        }

        $pairs = array_map(fn (object $row): string => Row::text($row, 'company_id').'|'.Row::text($row, 'client_organization_id'), $keys);
        $clients = array_values(array_unique(array_map(fn (object $row): int => (int) Row::text($row, 'client_organization_id'), $keys)));
        $sources = DB::table('application_source_balances as b')->whereIn('b.company_id', $companies)->whereIn('b.client_organization_id', $clients)->where('b.capacity_amount', '>', 0)
            ->leftJoin('payments as p', 'p.id', '=', 'b.payment_id')->leftJoin('opening_customer_credits as c', 'c.id', '=', 'b.opening_customer_credit_id')
            ->orderByRaw('coalesce(p.business_date, c.business_date), b.payment_id, b.opening_customer_credit_id')
            ->get(['b.company_id', 'b.client_organization_id', 'b.payment_id', 'b.opening_customer_credit_id', 'b.capacity_amount', 'b.gross_amount', 'p.public_id as payment', 'p.business_date as paid_on', 'c.business_date as opened_on',
                DB::raw("(p.id is not null and p.unevidenced_reason is not null and p.bank_statement_line_id is null and not exists (select 1 from evidence_links k join evidence_documents e on e.id = k.evidence_document_id where k.payment_id = p.id and k.unlinked_at is null and e.evidence_type in ('PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'))) as unevidenced")])->all();
        $refunds = DB::query()->fromSub(DB::table('disbursements as d')->where('d.kind', 'REFUND')->whereIn('d.company_id', $companies)->whereIn('d.client_organization_id', $clients)->join('money_fact_balances as m', 'm.disbursement_id', '=', 'd.id')
            ->leftJoin('correction_cases as k', 'k.id', '=', 'm.residual_case_id')
            ->select(['d.id', 'd.public_id', 'd.company_id', 'd.client_organization_id', 'd.business_date', 'd.amount', 'm.backed_amount', 'm.shortfall_amount', 'm.contra_amount', 'k.public_id as case',
                DB::raw('row_number() over (partition by d.company_id, d.client_organization_id order by d.id desc) as n')]), 'r')
            ->where('r.n', '<=', self::PER_CLIENT)->orderByDesc('r.id')->get()->all();
        $projects = [];

        foreach (array_unique(array_map(fn (object $row): int => (int) Row::text($row, 'company_id'), $keys)) as $company) {
            foreach (CreditProjects::ofClients($company, $clients) as $project => $figures) {
                $projects[$project] = $figures;
            }
        }

        $rows = $projects === [] ? [] : DB::table('projects')->whereIn('id', array_keys($projects))->orderBy('project_number')->get(['id', 'public_id', 'project_number', 'title', 'state', 'company_id', 'client_organization_id', 'credit_held_amount'])->all();
        // "Terapkan ke Invoice": the clients' issued invoices with something outstanding, never another client's or company's (BR-FIN-07; C-14).
        $invoices = DB::query()->fromSub(DB::table('invoices as i')->whereIn('i.company_id', $companies)->whereIn('i.client_organization_id', $clients)->where('i.state', 'ISSUED')->join('invoice_balances as v', 'v.invoice_id', '=', 'i.id')->where('v.outstanding', '>', 0)
            ->leftJoin('projects as p', 'p.id', '=', 'i.project_id')
            ->select(['i.id', 'i.public_id', 'i.company_id', 'i.client_organization_id', 'p.project_number', 'v.outstanding', 'v.current_due_date',
                DB::raw("(select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1) as number"),
                DB::raw("row_number() over (partition by i.company_id, i.client_organization_id order by coalesce(v.current_due_date, date '9999-12-31'), i.id) as n")]), 'x')
            ->where('x.n', '<=', self::PER_CLIENT)->orderByRaw("coalesce(x.current_due_date, date '9999-12-31'), x.id")->get()->all();
        $accounts = DB::table('company_bank_accounts')->whereIn('company_id', $companies)->whereNull('deactivated_at')->orderBy('id')->get(['id', 'company_id', 'bank_name', 'account_number'])->all();
        $proofs = DB::table('evidence_documents')->whereIn('company_id', $companies)->where('evidence_scope', 'COMPANY')->where('evidence_type', 'TRANSFER_PROOF')->orderByDesc('id')->limit(self::PAGE)
            ->get(['public_id', 'company_id', 'declared_issuer', 'issuer_reference', 'document_date'])->all();

        $groups = [];

        foreach ($keys as $index => $key) {
            $company = (int) Row::text($key, 'company_id');
            $client = (int) Row::text($key, 'client_organization_id');
            $mine = fn (object $row): bool => (int) Row::text($row, 'company_id') === $company && (int) Row::text($row, 'client_organization_id') === $client;
            $groups[] = [
                'key' => $pairs[$index], 'company_id' => $company, 'client' => ['id' => Row::text($key, 'public_id'), 'name' => Row::text($key, 'name')], 'credit' => Money::of(Row::text($key, 'credit'))->toString(),
                'sources' => array_values(array_map(fn (object $row): array => [
                    'id' => Row::nullable($row, 'payment') ?? RowHandle::of('opening_customer_credits', (int) Row::text($row, 'opening_customer_credit_id')),
                    'kind' => Row::nullable($row, 'payment') !== null ? 'PAYMENT' : 'OPENING',
                    'date' => Row::nullable($row, 'paid_on') ?? Row::text($row, 'opened_on'), 'gross' => Row::text($row, 'gross_amount'), 'capacity' => Row::text($row, 'capacity_amount'), 'evidenced' => ! (bool) Row::text($row, 'unevidenced'),
                ], array_filter($sources, $mine))),
                'refunds' => array_values(array_map(fn (object $row): array => [
                    'id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'amount' => Row::text($row, 'amount'), 'backed' => Row::text($row, 'backed_amount'), 'shortfall' => Row::text($row, 'shortfall_amount'),
                    'standing' => Money::of(Row::text($row, 'contra_amount'))->isZero(), 'case' => Row::nullable($row, 'case'),
                ], array_filter($refunds, $mine))),
                'projects' => array_values(array_map(fn (object $row): array => [
                    'id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'project_number'), 'title' => Row::text($row, 'title'), 'state' => Row::text($row, 'state'),
                    'arisen' => $projects[(int) Row::text($row, 'id')]['arisen'], 'left' => $projects[(int) Row::text($row, 'id')]['left'], 'held' => Row::text($row, 'credit_held_amount'),
                ], array_filter($rows, $mine))),
                'accounts' => array_values(array_map(fn (object $row): array => ['id' => RowHandle::of('company_bank_accounts', (int) Row::text($row, 'id')), 'label' => Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number')],
                    array_filter($accounts, fn (object $row): bool => (int) Row::text($row, 'company_id') === $company))),
                'proofs' => array_values(array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'label' => Row::text($row, 'declared_issuer').' · '.(Row::nullable($row, 'issuer_reference') ?? Row::text($row, 'document_date'))],
                    array_filter($proofs, fn (object $row): bool => (int) Row::text($row, 'company_id') === $company))),
                'invoices' => array_values(array_map(fn (object $row): array => ['id' => Row::text($row, 'public_id'), 'number' => Row::nullable($row, 'number'), 'project_number' => Row::nullable($row, 'project_number'), 'outstanding' => Row::text($row, 'outstanding')],
                    array_filter($invoices, $mine))),
            ];
        }

        return [$groups, $more];
    }
}
