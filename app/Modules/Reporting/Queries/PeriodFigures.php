<?php

namespace App\Modules\Reporting\Queries;

use App\Support\Finance\AgingCalculator;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * *Beranda*'s G5 *Angka Periode* (IA §9; PF-43; `DIR-079` CB-03; GAP-060): the period figures CALC-08–CALC-14 per
 * company — each concept on its own business date (CALC-13): revenue by its issue date, cost by its attribution or
 * expense date, Cash-In by payment date, Cash-Out by disbursement date — and, with `reports.consolidated`, *Gabungan*
 * as their sums (CALC-12; CS-08; PF-25); with them the receivables position — one grouped statement over
 * `invoice_balances` per company in scope: CALC-05's outstanding total and CALC-07's due-date buckets, the *migrasi*
 * bucket of opening receivables included (BR-FIN-14), a disputed amount kept with its indicator. Six statements read
 * in one REPEATABLE READ snapshot, so revenue, cost and profit are figures of one instant (TX-10; QB-10: at most
 * seven); the group is deferred and never part of a first response.
 *
 * - CALC-08: the issued, non-void LIVE invoices' sales value, output tax excluded (BR-FIN-17); an opening receivable
 *   moves no live-period revenue (BR-XD-06).
 * - CALC-09: the HPP and losses attributed to the company's projects and their expenses, each net of its contras.
 * - CALC-11: CALC-10's project profits — revenue less project cost — plus the INCOME-class other cash receipts, less
 *   the company-level losses and the expenses borne by no project.
 * - CALC-14: Cash-In — payments and other cash receipts — against Cash-Out — disbursements —, each net of its contras.
 *
 * @phpstan-type Figures array{company: ?string, revenue: string, direct_cost: string, project_profit: string, profit: string, cash_in: string, cash_out: string, receivable: array{outstanding: string, disputed: string, buckets: array<string, string>}}
 */
