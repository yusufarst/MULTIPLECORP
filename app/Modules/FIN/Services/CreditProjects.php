<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Data\CreditAttribution;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What each project's credit stands at (WF-FIN-06; `DIR-104`): every source that ever applied money to its invoices replayed by `CreditAttribution` from its own rows in
 * recorded order — what has arisen for the project and what of it is left —, never stored. In a constant number of statements whatever the rows (QB-02).
 */
final class CreditProjects
{
    /**
     * The projects' credit, from every source that ever applied money to their invoices.
     *
     * @param  list<int>  $projectIds
     * @return array<int, array{arisen: string, left: string}>
     */
    public static function ofProjects(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $touching = fn (Builder $where) => $where->from('payment_applications as t')->join('invoices as v', 'v.id', '=', 't.invoice_id')->whereIn('v.project_id', $projectIds);
        $figures = self::replay(fn (Builder $sources) => $sources->where(fn (Builder $either) => $either
            ->whereIn('b.payment_id', fn (Builder $ids) => $touching($ids)->whereNotNull('t.payment_id')->select('t.payment_id'))
            ->orWhereIn('b.opening_customer_credit_id', fn (Builder $ids) => $touching($ids)->whereNotNull('t.opening_customer_credit_id')->select('t.opening_customer_credit_id'))));

        return array_intersect_key($figures, array_flip($projectIds));
    }

    /**
     * The projects' credit from a company's clients' sources.
     *
     * @param  list<int>  $clientIds
     * @return array<int, array{arisen: string, left: string}>
     */
    public static function ofClients(int $companyId, array $clientIds): array
    {
        return $clientIds === [] ? [] : self::replay(fn (Builder $sources) => $sources->where('b.company_id', $companyId)->whereIn('b.client_organization_id', $clientIds));
    }

