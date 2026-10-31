<?php

namespace App\Modules\PRJ\Queries;

use App\Modules\DOC\Queries\ProjectDocuments;
use App\Modules\OPS\Queries\ProjectCases;
use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyFacts;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Modules\PRJ\Services\Journey;
use App\Support\Search\Row;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The journeys of a set of projects, derived each time (L-01; `DIR-077` CA-06): PRJ's own facts in two statements —
 * the projects with their confirmation, latest quotation state and, to a viewer with `finance.view`, whether the
 * invoiced value reaches the confirmed one; their lines' fulfilment and delivery by mode —, then the completion
 * predicates as set queries over the same projects: the returns predicate from OPS's cases and the documents readiness
 * from DOC, read directly, and every higher tier's through `CompletionPredicateProvider` (ARCHITECTURE §3). The
 * statements do not depend on how many projects are asked (QB-01; PFM-02).
 */
final class ProjectJourneys
{
    public function __construct(
        private readonly Container $app,
        private readonly ProjectCases $cases,
        private readonly ProjectDocuments $documents,
    ) {}

    /**
     * Each project's derived journey, its money items only where the viewer holds `finance.view` of its company (PJ-13):
     * the facts are read once for every project, each journey derived with its own project's family.
     *
     * @param  list<int>  $projectIds
     * @param  array<int, bool>  $money  by project, whether the viewer may see its money items
     * @param  array<int, ?string>  $editHrefs  "Ubah proyek" by project, for an account that may amend it
     * @param  array<int, ?string>  $quotationHrefs  PG-014 by project, for an account that may open its quotation
     * @param  array<int, ?string>  $serviceHrefs  PG-033 by project, for an account that may record its handover
     * @param  array<int, ?string>  $invoiceHrefs  PG-042 by project, for an account that may issue its invoice
     * @param  array<int, ?string>  $paymentHrefs  PG-046 by project, for an account that may record its payment
     * @return array<int, array{outcome: ?string, stages: list<array{key: string, state: string}>, not_needed: list<string>, current: ?string, position: ?int, blocked: bool, blockers: list<array<string, mixed>>, next: ?array<string, mixed>, remaining: list<array{predicate: string, items: list<array<string, mixed>>}>}>
     */
    public function of(array $projectIds, array $money, array $editHrefs = [], array $quotationHrefs = [], array $serviceHrefs = [], array $invoiceHrefs = [], array $paymentHrefs = []): array
    {
        $any = in_array(true, $money, true);
        $facts = $this->facts($projectIds, $any);
        $predicates = $this->predicates($projectIds, $any);
        $journeys = [];

        foreach ($facts as $id => $fact) {
            $journeys[$id] = Journey::derive($fact, $predicates[$id] ?? [], $money[$id] ?? false, $editHrefs[$id] ?? null, $quotationHrefs[$id] ?? null, $serviceHrefs[$id] ?? null, $invoiceHrefs[$id] ?? null, $paymentHrefs[$id] ?? null);
        }

        return $journeys;
    }

    /**
     * PRJ's own facts of each project, in two statements.
     *
     * @param  list<int>  $projectIds
     * @return array<int, JourneyFacts>
     */
    public function facts(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $projects = DB::table('projects as p')->whereIn('p.id', $projectIds)
            ->select(['p.id', 'p.state'])
            ->selectRaw('(p.client_unit_id is null or p.client_contact_id is null) as details_missing')
            ->selectRaw('exists (select 1 from commercial_confirmations c where c.project_id = p.id) as confirmed')
            ->selectRaw('(select q.state from quotation_revisions q where q.project_id = p.id order by q.revision_no desc limit 1) as quotation')
            ->when($money, fn (Builder $query) => $query->leftJoin('project_commercial_balances as b', 'b.project_id', '=', 'p.id')
                ->selectRaw('(b.confirmed_sales_value > 0 and b.invoiced_sales_value >= b.confirmed_sales_value) as invoiced'))
            ->get();
        // A line's demand is its current pin's once confirmed, its draft quantity before (DATABASE §9).
        $demand = 'coalesce(b.demand_qty_base, l.qty_base)';
        $lines = DB::table('project_lines as l')->whereIn('l.project_id', $projectIds)
            ->leftJoin('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->groupBy('l.project_id', 'l.fulfillment_mode')
            ->select(['l.project_id', 'l.fulfillment_mode'])
            ->selectRaw("bool_and(case l.fulfillment_mode
                when 'WAREHOUSE' then coalesce(b.dispatched_net_qty_base, 0) + coalesce(b.cancelled_qty_base, 0) >= {$demand}
                when 'DROPSHIP' then coalesce(b.dropship_confirmed_net_qty_base, 0) + coalesce(b.cancelled_qty_base, 0) >= {$demand}
                else coalesce(b.handed_over_qty_base, 0) + coalesce(b.cancelled_qty_base, 0) >= {$demand} end) as done")
            ->selectRaw("bool_and(l.fulfillment_mode = 'SERVICE' or coalesce(b.delivered_net_qty_base, 0) + coalesce(b.cancelled_qty_base, 0) >= {$demand}) as delivered")
            ->get();
        $modes = [];

        foreach ($lines as $line) {
            $modes[(int) Row::text($line, 'project_id')][Row::text($line, 'fulfillment_mode')] = ['done' => ($line->done ?? null) === true, 'delivered' => ($line->delivered ?? null) === true];
        }

        $facts = [];

        foreach ($projects as $project) {
            $id = (int) Row::text($project, 'id');
            $invoiced = $project->invoiced ?? null;
            $facts[$id] = new JourneyFacts(
                Row::text($project, 'state'),
                ($project->confirmed ?? null) === true,
                Row::nullable($project, 'quotation'),
                ($project->details_missing ?? null) === true,
                $modes[$id] ?? [],
                $money ? $invoiced === true : null,
            );
        }

        return $facts;
    }

    /**
     * The completion predicates of each project: the returns predicate from OPS's cases, the documents predicate from
     * DOC's readiness, then each registered provider's.
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, PredicateState>>
     */
    public function predicates(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $states = [];

        foreach ($this->cases->of($projectIds) as $project => $cases) {
            $open = array_sum(array_map(fn (array $kind): int => $kind['count'], $cases['open']));
            $states[$project][Predicates::RETURNS] = new PredicateState($cases['cases'] > 0,
                $open === 0 ? [] : [new JourneyItem('projects.item.cases_open', ['jumlah' => (string) $open], null, true)]);
        }

        foreach ($this->documents->readiness($projectIds) as $project => $readiness) {
            $states[$project][Predicates::DOCUMENTS] = new PredicateState($readiness['documents'] > 0,
                $readiness['failed'] === 0 ? [] : [new JourneyItem('projects.item.rendition_failed', ['jumlah' => (string) $readiness['failed']], 'documents', true)]);
        }

        foreach ($this->app->tagged(CompletionPredicateProvider::TAG) as $provider) {
            if (! $provider instanceof CompletionPredicateProvider) {
                continue;
            }

            foreach ($provider->states($projectIds, $money) as $project => $predicates) {
                foreach ($predicates as $key => $state) {
                    if (in_array($key, $provider->predicates(), true)) {
                        $states[$project][$key] = $state;
                    }
                }
            }
        }

        return $states;
    }
}