final class PeriodFigures
{
    /**
     * @param  array<int, string>  $companies  by row id: the code
     * @return array{as_of: string, from: string, to: string, companies: list<Figures>, consolidated: ?Figures}
     */
    public function read(array $companies, string $from, string $to, string $today, bool $consolidated): array
    {
        $ids = '{'.implode(',', array_map('intval', array_keys($companies))).'}';
        $read = function () use ($ids, $from, $to, $today): array {
            $revenue = $this->grouped(DB::table('invoices as i')->join('invoice_versions as v', 'v.id', '=', 'i.current_version_id')
                ->whereRaw('i.company_id = any (?::bigint[])', [$ids])->where('i.state', 'ISSUED')->where('i.origin', 'LIVE')->whereBetween('v.business_date', [$from, $to])
                ->groupBy('i.company_id')->selectRaw('i.company_id, sum(v.sales_value) as revenue'));
            $costs = $this->grouped(DB::table('cost_attributions as a')
                ->whereRaw('a.bearer_company_id = any (?::bigint[])', [$ids])->whereBetween('a.business_date', [$from, $to])
                ->groupBy('a.bearer_company_id')->selectRaw("a.bearer_company_id as company_id,
                    coalesce(sum(a.amount) filter (where a.bearer_project_id is not null and a.cost_class in ('HPP', 'LOSS')), 0) as project_cost,
                    coalesce(sum(a.amount) filter (where a.bearer_project_id is null and a.cost_class = 'LOSS'), 0) as company_loss"));
            $expenses = $this->grouped(DB::table('expenses as x')
                ->whereRaw('x.company_id = any (?::bigint[])', [$ids])->whereBetween('x.business_date', [$from, $to])
                ->groupBy('x.company_id')->selectRaw('x.company_id,
                    coalesce(sum(x.amount - coalesce((select sum(f.amount) from financial_contras f where f.expense_id = x.id), 0)) filter (where x.project_id is not null), 0) as project_expenses,
                    coalesce(sum(x.amount - coalesce((select sum(f.amount) from financial_contras f where f.expense_id = x.id), 0)) filter (where x.project_id is null), 0) as company_expenses'));
            $cashIn = $this->grouped(DB::query()->fromSub(
                DB::table('payments as p')->whereRaw('p.company_id = any (?::bigint[])', [$ids])->whereBetween('p.business_date', [$from, $to])
                    ->selectRaw('p.company_id, p.amount - coalesce((select sum(f.amount) from financial_contras f where f.payment_id = p.id), 0) as amount, 0 as income')
                    ->unionAll(DB::table('other_cash_receipts as r')->whereRaw('r.company_id = any (?::bigint[])', [$ids])->whereBetween('r.business_date', [$from, $to])
                        ->selectRaw("r.company_id, r.amount - coalesce((select sum(f.amount) from financial_contras f where f.other_cash_receipt_id = r.id), 0), case when r.income_class = 'INCOME' then r.amount - coalesce((select sum(f.amount) from financial_contras f where f.other_cash_receipt_id = r.id), 0) else 0 end")), 'c')
                ->groupBy('c.company_id')->selectRaw('c.company_id, sum(c.amount) as cash_in, sum(c.income) as income'));
            $cashOut = $this->grouped(DB::table('disbursements as d')->whereRaw('d.company_id = any (?::bigint[])', [$ids])->whereBetween('d.business_date', [$from, $to])
                ->groupBy('d.company_id')->selectRaw('d.company_id, sum(d.amount - coalesce((select sum(f.amount) from financial_contras f where f.disbursement_id = d.id), 0)) as cash_out'));
            $positions = [];
            // AgingCalculator builds it from a column name it checks against a pattern and a day it parses as one, so no
            // request text reaches it: the one bucket rule of CALC-07, never copied here.
            /** @var literal-string $bucket */
            $bucket = AgingCalculator::bucketSql('b.current_due_date', $today);

            // The receivables position over the partial index `invoice_balances_ix11_due` (QS-09–QS-11's), its buckets by AgingCalculator.
            foreach (DB::table('invoice_balances as b')->whereRaw('b.company_id = any (?::bigint[])', [$ids])->where('b.outstanding', '>', 0)->whereNotNull('b.current_billing_act_id')
                ->groupByRaw('b.company_id, 2')->select(['b.company_id', DB::raw($bucket.' as bucket'), DB::raw('sum(b.outstanding) as outstanding'),
                    DB::raw('coalesce(sum(b.outstanding) filter (where exists (select 1 from receivable_dispute_holds h where h.invoice_id = b.invoice_id and h.cleared_at is null)), 0) as disputed')])->get() as $row) {
                $positions[(int) Row::text($row, 'company_id')][Row::text($row, 'bucket')] = ['outstanding' => Row::text($row, 'outstanding'), 'disputed' => Row::text($row, 'disputed')];
            }

            return [$revenue, $costs, $expenses, $cashIn, $cashOut, $positions];
        };

        // One snapshot (TX-10): a transaction of its own when none is open; inside one — a test's — its reads share it.
        [$revenue, $costs, $expenses, $cashIn, $cashOut, $positions] = DB::connection()->transactionLevel() === 0
            ? DB::transaction(function () use ($read): array {
                DB::statement('set transaction isolation level repeatable read read only');

                return $read();
            })
            : $read();

        $figures = [];

        foreach ($companies as $id => $code) {
            $figures[] = self::figures($code, $revenue[$id] ?? [], $costs[$id] ?? [], $expenses[$id] ?? [], $cashIn[$id] ?? [], $cashOut[$id] ?? [], $positions[$id] ?? []);
        }

        return ['as_of' => now()->toIso8601ZuluString(), 'from' => $from, 'to' => $to, 'companies' => $figures, 'consolidated' => $consolidated && count($figures) > 1 ? self::sum($figures) : null];
    }

    /**
     * @return array<int, array<string, string>> company id => its columns
     */
    private function grouped(Builder $query): array
    {
        $rows = [];

        foreach ($query->get() as $row) {
            $columns = [];

            foreach ((array) $row as $column => $value) {
                $columns[(string) $column] = is_scalar($value) ? (string) $value : '0';
            }

            $rows[(int) ($columns['company_id'] ?? 0)] = $columns;
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $revenue
     * @param  array<string, string>  $costs
     * @param  array<string, string>  $expenses
     * @param  array<string, string>  $cashIn
     * @param  array<string, string>  $cashOut
     * @param  array<string, array{outstanding: string, disputed: string}>  $position
     * @return Figures
     */
    private static function figures(string $code, array $revenue, array $costs, array $expenses, array $cashIn, array $cashOut, array $position): array
    {
        $sales = Money::of($revenue['revenue'] ?? '0');
        $directCost = Money::sum(Money::of($costs['project_cost'] ?? '0'), Money::of($expenses['project_expenses'] ?? '0'));
        $projectProfit = $sales->minus($directCost);
        $companyProfit = Money::sum($projectProfit, Money::of($cashIn['income'] ?? '0'))->minus(Money::sum(Money::of($costs['company_loss'] ?? '0'), Money::of($expenses['company_expenses'] ?? '0')));
        $buckets = [];
        $outstanding = Money::of('0');
        $disputed = Money::of('0');

        foreach (AgingCalculator::BUCKETS as $bucket) {
            $amount = Money::of($position[$bucket]['outstanding'] ?? '0');
            $buckets[$bucket] = $amount->toString();
            $outstanding = Money::sum($outstanding, $amount);
            $disputed = Money::sum($disputed, Money::of($position[$bucket]['disputed'] ?? '0'));
        }

        return [
            'company' => $code,
            'revenue' => $sales->toString(),
            'direct_cost' => $directCost->toString(),
            'project_profit' => $projectProfit->toString(),
            'profit' => $companyProfit->toString(),
            'cash_in' => Money::of($cashIn['cash_in'] ?? '0')->toString(),
            'cash_out' => Money::of($cashOut['cash_out'] ?? '0')->toString(),
            'receivable' => ['outstanding' => $outstanding->toString(), 'disputed' => $disputed->toString(), 'buckets' => $buckets],
        ];
    }

    /**
     * *Gabungan*: CALC-12, the sums of the companies' figures — the Owner's alone (`reports.consolidated`; CS-08).
     *
     * @param  list<Figures>  $figures
     * @return Figures
     */
    private static function sum(array $figures): array
    {
        $keys = ['revenue', 'direct_cost', 'project_profit', 'profit', 'cash_in', 'cash_out'];
        $totals = array_fill_keys($keys, Money::zero());
        $outstanding = Money::zero();
        $disputed = Money::zero();
        $buckets = array_fill_keys(AgingCalculator::BUCKETS, Money::zero());

        foreach ($figures as $row) {
            foreach ($keys as $key) {
                $totals[$key] = $totals[$key]->plus(Money::of($row[$key]));
            }

            $outstanding = $outstanding->plus(Money::of($row['receivable']['outstanding']));
            $disputed = $disputed->plus(Money::of($row['receivable']['disputed']));

            foreach (AgingCalculator::BUCKETS as $bucket) {
                $buckets[$bucket] = $buckets[$bucket]->plus(Money::of($row['receivable']['buckets'][$bucket] ?? '0'));
            }
        }

        return [
            'company' => null,
            'revenue' => $totals['revenue']->toString(),
            'direct_cost' => $totals['direct_cost']->toString(),
            'project_profit' => $totals['project_profit']->toString(),
            'profit' => $totals['profit']->toString(),
            'cash_in' => $totals['cash_in']->toString(),
            'cash_out' => $totals['cash_out']->toString(),
            'receivable' => ['outstanding' => $outstanding->toString(), 'disputed' => $disputed->toString(), 'buckets' => array_map(fn (Money $amount): string => $amount->toString(), $buckets)],
        ];
    }
}