    /**
     * Each chosen source replayed, the figures summed per project.
     *
     * @param  callable(Builder): Builder  $choose
     * @return array<int, array{arisen: string, left: string}>
     */
    private static function replay(callable $choose): array
    {
        $sources = $choose(DB::table('application_source_balances as b'))->orderBy('b.payment_id')->orderBy('b.opening_customer_credit_id')->get(['b.payment_id', 'b.opening_customer_credit_id', 'b.gross_amount'])->all();

        if ($sources === []) {
            return [];
        }

        $payments = array_values(array_filter(array_map(fn (object $row): ?int => Row::nullable($row, 'payment_id') === null ? null : (int) Row::text($row, 'payment_id'), $sources), is_int(...)));
        $credits = array_values(array_filter(array_map(fn (object $row): ?int => Row::nullable($row, 'opening_customer_credit_id') === null ? null : (int) Row::text($row, 'opening_customer_credit_id'), $sources), is_int(...)));
        $ofSources = fn (Builder $where, string $alias) => $where->where(fn (Builder $either) => $either->whereIn("{$alias}.payment_id", $payments === [] ? [0] : $payments)->orWhereIn("{$alias}.opening_customer_credit_id", $credits === [] ? [0] : $credits));

        // Commands in the order they committed, read from what they wrote and never from the purged command log (JB-08): a row's own time, an ending command's the contra
        // it named, else a successor it created, else its first audit event; the row's id breaking ties.
        $applications = $ofSources(DB::table('payment_applications as a'), 'a')->leftJoin('invoices as i', 'i.id', '=', 'a.invoice_id')
            ->orderBy('a.id')
            ->get(['a.id', 'a.payment_id', 'a.opening_customer_credit_id', 'a.target_kind', 'a.amount', 'a.end_reason', 'a.command_id', 'a.end_command_id', 'a.supersedes_application_id', 'a.recorded_at', 'i.project_id',
                DB::raw('(select x.payment_id from payment_applications x where x.id = a.supersedes_application_id) as superseded_payment'),
                DB::raw('(select x.opening_customer_credit_id from payment_applications x where x.id = a.supersedes_application_id) as superseded_credit'),
                DB::raw('coalesce((select f.recorded_at from financial_contras f where f.id = a.end_contra_id), (select min(s.recorded_at) from payment_applications s where s.supersedes_application_id = a.id),
                    (select min(e.occurred_at) from audit_events e where e.command_id = a.end_command_id), a.recorded_at) as ended_at')])->all();
        $contras = $ofSources(DB::table('financial_contras as f'), 'f')->orderBy('f.id')->get(['f.payment_id', 'f.opening_customer_credit_id', 'f.amount', 'f.command_id', 'f.recorded_at'])->all();

        $key = fn (object $row): string => Row::nullable($row, 'payment_id') !== null ? 'p'.Row::text($row, 'payment_id') : 'c'.Row::text($row, 'opening_customer_credit_id');
        // A successor on the same source carries its ended row's money; one on another source — a re-recording's move — is that source's own new application.
        $sameSource = fn (object $row): ?int => Row::nullable($row, 'supersedes_application_id') !== null
            && (Row::nullable($row, 'superseded_payment') !== null ? 'p'.Row::text($row, 'superseded_payment') : 'c'.(Row::nullable($row, 'superseded_credit') ?? '')) === $key($row)
            ? (int) Row::text($row, 'supersedes_application_id') : null;
        $reapplied = [];

        foreach ($applications as $row) {
            $supersedes = $sameSource($row);

            if ($supersedes !== null) {
                $reapplied[$supersedes] = Money::of($reapplied[$supersedes] ?? '0')->plus(Money::of(Row::text($row, 'amount')))->toString();
            }
        }

        /** @var array<string, array<string, array{at: string, order: int}>> $times */
        $times = [];
        /** @var array<string, array<string, string>> $contra */
        $contra = [];
        /** @var array<string, array<string, list<array{id: int, reason: string, project: ?int, amount: string, reapplied: string}>>> $ends */
        $ends = [];
        /** @var array<string, array<string, list<array{id: int, target: string, project: ?int, amount: string, supersedes: ?int}>>> $creates */
        $creates = [];
        foreach ($applications as $row) {
            $source = $key($row);
            $id = (int) Row::text($row, 'id');
            $project = Row::nullable($row, 'project_id') === null ? null : (int) Row::text($row, 'project_id');
            $command = Row::text($row, 'command_id');
            $times[$source][$command] ??= ['at' => Row::text($row, 'recorded_at'), 'order' => $id];
            $creates[$source][$command][] = ['id' => $id, 'target' => Row::text($row, 'target_kind'), 'project' => $project, 'amount' => Row::text($row, 'amount'), 'supersedes' => $sameSource($row)];
            $ended = Row::nullable($row, 'end_command_id');

            if ($ended !== null) {
                $times[$source][$ended] ??= ['at' => Row::text($row, 'ended_at'), 'order' => $id];
                $ends[$source][$ended][] = ['id' => $id, 'reason' => Row::text($row, 'end_reason'), 'project' => $project, 'amount' => Row::text($row, 'amount'), 'reapplied' => $reapplied[$id] ?? '0.00'];
            }
        }

        foreach ($contras as $index => $row) {
            $source = $key($row);
            $command = Row::text($row, 'command_id');
            $times[$source][$command] ??= ['at' => Row::text($row, 'recorded_at'), 'order' => PHP_INT_MAX - count($contras) + $index];
            $contra[$source][$command] = Money::of($contra[$source][$command] ?? '0')->plus(Money::of(Row::text($row, 'amount')))->toString();
        }

        $totals = [];

        foreach ($sources as $row) {
            $source = $key($row);
            $order = $times[$source] ?? [];
            uasort($order, fn (array $a, array $b): int => [$a['at'], $a['order']] <=> [$b['at'], $b['order']]);
            $commands = [];

            foreach (array_keys($order) as $command) {
                $commands[] = ['contra' => $contra[$source][$command] ?? '0.00', 'ends' => $ends[$source][$command] ?? [], 'creates' => $creates[$source][$command] ?? []];
            }

            $replayed = CreditAttribution::replay(Row::text($row, 'gross_amount'), $commands);

            foreach ($replayed['projects'] as $project => $figures) {
                $totals[$project] = [
                    'arisen' => Money::of($totals[$project]['arisen'] ?? '0')->plus(Money::of($figures['arisen']))->toString(),
                    'left' => Money::of($totals[$project]['left'] ?? '0')->plus(Money::of($figures['left']))->toString(),
                ];
            }
        }

        return $totals;
    }
}
