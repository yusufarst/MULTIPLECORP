<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\ReadClass;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Profit and margin (RD-06; CALC-08–CALC-11; CALC-13; REF-045; BR-FIN-01; PJ-22): with `profit.view` — which holds
 * `finance.view` and `cost.view` (RG-06) —, each company's projects with revenue or cost in the period — revenue by
 * its issue date (CALC-08), direct cost by its attribution or expense date (CALC-09), profit and margin (CALC-10,
 * margin only where revenue is above zero) —; the company's managerial profit (CALC-11): the projects' profit, the
 * INCOME-class other cash receipts, less the company-level losses and the expenses borne by no project; and the
 * company's own exposure to what waits on the Owner, never yet a cost (MSG-41; PJ-22). No SPJ, SIPLAH or reference
 * value enters any figure (FS-13). Three statements.
 */
final class ProfitReport implements Report
{
    private const EXPENSE_NET = 'x.amount - coalesce((select sum(f.amount) from financial_contras f where f.expense_id = x.id), 0)';

    public function code(): string
    {
        return 'laba';
    }

    public function opens(Authority $viewer): array
    {
        return ReadClass::Profitability->admits($viewer) ? ['profit.view'] : [];
    }

    public function masking(Authority $viewer): array
    {
        return [];
    }

    public function tables(Authority $viewer, Filters $filters): array
    {
        $companies = $filters->companyIds();
        $period = [$filters->period->from, $filters->period->to];
        $revenue = "coalesce((select sum(v.sales_value) from invoices i join invoice_versions v on v.id = i.current_version_id where i.project_id = j.id and i.state = 'ISSUED' and i.origin = 'LIVE' and v.business_date between ?::date and ?::date), 0)";
        $cost = "coalesce((select sum(a.amount) from cost_attributions a where a.bearer_project_id = j.id and a.cost_class in ('HPP', 'LOSS') and a.business_date between ?::date and ?::date), 0)
            + coalesce((select sum(".self::EXPENSE_NET.') from expenses x where x.project_id = j.id and x.business_date between ?::date and ?::date), 0)';
        $projects = DB::query()->fromSub(DB::table('projects as j')->whereIn('j.company_id', $companies)
            ->selectRaw("j.id, j.company_id, j.project_number, j.title, {$revenue} as revenue, {$cost} as cost", [...$period, ...$period, ...$period]), 'q')
            ->where(fn (Builder $active) => $active->where('q.revenue', '<>', 0)->orWhere('q.cost', '<>', 0))
            ->selectRaw('q.id, q.company_id, q.project_number, q.title, q.revenue, q.cost, q.revenue - q.cost as profit, case when q.revenue > 0 then round((q.revenue - q.cost) * 100 / q.revenue, 1) end as margin');

        return [
            new Table('proyek', [new Column('project'), new Column('title'), new Column('revenue', 'money', true), new Column('direct_cost', 'money', true), new Column('profit', 'money', true), new Column('margin', 'percent')], $companies,
                function () use ($projects): iterable {
                    foreach (Keyed::rows($projects, ['q.company_id' => 'company_id', 'q.id' => 'id']) as $row) {
                        yield [(int) Row::text($row, 'company_id'), [Row::text($row, 'project_number'), Row::text($row, 'title'), Row::text($row, 'revenue'), Row::text($row, 'cost'), Row::text($row, 'profit'), Row::nullable($row, 'margin')]];
                    }
                },
                fn (): int => (clone $projects)->count()),
            new Table('perusahaan', [new Column('revenue', 'money'), new Column('direct_cost', 'money'), new Column('project_profit', 'money'), new Column('other_income', 'money'), new Column('company_cost', 'money'), new Column('profit', 'money')], $companies,
                fn (): iterable => $this->companies($companies, $period),
                fn (): int => count($companies)),
            StockReport::exposures('paparan', $companies),
        ];
    }

    /**
     * CALC-11 per company, each concept on its own business date (CALC-13).
     *
     * @param  list<int>  $companies
     * @param  array{0: string, 1: string}  $period
     * @return iterable<array{0: int, 1: list<string>}>
     */
    private function companies(array $companies, array $period): iterable
    {
        $rows = DB::table('companies as c')->whereIn('c.id', $companies)->selectRaw(<<<'SQL'
            c.id,
            coalesce((select sum(v.sales_value) from invoices i join invoice_versions v on v.id = i.current_version_id where i.company_id = c.id and i.state = 'ISSUED' and i.origin = 'LIVE' and v.business_date between ?::date and ?::date), 0) as revenue,
            coalesce((select sum(a.amount) from cost_attributions a where a.bearer_company_id = c.id and a.bearer_project_id is not null and a.cost_class in ('HPP', 'LOSS') and a.business_date between ?::date and ?::date), 0) as project_cost,
            coalesce((select sum(x.amount - coalesce((select sum(f.amount) from financial_contras f where f.expense_id = x.id), 0)) from expenses x where x.company_id = c.id and x.project_id is not null and x.business_date between ?::date and ?::date), 0) as project_expenses,
            coalesce((select sum(r.amount - coalesce((select sum(f.amount) from financial_contras f where f.other_cash_receipt_id = r.id), 0)) from other_cash_receipts r where r.company_id = c.id and r.income_class = 'INCOME' and r.business_date between ?::date and ?::date), 0) as income,
            coalesce((select sum(a.amount) from cost_attributions a where a.bearer_company_id = c.id and a.bearer_project_id is null and a.cost_class = 'LOSS' and a.business_date between ?::date and ?::date), 0) as company_loss,
            coalesce((select sum(x.amount - coalesce((select sum(f.amount) from financial_contras f where f.expense_id = x.id), 0)) from expenses x where x.company_id = c.id and x.project_id is null and x.business_date between ?::date and ?::date), 0) as company_expenses
            SQL, [...$period, ...$period, ...$period, ...$period, ...$period, ...$period])->get()->keyBy(fn (object $row): int => (int) Row::text($row, 'id'));

        foreach ($companies as $company) {
            $row = $rows->get($company);
            $figure = fn (string $column): Money => Money::of(is_object($row) ? Row::text($row, $column) : '0');
            $directCost = Money::sum($figure('project_cost'), $figure('project_expenses'));
            $projectProfit = $figure('revenue')->minus($directCost);
            $companyCost = Money::sum($figure('company_loss'), $figure('company_expenses'));
            yield [$company, [$figure('revenue')->toString(), $directCost->toString(), $projectProfit->toString(), $figure('income')->toString(), $companyCost->toString(),
                Money::sum($projectProfit, $figure('income'))->minus($companyCost)->toString()]];
        }
    }
}
