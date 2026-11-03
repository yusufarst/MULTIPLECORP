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

        // The time a command ended a row: its log, else a row it wrote — the contra it named, a successor it created —, else the row's own.
        $applications = $ofSources(DB::table('payment_applications as a'), 'a')->leftJoin('invoices as i', 'i.id', '=', 'a.invoice_id')
            ->orderBy('a.id')
            ->get(['a.id', 'a.payment_id', 'a.opening_customer_credit_id', 'a.target_kind', 'a.amount', 'a.end_reason', 'a.command_id', 'a.end_command_id', 'a.supersedes_application_id', 'a.recorded_at', 'i.project_id',
                DB::raw('(select c.recorded_at from command_log c where c.command_id = a.command_id) as created_at'),
                DB::raw('coalesce((select c.recorded_at from command_log c where c.command_id = a.end_command_id), (select f.recorded_at from financial_contras f where f.id = a.end_contra_id),
                    (select min(s.recorded_at) from payment_applications s where s.supersedes_application_id = a.id), a.recorded_at) as ended_at')])->all();
        $contras = $ofSources(DB::table('financial_contras as f'), 'f')->orderBy('f.id')->get(['f.payment_id', 'f.opening_customer_credit_id', 'f.amount', 'f.command_id', 'f.recorded_at'])->all();

        $reapplied = [];

        foreach ($applications as $row) {
            $supersedes = Row::nullable($row, 'supersedes_application_id');

            if ($supersedes !== null) {
                $reapplied[(int) $supersedes] = Money::of($reapplied[(int) $supersedes] ?? '0')->plus(Money::of(Row::text($row, 'amount')))->toString();
            }
        }

        /** @var array<string, array<string, array{at: string, order: int, contra: string, ends: list<array{id: int, reason: string, project: ?int, amount: string, reapplied: string}>, creates: list<array{id: int, target: string, project: ?int, amount: string, successor: bool}>}>> $commands */
        $commands = [];
        $key = fn (object $row): string => Row::nullable($row, 'payment_id') !== null ? 'p'.Row::text($row, 'payment_id') : 'c'.Row::text($row, 'opening_customer_credit_id');
        $slot = function (string $source, string $command, string $at, int $order) use (&$commands): void {
            $commands[$source][$command] ??= ['at' => $at, 'order' => $order, 'contra' => '0.00', 'ends' => [], 'creates' => []];
        };

        foreach ($applications as $row) {
            $source = $key($row);
            $id = (int) Row::text($row, 'id');
            $project = Row::nullable($row, 'project_id') === null ? null : (int) Row::text($row, 'project_id');
            $command = Row::text($row, 'command_id');
            $slot($source, $command, Row::nullable($row, 'created_at') ?? Row::text($row, 'recorded_at'), $id);
            $commands[$source][$command]['creates'][] = ['id' => $id, 'target' => Row::text($row, 'target_kind'), 'project' => $project, 'amount' => Row::text($row, 'amount'), 'successor' => Row::nullable($row, 'supersedes_application_id') !== null];
            $ended = Row::nullable($row, 'end_command_id');

            if ($ended !== null) {
                $slot($source, $ended, Row::text($row, 'ended_at'), $id);
                $commands[$source][$ended]['ends'][] = ['id' => $id, 'reason' => Row::text($row, 'end_reason'), 'project' => $project, 'amount' => Row::text($row, 'amount'), 'reapplied' => $reapplied[$id] ?? '0.00'];
            }
        }

        foreach ($contras as $index => $row) {
            $source = $key($row);
            $command = Row::text($row, 'command_id');
            $slot($source, $command, Row::text($row, 'recorded_at'), PHP_INT_MAX - count($contras) + $index);
            $commands[$source][$command]['contra'] = Money::of($commands[$source][$command]['contra'])->plus(Money::of(Row::text($row, 'amount')))->toString();
        }

        $totals = [];

        foreach ($sources as $row) {
            $ordered = array_values($commands[$key($row)] ?? []);
            usort($ordered, fn (array $a, array $b): int => [$a['at'], $a['order']] <=> [$b['at'], $b['order']]);
            $replayed = CreditAttribution::replay(Row::text($row, 'gross_amount'), array_map(fn (array $command): array => ['contra' => $command['contra'], 'ends' => $command['ends'], 'creates' => $command['creates']], $ordered));

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
