<?php

namespace App\Modules\FIN\Queries;

use App\Modules\FIN\Data\InvoicePosition;
use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use App\Support\Finance\AgingCalculator;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PG-044 *Piutang* (ROUTE-044; NAV-044; SCR-40; D-IA-15; J-FIN-02): by branch, the tabs *Aktif*, *Belum Ditagihkan* and *Sengketa*, oldest due first by keyset (IX-11),
 * each row with its company where several are in scope; a subtotal per company and its ageing buckets (CALC-07), never a total across companies (CS-08) — the
 * Owner's consolidated figure is PG-052's. `finance.view` within its company (PJ-23; SM-25). In at most four statements, ageing one group (QB-07).
 */
final class ReceivableList
{
    public const PAGE = 50;

    public const TABS = ['aktif', 'belum', 'sengketa'];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function screen(int $viewerId, ?string $branch, ?string $tab, ?string $client, ?string $after): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $tab = in_array($tab, self::TABS, true) ? $tab : 'aktif';
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
        $today = BusinessDate::today();

        return [
            'branches' => array_values($branches),
            'branch' => $chosen === null ? null : $branches[$chosen]['id'],
            'tab' => $tab,
            'client' => $clientKey,
            'clients' => $companies === [] ? [] : $this->clients($viewer, $companies, $tab),
            'subtotals' => $companies === [] ? [] : $this->subtotals($viewer, $companies, $tab, $clientKey, $today, $branches),
            ...($companies === [] ? ['rows' => [], 'more' => null] : $this->page($viewer, $companies, $tab, $clientKey, $after, $today, $branches)),
            'today' => $today,
        ];
    }

    /**
     * One page of the tab after the cursor, one more read to know whether more follow.
     *
     * @param  list<int>  $companies
     * @param  array<int, array{id: string, code: string, name: string}>  $branches
     * @return array{rows: list<array<string, mixed>>, more: ?string}
     */
    public function page(Authority $viewer, array $companies, string $tab, ?string $client, ?string $after, string $today, array $branches): array
    {
        $found = $this->scoped($viewer, $companies, $tab, $client)
            ->when(is_string($after) && preg_match(self::UUID, $after) === 1, fn (Builder $where) => $where->whereRaw(
                "(coalesce(b.current_due_date, date '0001-01-01'), b.invoice_id) > (select coalesce(c.current_due_date, date '0001-01-01'), c.invoice_id from invoice_balances c join invoices n on n.id = c.invoice_id where n.public_id = ? and n.company_id = any(?::bigint[]))",
                [strtolower((string) $after), '{'.implode(',', $companies).'}'],
            ))
            ->leftJoin('projects as p', 'p.id', '=', 'i.project_id')
            ->leftJoin('invoice_billing_acts as a', 'a.id', '=', 'b.current_billing_act_id')
            ->orderByRaw("coalesce(b.current_due_date, date '0001-01-01'), b.invoice_id")
            ->limit(self::PAGE + 1)
            ->selectRaw("i.public_id, i.state, i.company_id, b.outstanding, b.written_off_amount, b.gross_value, b.current_billing_act_id, b.current_due_date, a.billed_at, p.public_id as project, p.project_number, o.name as client,
                exists (select 1 from receivable_dispute_holds h where h.invoice_id = i.id and h.cleared_at is null) as on_hold,
                (select n.formatted_number from documents d join document_versions n on n.document_id = d.id where d.invoice_id = i.id and d.document_type_code in ('DOC-04', 'DOC-06') and n.formatted_number is not null order by n.state = 'ISSUED' desc, d.id, n.id desc limit 1) as number")
            ->get()->all();
        $more = count($found) > self::PAGE;
        $rows = [];

        foreach (array_slice($found, 0, self::PAGE) as $row) {
            $company = (int) Row::text($row, 'company_id');
            $due = Row::nullable($row, 'current_due_date');
            $billed = Row::nullable($row, 'current_billing_act_id') !== null;
            $rows[] = [
                'id' => Row::text($row, 'public_id'),
                'number' => Row::nullable($row, 'number'),
                'company' => $branches[$company]['code'] ?? '',
                'client' => Row::text($row, 'client'),
                'project' => Row::nullable($row, 'project'),
                'project_number' => Row::nullable($row, 'project_number'),
                'billed_at' => Row::nullable($row, 'billed_at'),
                'due_date' => $due,
                'days_past' => $billed && $due !== null ? AgingCalculator::daysPast($due, $today) : 0,
                'bucket' => $billed ? AgingCalculator::bucket($due, $today) : null,
                'outstanding' => Row::text($row, 'outstanding'),
                'held' => ($row->on_hold ?? false) === true,
                'state' => InvoicePosition::of(Row::text($row, 'state'), $billed, $due, $today, Row::text($row, 'outstanding'), Row::text($row, 'written_off_amount'), ($row->on_hold ?? false) === true),
                'href' => '/keuangan/invoice/'.Row::text($row, 'public_id'),
                'can' => ['bill' => ! $billed && $viewer->allows('billing.record', $company), 'pay' => $viewer->allows('payment.record', $company)],
            ];
        }

        return ['rows' => $rows, 'more' => $more && $rows !== [] ? $rows[count($rows) - 1]['id'] : null];
    }

    /**
     * Each company's subtotal of the tab and its ageing buckets, one group; no row sums them across companies (CS-08).
     *
     * @param  list<int>  $companies
     * @param  array<int, array{id: string, code: string, name: string}>  $branches
     * @return list<array{company: string, name: string, count: int, amount: string, buckets: array<string, string>}>
     */
    public function subtotals(Authority $viewer, array $companies, string $tab, ?string $client, string $today, array $branches): array
    {
        /** @var literal-string $bucket the calculator validated both parts */
        $bucket = AgingCalculator::bucketSql('b.current_due_date', $today);
        $grouped = [];

        foreach ($this->scoped($viewer, $companies, $tab, $client)->groupByRaw("i.company_id, {$bucket}")->selectRaw("i.company_id, {$bucket} as bucket, count(*) as rows_counted, sum(b.outstanding) as amount")->get() as $row) {
            $company = (int) Row::text($row, 'company_id');
            $grouped[$company] ??= ['company' => $branches[$company]['code'] ?? '', 'name' => $branches[$company]['name'] ?? '', 'count' => 0, 'amount' => '0.00', 'buckets' => []];
            $grouped[$company]['count'] += (int) Row::text($row, 'rows_counted');
            $grouped[$company]['amount'] = Money::of($grouped[$company]['amount'])->plus(Money::of(Row::text($row, 'amount')))->toString();
            $grouped[$company]['buckets'][Row::text($row, 'bucket')] = Row::text($row, 'amount');
        }

        usort($grouped, fn (array $a, array $b): int => $a['company'] <=> $b['company']);

        return $grouped;
    }

    /**
     * The clients with a row in the tab, for the phone's lookup of a client's receivables (J-FIN-02; NV-06).
     *
     * @param  list<int>  $companies
     * @return list<array{id: string, name: string}>
     */
    private function clients(Authority $viewer, array $companies, string $tab): array
    {
        $clients = [];

        foreach ($this->scoped($viewer, $companies, $tab, null)->distinct()->orderBy('o.name')->orderBy('o.public_id')->limit(200)->get(['o.public_id', 'o.name']) as $row) {
            $clients[] = ['id' => Row::text($row, 'public_id'), 'name' => Row::text($row, 'name')];
        }

        return $clients;
    }

    /** @param  list<int>  $companies */
    private function scoped(Authority $viewer, array $companies, string $tab, ?string $client): Builder
    {
        return $viewer->scope->apply(DB::table('invoices as i'), 'i.company_id')
            ->whereIn('i.company_id', $companies)
            ->where('i.state', 'ISSUED')
            ->join('invoice_balances as b', 'b.invoice_id', '=', 'i.id')
            ->join('client_organizations as o', 'o.id', '=', 'i.client_organization_id')
            ->when($client !== null, fn (Builder $where) => $where->where('o.public_id', $client))
            ->when($tab === 'belum', fn (Builder $where) => $where->whereNull('b.current_billing_act_id'))
            ->when($tab !== 'belum', fn (Builder $where) => $where->whereNotNull('b.current_billing_act_id')->where('b.outstanding', '>', 0))
            ->when($tab === 'sengketa', fn (Builder $where) => $where->whereExists(fn (Builder $hold) => $hold->from('receivable_dispute_holds as h')->whereColumn('h.invoice_id', 'i.id')->whereNull('h.cleared_at')));
    }
}
