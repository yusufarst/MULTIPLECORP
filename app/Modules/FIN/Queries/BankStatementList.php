<?php

namespace App\Modules\FIN\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-050 *Mutasi Bank* (ROUTE-050; NAV-050; SCR-46; J-FIN-07; L-22; QS-12): one branch at a time, dense: the statement lines with what cites them, the bank payments flagged until cited, and the current claimed deductions; each correction with `cash.correct`
 * and its reason (IP-09). Tabs and the cursor in the address (IP-18; NV-05), a cursor that reads nothing answered as failed, never a silent first page. `bank_statement.record` with `finance.view` within its company (PJ-23). In at most four statements (QB-12).
 */
final class BankStatementList
{
    public const PAGE = 50;

    public const TABS = ['mutasi', 'belum', 'potongan'];

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $tab, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $tab = in_array($tab, self::TABS, true) ? $tab : 'mutasi';
        $branches = [];

        foreach ($viewer->scope->apply(DB::table('companies'), 'id')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']) as $company) {
            if ($viewer->allows('bank_statement.record', (int) Row::text($company, 'id'))) {
                $branches[(int) Row::text($company, 'id')] = ['id' => Row::text($company, 'public_id'), 'code' => Row::text($company, 'code'), 'name' => Row::text($company, 'legal_name')];
            }
        }

        $chosen = array_key_first($branches);

        foreach ($branches as $id => $candidate) {
            if ($candidate['id'] === $branch) {
                $chosen = $id;
            }
        }

        $cursor = is_string($after) && preg_match('/^\d{1,18}$/', $after) === 1 ? (int) $after : null;
        $badCursor = is_string($after) && $after !== '' && $cursor === null;
        $correct = $chosen !== null && $viewer->allows('cash.correct', $chosen);
        $page = $chosen === null || $badCursor ? ['rows' => [], 'more' => null] : match ($tab) {
            'belum' => self::flagged($chosen, $cursor, $correct),
            'potongan' => self::claims($chosen, $cursor, $correct),
            default => self::lines($chosen, $cursor, $correct),
        };
        $failed = ($cursor !== null || $badCursor) && $page['rows'] === [];

