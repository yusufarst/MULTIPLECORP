<?php

namespace App\Modules\Reporting\Signals;

use App\Modules\PRJ\Queries\ProjectJourneys;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * QS-15 (WORKFLOWS §10; IA §9.1; PERFORMANCE §7): completion blockers per project, invoicing above delivered value and
 * confirmed value not yet invoiced — a group of its own (QB-10). One statement reads, per company, its oldest ACTIVE
 * projects on `projects (company_id, state, id)` (IX-14) with their commercial guard and their delivered value from
 * the pins; then the ten completion predicates of TASK-055 run as one set query each over those project ids, never
 * one per project (PRJ ProjectJourneys). RD-03; the money items only with `finance.view` in the company (RD-04;
 * PJ-22): a project's money items and blockers leave the row for a viewer without the family. A blocked project opens
 * PG-012; confirmed value not yet invoiced "Buat invoice" → PG-042 for `invoice.issue`, otherwise PG-011.
 */
final class QS15CompletionBlockers extends Signal
{
    /** The value delivered or handed over, priced by each line's pin (CALC-04). */
    private const DELIVERED = '(select coalesce(sum((lb.delivered_net_qty_base + lb.handed_over_qty_base) * cp.sales_value / nullif(cp.qty_base, 0)), 0) from project_lines l join project_line_balances lb on lb.project_line_id = l.id join confirmation_line_pins cp on cp.id = l.current_pin_id where l.project_id = p.id)';

    public function __construct(private readonly ProjectJourneys $journeys) {}

    public function code(): string
    {
        return 'QS-15';
    }

    public function companies(Context $context): array
    {
        return $context->holding('projects.view');
    }

    protected function branch(Context $context, int $company): Builder
    {
        return DB::table('projects as p')->leftJoin('project_commercial_balances as c', 'c.project_id', '=', 'p.id')
            ->where('p.company_id', $company)->where('p.state', 'ACTIVE')->orderBy('p.id')
            ->selectRaw('p.id, p.company_id, p.project_number, p.title, p.public_id::text as target, p.recorded_at::text as at,
                greatest(coalesce(c.confirmed_sales_value - c.invoiced_sales_value, 0), 0)::text as uninvoiced,
                greatest(coalesce(c.invoiced_sales_value, 0) - '.self::DELIVERED.', 0)::text as over_invoiced');
    }

    public function read(Context $context): ?array
    {
        $companies = $this->companies($context);

        if ($companies === []) {
            return null;
        }

        $union = $this->branch($context, $companies[0])->limit(self::BOUND + 1);

        foreach (array_slice($companies, 1) as $company) {
            $union->unionAll($this->branch($context, $company)->limit(self::BOUND + 1));
        }

        $projects = $union->get()->all();
        $states = $this->journeys->predicates(array_values(array_map(fn (object $row): int => (int) Row::text($row, 'id'), $projects)), true);
        $rows = [];
        $perCompany = [];

        foreach ($projects as $project) {
            $id = (int) Row::text($project, 'id');
            $company = (int) Row::text($project, 'company_id');
            $money = $context->viewer->allows('finance.view', $company);
            $unmet = 0;

            foreach ($states[$id] ?? [] as $state) {
                $unmet += ($money ? $state : $state->withoutMoney())->met() ? 0 : 1;
            }

            $found = [];

            if ($unmet > 0) {
                $found[] = ['kind' => 'unmet', 'value' => (string) $unmet, 'href' => '/proyek/'.Row::text($project, 'target').'/penyelesaian'];
            }

            if ($money && BigDecimal::of(Row::text($project, 'uninvoiced'))->isPositive()) {
                $found[] = ['kind' => 'not_invoiced', 'value' => Row::text($project, 'uninvoiced'),
                    'href' => $context->viewer->allows('invoice.issue', $company) ? '/keuangan/invoice/baru?proyek='.Row::text($project, 'target') : '/proyek/'.Row::text($project, 'target')];
            }

            if ($money && BigDecimal::of(Row::text($project, 'over_invoiced'))->isPositive()) {
                $found[] = ['kind' => 'over_invoiced', 'value' => Row::text($project, 'over_invoiced'), 'href' => '/proyek/'.Row::text($project, 'target')];
            }

            if ($found !== []) {
                $perCompany[$company] = ($perCompany[$company] ?? 0) + 1;
            }

            foreach ($found as $item) {
                $rows[] = ['id' => $item['kind'].$id, 'company' => $context->code($company), 'subject' => Row::text($project, 'project_number'), 'ref' => $item['kind'],
                    'value' => $item['value'], 'unit' => null, 'at' => Row::text($project, 'at'), 'href' => $item['href']];
            }
        }

        $counts = [];

        foreach ($perCompany as $company => $count) {
            $counts[] = ['company' => $context->code($company) ?? '', 'count' => $count > self::BOUND ? self::BOUND.'+' : (string) $count];
        }

        usort($counts, fn (array $a, array $b): int => strcmp($a['company'], $b['company']));

        return ['code' => $this->code(), 'rows' => array_slice($rows, 0, self::ROWS), 'counts' => $counts, 'more' => $this->seeAll($context)];
    }

    protected function href(Context $context, object $row): ?string
    {
        return null;
    }

    public function seeAll(Context $context): string
    {
        return '/proyek';
    }
}
