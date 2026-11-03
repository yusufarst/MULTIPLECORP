<?php

namespace App\Modules\FIN\Queries;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\FIN\Data\OtherCash;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CrossCompanyLink;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-049 *Pengeluaran & Kas Lain* (ROUTE-049; NAV-049; SCR-45; WF-FIN-07; J-FIN-07): by branch, the disbursements, the expenses and the other Cash-In newest first by keyset, each
 * with what it cites, never a total across companies (CS-07; CS-08). Cash facts with `finance.view`, expenses with `cost.view` (row 78; RG-06; SM-25); an expense another posting
 * made shows its origin and no control; a transfer's other side is "perusahaan lain" unless both companies are in scope (INFORMATION_ARCHITECTURE §7; XL-5). Each form's options
 * for one company, and only to its capability (AZ-01). A page in at most three statements (QB-13).
 */
final class OtherCashList
{
    public const PAGE = 50;

    public const TABS = ['pengeluaran', 'beban', 'kas-masuk'];

    /** Each form's choices are the newest of their kind, bounded (PFM-07). */
    private const CHOICES = 100;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** What each tab's rows need in their company. */
    private const READ = ['pengeluaran' => 'finance.view', 'beban' => 'cost.view', 'kas-masuk' => 'finance.view'];

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $tab, ?string $after, ?string $recorded): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $branches = [];
        $reads = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            $id = (int) Row::text($company, 'id');
            $finance = $viewer->allows('finance.view', $id);
            $cost = $viewer->allows('cost.view', $id);

            // RG-06: the page opens to finance, or to whom records expenses with the cost side.
            if ($finance || ($cost && $viewer->allows('expense.record', $id))) {
                $branches[$id] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name'),
                    'can' => ['disbursement' => $viewer->allows('disbursement.record', $id), 'expense' => $cost && $viewer->allows('expense.record', $id), 'cashin' => $viewer->allows('cashin.record', $id)]];
                $reads[$id] = ['finance.view' => $finance, 'cost.view' => $cost];
            }
        }

        $chosen = null;

        foreach ($branches as $id => $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $id;
            }
        }

        $tabs = array_values(array_filter(self::TABS, fn (string $each): bool => array_filter($reads, fn (array $read): bool => $read[self::READ[$each]]) !== []));
        $tab = in_array($tab, $tabs, true) ? (string) $tab : ($tabs[0] ?? 'pengeluaran');
        $companies = array_values(array_filter($chosen !== null ? [$chosen] : array_keys($branches), fn (int $id): bool => $reads[$id][self::READ[$tab]]));
        $cursor = is_string($after) && $after !== '';
        $page = $companies === [] || ($cursor && ! RowHandle::valid((string) $after)) ? ['rows' => [], 'more' => null] : $this->page($viewer, $companies, $tab, $cursor ? (string) $after : null, $branches);
        $failed = $cursor && $page['rows'] === [];

        return [
            'branches' => array_values($branches),
            'branch' => $chosen === null ? null : $branches[$chosen]['id'],
            'tabs' => $tabs,
            'tab' => $tab,
            ...$page,
            'after' => $cursor && ! $failed ? (string) $after : null,
            'cursor_failed' => $failed,
            'recorded' => is_string($recorded) && RowHandle::valid($recorded) ? $recorded : null,
            'today' => BusinessDate::today(),
        ];
    }

    /**
     * What the forms offer in one company (ROUTE-257–ROUTE-259), each list only to its form's capability and each evidence only within its family (PJ-08).
     *
     * @return array<string, mixed>|null
     */
    public function form(int $viewerId, string $companyPublicId): ?array
    {
        $viewer = $this->capabilities->for($viewerId);
        $company = preg_match(self::UUID, $companyPublicId) === 1 ? DB::table('companies')->where('public_id', strtolower($companyPublicId))->value('id') : null;
        $company = is_numeric($company) ? (int) $company : null;

        if ($company === null) {
            return null;
        }

        $expense = $viewer->allows('expense.record', $company) && $viewer->allows('cost.view', $company);
        $disbursement = $viewer->allows('disbursement.record', $company);
        $cashin = $viewer->allows('cashin.record', $company);

        if (! $expense && ! $disbursement && ! $cashin) {
            return null;
        }

        $cash = $disbursement || $cashin;
        $sees = fn (string $type): bool => EvidenceTypes::mayView($viewer, $type, $company);

        return [
            'accounts' => $cash ? self::accounts($company) : [],
            'proofs' => $cash ? self::evidence($company, array_values(array_filter(OtherCash::PROOF, $sees)), false) : [],
            'bills' => $expense && $sees('SUPPLIER_BILL') ? self::evidence($company, ['SUPPLIER_BILL'], true) : [],
            'expense_proofs' => $expense ? self::evidence($company, array_values(array_filter(EvidenceTypes::TYPES, fn (string $type): bool => $type !== 'OPENING_SIGNOFF' && $sees($type))), false) : [],
            'projects' => $expense ? self::projects($company) : [],
            'purchases' => $cash ? self::purchases($company) : [],
            'expenses' => $disbursement && $viewer->allows('cost.view', $company) ? self::expenses($company) : [],
            'tax_types' => $disbursement ? self::taxTypes() : [],
            'tax_proofs' => $disbursement && $sees('TAX_PAYMENT_PROOF') ? self::evidence($company, ['TAX_PAYMENT_PROOF'], true) : [],
            'cases' => $cashin ? self::cases($company) : [],
            'transfers' => $cashin ? self::transfers($viewer, $company) : [],
            'lines' => $cashin ? self::lines($company) : [],
            'can' => ['expense' => $expense, 'disbursement' => $disbursement, 'cashin' => $cashin, 'upload' => $viewer->allows('evidence.upload', $company) && $viewer->allows('evidence.view', $company)],
        ];
    }

    /** A bank account of the company, active, by its handle. */
    public function accountOf(int $companyId, string $handle): ?int
    {
        return self::handled(DB::table('company_bank_accounts')->where('company_id', $companyId)->where('is_active', true), 'company_bank_accounts', $handle);
    }

    /** A row of the company by its handle: an expense, a statement line. */
    public function handleOf(string $table, int $companyId, string $handle): ?int
    {
        return self::handled(DB::table($table)->where('company_id', $companyId), $table, $handle);
    }

    /** A row of the company by its public id: a purchase, a project, a return case, evidence — the action checks what it must be. */
    public function publicOf(string $table, int $companyId, string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table($table)->where('company_id', $companyId)->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** A transfer-out of any company, by its public id: the action checks its company and scope (XL-5). */
    public function transferOf(string $publicId): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('disbursements')->where('kind', 'INTERCOMPANY_TRANSFER_OUT')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function taxTypeOf(string $code): ?int
    {
        $id = DB::table('tax_types')->where('code', $code)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /** The company the viewer holds the capability in, by its public id. */
    public function companyOf(int $viewerId, string $publicId, string $capability): ?int
    {
        $id = preg_match(self::UUID, $publicId) === 1 ? DB::table('companies')->where('public_id', strtolower($publicId))->value('id') : null;

        return is_numeric($id) && $this->capabilities->for($viewerId)->allows($capability, (int) $id) ? (int) $id : null;
    }

    public function allows(int $viewerId, string $capability, int $companyId): bool
    {
        return $this->capabilities->for($viewerId)->allows($capability, $companyId);
    }

    private static function handled(Builder $rows, string $table, string $handle): ?int
    {
        if (! RowHandle::valid($handle)) {
            return null;
        }

        foreach ($rows->select('id')->lazyByIdDesc(1000) as $row) {
            if (hash_equals(RowHandle::of($table, (int) Row::text($row, 'id')), $handle)) {
                return (int) Row::text($row, 'id');
            }
        }

        return null;
    }

    /** @return list<array{id: string, label: string}> */
    private static function accounts(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('company_bank_accounts')->where('company_id', $companyId)->where('is_active', true)->orderBy('id')->get(['id', 'bank_name', 'account_number']) as $row) {
            $rows[] = ['id' => RowHandle::of('company_bank_accounts', (int) Row::text($row, 'id')), 'label' => Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number')];
        }

        return $rows;
    }

    /**
     * The company's evidence of the types, newest first; an amount-bearing one only with room, its room stated (C-18; C-20).
     *
     * @param  list<string>  $types
     * @return list<array{id: string, type: string, label: string, uncited: ?string}>
     */
    private static function evidence(int $companyId, array $types, bool $room): array
    {
        if ($types === []) {
            return [];
        }

        $rows = [];
        $query = DB::table('evidence_documents as e')->leftJoin('evidence_balances as b', 'b.evidence_document_id', '=', 'e.id')->where('e.company_id', $companyId)->where('e.evidence_scope', 'COMPANY')->whereIn('e.evidence_type', $types)
            ->when($room, fn (Builder $rows) => $rows->whereColumn('b.cited_amount', '<', 'b.proven_amount'))->orderByDesc('e.id')->limit(self::CHOICES)
            ->selectRaw('e.public_id, e.evidence_type, e.declared_issuer, e.issuer_reference, e.document_date, b.proven_amount - b.cited_amount as uncited');

        foreach ($query->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'type' => Row::text($row, 'evidence_type'), 'label' => trim(Row::text($row, 'declared_issuer').' · '.(Row::nullable($row, 'issuer_reference') ?? Row::text($row, 'document_date'))),
                'uncited' => $room ? Row::nullable($row, 'uncited') : null];
        }

        return $rows;
    }

    /** @return list<array{id: string, number: string, title: string}> */
    private static function projects(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('projects')->where('company_id', $companyId)->whereIn('state', ['ACTIVE', 'COMPLETED_NORMAL', 'COMPLETED_FORCED'])->orderByDesc('id')->limit(self::CHOICES)->get(['public_id', 'project_number', 'title']) as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'project_number'), 'title' => Row::text($row, 'title')];
        }

        return $rows;
    }

    /** @return list<array{id: string, number: string, supplier: string}> */
    private static function purchases(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('purchases')->where('company_id', $companyId)->where('state', '<>', 'CANCELLED')->orderByDesc('id')->limit(self::CHOICES)->selectRaw("public_id, purchase_number, supplier_snapshot->>'name' as supplier")->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'number' => Row::text($row, 'purchase_number'), 'supplier' => Row::nullable($row, 'supplier') ?? ''];
        }

        return $rows;
    }

    /** The company's manual expenses still standing, what a payment is against (C-45). @return list<array{id: string, category: string, date: string, net: string}> */
    private static function expenses(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('expenses as e')->join('money_fact_balances as f', 'f.expense_id', '=', 'e.id')->where('e.company_id', $companyId)->where('e.kind', 'MANUAL')->whereColumn('f.contra_amount', '<', 'f.amount')
            ->orderByDesc('e.id')->limit(self::CHOICES)->selectRaw('e.id, e.category, e.business_date, f.amount - f.contra_amount as net')->get() as $row) {
            $rows[] = ['id' => RowHandle::of('expenses', (int) Row::text($row, 'id')), 'category' => Row::text($row, 'category'), 'date' => substr(Row::text($row, 'business_date'), 0, 10), 'net' => Row::text($row, 'net')];
        }

        return $rows;
    }

    /** @return list<array{code: string, name: string}> */
    private static function taxTypes(): array
    {
        $rows = [];

        foreach (DB::table('tax_types')->where('is_active', true)->orderBy('code')->get(['code', 'name']) as $row) {
            $rows[] = ['code' => Row::text($row, 'code'), 'name' => Row::text($row, 'name')];
        }

        return $rows;
    }

    /** The company's purchase-return cases still awaiting value, what a supplier refund is for (C-46). @return list<array{id: string, open: string}> */
    private static function cases(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('correction_cases')->where('company_id', $companyId)->where('case_kind', 'PURCHASE_RETURN')->whereRaw('returned_cost - refunded_value - replaced_value - unrecovered_value > 0')
            ->orderByDesc('id')->limit(self::CHOICES)->selectRaw('public_id, returned_cost - refunded_value - replaced_value - unrecovered_value as open')->get() as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'open' => Row::text($row, 'open')];
        }

        return $rows;
    }

    /** XL-5: other companies' transfers-out the viewer may cite into this one, with what each has not yet backed. @return list<array{id: string, company: string, date: string, left: string}> */
    private static function transfers(Authority $viewer, int $companyId): array
    {
        $rows = [];

        foreach (DB::table('disbursements as d')->join('money_fact_balances as f', 'f.disbursement_id', '=', 'd.id')->join('companies as c', 'c.id', '=', 'd.company_id')->where('d.kind', 'INTERCOMPANY_TRANSFER_OUT')->where('d.company_id', '<>', $companyId)
            ->whereRaw('f.amount - f.contra_amount - f.transfer_cited_amount > 0')->orderByDesc('d.id')->limit(self::CHOICES)
            ->selectRaw('d.public_id, d.company_id, d.business_date, c.code, f.amount - f.contra_amount - f.transfer_cited_amount as left_amount')->get() as $row) {
            if (CrossCompanyLink::mayCiteTransfer($viewer, 'cashin.record', $companyId, (int) Row::text($row, 'company_id'))) {
                $rows[] = ['id' => Row::text($row, 'public_id'), 'company' => Row::text($row, 'code'), 'date' => substr(Row::text($row, 'business_date'), 0, 10), 'left' => Row::text($row, 'left_amount')];
            }
        }

        return $rows;
    }

    /** The company's statement lines with money no Cash-In cites yet (C-19). @return list<array{id: string, account: string, date: string, reference: string, uncited: string}> */
    private static function lines(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('bank_statement_lines as l')->join('evidence_balances as b', 'b.bank_statement_line_id', '=', 'l.id')->where('l.company_id', $companyId)->where('l.state', 'ACTIVE')->whereColumn('b.cited_amount', '<', 'b.proven_amount')
            ->orderByDesc('l.id')->limit(self::CHOICES)->selectRaw('l.id, l.company_bank_account_id, l.business_date, l.line_reference, b.proven_amount - b.cited_amount as uncited')->get() as $row) {
            $rows[] = ['id' => RowHandle::of('bank_statement_lines', (int) Row::text($row, 'id')), 'account' => RowHandle::of('company_bank_accounts', (int) Row::text($row, 'company_bank_account_id')),
                'date' => substr(Row::text($row, 'business_date'), 0, 10), 'reference' => Row::text($row, 'line_reference'), 'uncited' => Row::text($row, 'uncited')];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $companies
     * @param  array<int, array{id: string, code: string, name: string, can: array<string, bool>}>  $branches
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    private function page(Authority $viewer, array $companies, string $tab, ?string $after, array $branches): array
    {
        [$table, $query] = match ($tab) {
            'beban' => ['expenses', self::expenseRows($companies)],
            'kas-masuk' => ['other_cash_receipts', self::cashInRows($companies)],
            default => ['disbursements', self::disbursementRows($companies)],
        };

        if ($after !== null) {
            $anchor = self::handled(DB::table($table)->whereIn('company_id', $companies), $table, $after);

            if ($anchor === null) {
                return ['rows' => [], 'more' => null];
            }

            $query->where('x.id', '<', $anchor);
        }

        $found = $query->orderByDesc('x.id')->limit(self::PAGE + 1)->get();
        $rows = [];

        foreach ($found->take(self::PAGE) as $row) {
            $company = (int) Row::text($row, 'company_id');
            $common = ['handle' => RowHandle::of($table, (int) Row::text($row, 'id')), 'company' => $branches[$company]['code'] ?? '', 'amount' => Row::text($row, 'amount'), 'net' => Row::text($row, 'net'),
                'date' => substr(Row::text($row, 'business_date'), 0, 10)];
            $rows[] = [...$common, ...match ($tab) {
                'beban' => self::expenseRow($viewer, $row, $company),
                'kas-masuk' => self::cashInRow($viewer, $row, $company),
                default => self::disbursementRow($viewer, $row, $company),
            }];
        }

        $last = $rows === [] ? null : $rows[count($rows) - 1]['handle'];

        return ['rows' => $rows, 'more' => $found->count() > self::PAGE ? $last : null];
    }

    /** @param  list<int>  $companies */
    private static function disbursementRows(array $companies): Builder
    {
        return DB::table('disbursements as x')->join('money_fact_balances as f', 'f.disbursement_id', '=', 'x.id')->join('company_bank_accounts as a', 'a.id', '=', 'x.company_bank_account_id')
            ->leftJoin('purchases as p', 'p.id', '=', 'x.purchase_id')->leftJoin('expenses as e', 'e.id', '=', 'x.expense_id')->leftJoin('client_organizations as c', 'c.id', '=', 'x.client_organization_id')
            ->whereIn('x.company_id', $companies)
            ->selectRaw("x.id, x.public_id, x.company_id, x.kind, x.amount, x.business_date, f.amount - f.contra_amount as net, f.transfer_cited_amount, a.bank_name, a.account_number, p.public_id as purchase, p.purchase_number, e.category as expense_category, e.company_id as expense_company, c.name as client,
                (select e.public_id || ':' || e.evidence_type from evidence_links k join evidence_documents e on e.id = k.evidence_document_id where k.disbursement_id = x.id and k.unlinked_at is null order by k.id limit 1) as proof,
                (select string_agg(distinct r.company_id::text || ':' || c.code, ',') from (select company_id from other_cash_receipts where transfer_from_disbursement_id = x.id union select company_id from payments where transfer_from_disbursement_id = x.id) r join companies c on c.id = r.company_id) as cited_by");
    }

    /** @param  list<int>  $companies */
    private static function expenseRows(array $companies): Builder
    {
        return DB::table('expenses as x')->join('money_fact_balances as f', 'f.expense_id', '=', 'x.id')->leftJoin('projects as p', 'p.id', '=', 'x.project_id')->leftJoin('evidence_documents as b', 'b.id', '=', 'x.source_bill_evidence_id')
            ->whereIn('x.company_id', $companies)
            ->selectRaw('x.id, x.company_id, x.kind, x.category, x.amount, x.business_date, f.amount - f.contra_amount as net, p.public_id as project, p.project_number, p.title, b.public_id as bill, b.declared_issuer as bill_issuer,
                exists (select 1 from disbursements d join money_fact_balances g on g.disbursement_id = d.id where d.expense_id = x.id and g.contra_amount < g.amount) as paid');
    }

    /** @param  list<int>  $companies */
    private static function cashInRows(array $companies): Builder
    {
        return DB::table('other_cash_receipts as x')->join('money_fact_balances as f', 'f.other_cash_receipt_id', '=', 'x.id')->join('company_bank_accounts as a', 'a.id', '=', 'x.company_bank_account_id')
            ->leftJoin('purchases as p', 'p.id', '=', 'x.purchase_id')->leftJoin('disbursements as t', 't.id', '=', 'x.transfer_from_disbursement_id')->leftJoin('companies as o', 'o.id', '=', 't.company_id')
            ->whereIn('x.company_id', $companies)
            ->selectRaw("x.id, x.company_id, x.category, x.income_class, x.amount, x.business_date, f.amount - f.contra_amount as net, a.bank_name, a.account_number, p.public_id as purchase, p.purchase_number, t.company_id as other_company, o.code as other_code,
                (x.bank_statement_line_id is not null) as cited_line,
                (select e.public_id || ':' || e.evidence_type from evidence_links k join evidence_documents e on e.id = k.evidence_document_id where k.other_cash_receipt_id = x.id and k.unlinked_at is null order by k.id limit 1) as proof");
    }

    /** @return array<string, mixed> */
    private static function disbursementRow(Authority $viewer, object $row, int $company): array
    {
        $kind = Row::text($row, 'kind');
        $citedBy = array_values(array_filter(explode(',', Row::nullable($row, 'cited_by') ?? ''), fn (string $id): bool => $id !== ''));
        $expenseCompany = Row::nullable($row, 'expense_company');

        return [
            'id' => Row::text($row, 'public_id'), 'kind' => $kind, 'account' => Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number'),
            'purchase' => Row::nullable($row, 'purchase'), 'purchase_number' => Row::nullable($row, 'purchase_number'), 'client' => Row::nullable($row, 'client'),
            // Row 114: a linked expense's details on the cost side only.
            'expense' => $expenseCompany !== null && $viewer->allows('cost.view', $company) ? Row::nullable($row, 'expense_category') : null,
            'cited' => $kind === 'INTERCOMPANY_TRANSFER_OUT' ? Row::text($row, 'transfer_cited_amount') : null,
            // INFORMATION_ARCHITECTURE §7: the citing company named only when both are in scope.
            'cited_by' => array_map(fn (string $pair): string => $viewer->scope->includes((int) explode(':', $pair, 2)[0]) ? (explode(':', $pair, 2)[1] ?? '') : '', $citedBy),
            'proof' => self::proof($viewer, Row::nullable($row, 'proof'), $company),
            'can' => ['contra' => Money::of(Row::text($row, 'net'))->isPositive() && $viewer->allows('finance.contra', $company)],
        ];
    }

    /** @return array<string, mixed> */
    private static function expenseRow(Authority $viewer, object $row, int $company): array
    {
        $kind = Row::text($row, 'kind');
        $paid = in_array(Row::nullable($row, 'paid'), ['1', 'true', 't'], true);

        return [
            'kind' => $kind, 'category' => Row::text($row, 'category'), 'project' => Row::nullable($row, 'project'), 'project_number' => Row::nullable($row, 'project_number'), 'title' => Row::nullable($row, 'title'),
            'bill' => $viewer->allows('evidence.view', $company) ? Row::nullable($row, 'bill') : null, 'bill_issuer' => $viewer->allows('evidence.view', $company) ? Row::nullable($row, 'bill_issuer') : null, 'paid' => $paid,
            // CM-38: only a manual expense is contra'd here, and only once no payment for it stands (AX-33).
            'can' => ['contra' => $kind === 'MANUAL' && ! $paid && Money::of(Row::text($row, 'net'))->isPositive() && $viewer->allows('finance.contra', $company)],
        ];
    }

    /** @return array<string, mixed> */
    private static function cashInRow(Authority $viewer, object $row, int $company): array
    {
        $other = Row::nullable($row, 'other_company');

        return [
            'category' => Row::text($row, 'category'), 'income' => Row::text($row, 'income_class') === 'INCOME', 'account' => Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number'),
            'purchase' => Row::nullable($row, 'purchase'), 'purchase_number' => Row::nullable($row, 'purchase_number'),
            // INFORMATION_ARCHITECTURE §7: the other company named only when both are in scope.
            'transfer_from' => $other === null ? null : ($viewer->scope->includes((int) $other) ? Row::text($row, 'other_code') : ''),
            'cited_line' => in_array(Row::nullable($row, 'cited_line'), ['1', 'true', 't'], true),
            'proof' => self::proof($viewer, Row::nullable($row, 'proof'), $company),
            'can' => ['contra' => false],
        ];
    }

    /** The first linked proof, `public_id:type`, only to whom sees its family (PJ-08). */
    private static function proof(Authority $viewer, ?string $proof, int $company): ?string
    {
        [$publicId, $type] = explode(':', $proof ?? '', 2) + [1 => ''];

        return $proof !== null && EvidenceTypes::mayView($viewer, $type, $company) ? $publicId : null;
    }
}