        return [
            'branches' => array_values($branches),
            'branch' => $chosen === null ? null : $branches[$chosen]['id'],
            'tab' => $tab,
            ...$page,
            'after' => $failed || $cursor === null ? null : (string) $cursor,
            'cursor_failed' => $failed,
            'accounts' => $chosen === null ? [] : PaymentForm::accounts($chosen),
            'open_lines' => $chosen === null || ! $correct ? [] : PaymentForm::lines($chosen),
            'flagged' => $chosen === null ? [] : self::citable($chosen),
            'evidence' => $chosen === null ? [] : self::statements($chosen),
            'today' => BusinessDate::today(),
            'can' => ['correct' => $correct],
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, more: ?string} */
    private static function lines(int $companyId, ?int $after, bool $correct): array
    {
        $found = DB::table('bank_statement_lines as l')->where('l.company_id', $companyId)->join('evidence_balances as g', 'g.bank_statement_line_id', '=', 'l.id')->join('company_bank_accounts as a', 'a.id', '=', 'l.company_bank_account_id')->join('evidence_documents as e', 'e.id', '=', 'l.statement_evidence_id')
            ->when($after !== null, fn ($where) => $where->where('l.id', '<', $after))->orderByDesc('l.id')->limit(self::PAGE + 1)
            ->selectRaw("l.id, l.business_date, l.amount, l.line_reference, l.state, l.voided_reason, a.bank_name, a.account_number, e.public_id as evidence, g.cited_amount,
                (select coalesce(json_agg(json_build_object('id', p.public_id, 'client', o.name, 'date', p.business_date, 'net', b.net_amount) order by p.id), '[]') from payments p join client_organizations o on o.id = p.client_organization_id join application_source_balances b on b.payment_id = p.id where p.bank_statement_line_id = l.id) as citing")
            ->get()->all();
        $more = count($found) > self::PAGE;
        $found = array_slice($found, 0, self::PAGE);
        $rows = [];

        foreach ($found as $row) {
            $id = (int) Row::text($row, 'id');
            $active = Row::text($row, 'state') === 'ACTIVE';
            $cited = Row::text($row, 'cited_amount');
            $citing = json_decode(Row::text($row, 'citing'), true);
            $rows[] = ['kind' => 'line', 'key' => (string) $id, 'handle' => RowHandle::of('bank_statement_lines', $id), 'date' => Row::text($row, 'business_date'), 'account' => Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number'), 'amount' => Row::text($row, 'amount'), 'reference' => Row::text($row, 'line_reference'), 'state' => Row::text($row, 'state'),
                'voided_reason' => Row::nullable($row, 'voided_reason'), 'evidence' => Row::text($row, 'evidence'), 'cited' => $cited, 'citing' => is_array($citing) ? $citing : [], 'can_void' => $correct && $active && preg_match('/^0+(\.0+)?$/', $cited) === 1];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? Row::text($found[count($found) - 1], 'id') : null];
    }

    /** @return array{rows: list<array<string, mixed>>, more: ?string} */
    private static function flagged(int $companyId, ?int $after, bool $correct): array
    {
        $found = DB::table('payments as p')->where('p.company_id', $companyId)->whereNotNull('p.company_bank_account_id')->whereNull('p.bank_statement_line_id')->join('application_source_balances as b', 'b.payment_id', '=', 'p.id')->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->join('company_bank_accounts as a', 'a.id', '=', 'p.company_bank_account_id')
            ->when($after !== null, fn ($where) => $where->where('p.id', '<', $after))->orderByDesc('p.id')->limit(self::PAGE + 1)->get(['p.id', 'p.public_id', 'p.business_date', 'p.company_bank_account_id', 'p.reference', 'b.net_amount', 'o.name', 'a.bank_name', 'a.account_number'])->all();
        $more = count($found) > self::PAGE;
        $found = array_slice($found, 0, self::PAGE);
        $rows = [];

        foreach ($found as $row) {
            $rows[] = ['kind' => 'payment', 'key' => Row::text($row, 'public_id'), 'id' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'client' => Row::text($row, 'name'), 'net' => Row::text($row, 'net_amount'), 'reference' => Row::nullable($row, 'reference'),
                'account' => Row::text($row, 'bank_name').' · '.Row::text($row, 'account_number'), 'account_handle' => RowHandle::of('company_bank_accounts', (int) Row::text($row, 'company_bank_account_id')), 'can_cite' => $correct];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? Row::text($found[count($found) - 1], 'id') : null];
    }

    /** @return array{rows: list<array<string, mixed>>, more: ?string} */
    private static function claims(int $companyId, ?int $after, bool $correct): array
    {
        $found = DB::table('payment_claimed_deductions as c')->join('payments as p', 'p.id', '=', 'c.payment_id')->where('p.company_id', $companyId)->join('application_source_balances as b', 'b.payment_id', '=', 'p.id')->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')->leftJoin('tax_types as t', 't.id', '=', 'c.tax_type_id')
            ->whereNotExists(fn (Builder $next) => $next->from('payment_claimed_deductions as n')->whereColumn('n.supersedes_claimed_deduction_id', 'c.id'))
            ->when($after !== null, fn ($where) => $where->where('c.id', '<', $after))->orderByDesc('c.id')->limit(self::PAGE + 1)->get(['c.id', 'c.deduction_class', 'c.fee_type', 'c.claimed_amount', 'p.public_id', 'p.business_date', 'b.settled_amount', 'o.name', 't.code'])->all();
        $more = count($found) > self::PAGE;
        $found = array_slice($found, 0, self::PAGE);
        $rows = [];

        foreach ($found as $row) {
            $id = (int) Row::text($row, 'id');
            $rows[] = ['kind' => 'claim', 'key' => (string) $id, 'handle' => RowHandle::of('payment_claimed_deductions', $id), 'payment' => Row::text($row, 'public_id'), 'date' => Row::text($row, 'business_date'), 'client' => Row::text($row, 'name'), 'class' => Row::text($row, 'deduction_class'), 'type' => Row::nullable($row, 'code') ?? Row::nullable($row, 'fee_type') ?? '', 'amount' => Row::text($row, 'claimed_amount'), 'settled' => Row::text($row, 'settled_amount'), 'can_supersede' => $correct];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? Row::text($found[count($found) - 1], 'id') : null];
    }

    /**
     * The flagged payments a line arriving may cite (DATABASE §11.3; QS-12).
     *
     * @return list<array{id: string, account: string, date: string, client: string, net: string}>
     */
    private static function citable(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('payments as p')->where('p.company_id', $companyId)->whereNotNull('p.company_bank_account_id')->whereNull('p.bank_statement_line_id')->join('application_source_balances as b', 'b.payment_id', '=', 'p.id')->where('b.net_amount', '>', 0)->join('client_organizations as o', 'o.id', '=', 'p.client_organization_id')
            ->orderByDesc('p.id')->limit(100)->get(['p.public_id', 'p.company_bank_account_id', 'p.business_date', 'b.net_amount', 'o.name']) as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'account' => RowHandle::of('company_bank_accounts', (int) Row::text($row, 'company_bank_account_id')), 'date' => Row::text($row, 'business_date'), 'client' => Row::text($row, 'name'), 'net' => Row::text($row, 'net_amount')];
        }

        return $rows;
    }

    /** @return list<array{id: string, issuer: string, reference: ?string, date: string}> The company's registered statements (PJ-08). */
    private static function statements(int $companyId): array
    {
        $rows = [];

        foreach (DB::table('evidence_documents')->where('company_id', $companyId)->where('evidence_type', 'BANK_STATEMENT')->orderByDesc('id')->limit(100)->get(['public_id', 'declared_issuer', 'issuer_reference', 'document_date']) as $row) {
            $rows[] = ['id' => Row::text($row, 'public_id'), 'issuer' => Row::text($row, 'declared_issuer'), 'reference' => Row::nullable($row, 'issuer_reference'), 'date' => Row::text($row, 'document_date')];
        }

        return $rows;
    }
}
