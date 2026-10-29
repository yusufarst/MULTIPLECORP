<?php

namespace App\Modules\INV\Services;

use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Contracts\ProjectLifecycleParticipant;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Support\Command\CommandContext;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * INV's part of a project's lifecycle (ARCHITECTURE §3; TASK-038), inside PRJ's transaction under its LK-01: a
 * re-confirmation's lower demand lowers the reservations above it (AX-13; BR-QUO-02), a cancelled or split remainder releases
 * what it held (AX-14; SF-CANCEL-LINE; L-21), a cancelled project releases every active reservation (AX-27; BR-PRJ-01) —
 * each through the reservation service, its failure rolling PRJ's command back. And INV's part of the stock predicate: no
 * active reservation of the project (WF-PRJ-03 predicate 8), a residual one listed to be released (QS-16).
 */
final class ReservationLifecycle implements CompletionPredicateProvider, ProjectLifecycleParticipant
{
    private const RECONFIRMED = 'Konfirmasi ulang menurunkan permintaan baris.';

    private const SCOPE_REDUCED = 'Sisa baris dibatalkan atau dipindahkan.';

    private const CANCELLED = 'Proyek dibatalkan.';

    public function __construct(private readonly InventoryPosting $reservations) {}

    public function confirmed(CommandContext $context, int $projectId): void {}

    /** PRJ has pinned the new demand and moves the guards next: each reservation fits the new pin's remainder first. */
    public function reconfirmed(CommandContext $context, int $projectId): void
    {
        $this->reservations->fit($context, self::allowed(<<<'SQL'
            select l.id, coalesce(p.qty_base, 0) - coalesce(b.dispatched_net_qty_base, 0) - coalesce(b.cancelled_qty_base, 0) as allowed
            from project_lines l join reservations r on r.project_line_id = l.id and r.state = 'ACTIVE'
            left join confirmation_line_pins p on p.id = l.current_pin_id left join project_line_balances b on b.project_line_id = l.id
            where l.project_id = ? order by l.id
            SQL, $projectId), self::RECONFIRMED);
    }

    /** @param  array<int, string>  $released */
    public function scopeReduced(CommandContext $context, int $projectId, array $released): void
    {
        $allowed = [];

        foreach (self::allowed(<<<'SQL'
            select l.id, b.demand_qty_base - b.dispatched_net_qty_base - b.cancelled_qty_base as allowed
            from project_lines l join reservations r on r.project_line_id = l.id and r.state = 'ACTIVE' join project_line_balances b on b.project_line_id = l.id
            where l.project_id = ? order by l.id
            SQL, $projectId) as $line => $max) {
            if (isset($released[$line])) {
                $allowed[$line] = (string) BigDecimal::of($max)->minus($released[$line]);
            }
        }

        $this->reservations->fit($context, $allowed, self::SCOPE_REDUCED);
    }

    public function cancelled(CommandContext $context, int $projectId): void
    {
        $this->reservations->fit($context, array_map(fn (): string => '0', self::allowed(<<<'SQL'
            select l.id, 0 as allowed from project_lines l join reservations r on r.project_line_id = l.id and r.state = 'ACTIVE'
            where l.project_id = ? order by l.id
            SQL, $projectId)), self::CANCELLED);
    }

    public function revalidated(CommandContext $context, int $projectId): void {}

    /** @return list<string> */
    public function predicates(): array
    {
        return [Predicates::STOCK];
    }

    /**
     * Predicate 8's reservation part for each project with a warehouse line: met while no reservation of it is ACTIVE, one
     * blocker naming how many remain to be released otherwise — one set query (QB-01).
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, PredicateState>>
     */
    public function states(array $projectIds, bool $money): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('project_lines as l')->leftJoin('reservations as r', fn (JoinClause $join) => $join->on('r.project_line_id', '=', 'l.id')->where('r.state', 'ACTIVE'))
            ->whereIn('l.project_id', $projectIds)->where('l.fulfillment_mode', 'WAREHOUSE')->groupBy('l.project_id')
            ->selectRaw('l.project_id, count(r.id) as active')->get();
        $states = [];

        foreach ($rows as $row) {
            $active = (int) Row::text($row, 'active');
            $states[(int) Row::text($row, 'project_id')] = [Predicates::STOCK => new PredicateState(true,
                $active === 0 ? [] : [new JourneyItem('projects.item.reservations_active', ['jumlah' => (string) $active], 'warehouse', true)])];
        }

        return $states;
    }

    /** @return array<int, string> project line => the reserved quantity it may hold */
    private static function allowed(string $sql, int $projectId): array
    {
        $allowed = [];

        foreach (DB::select($sql, [$projectId]) as $row) {
            if (is_object($row)) {
                $allowed[(int) Row::text($row, 'id')] = Row::text($row, 'allowed');
            }
        }

        return $allowed;
    }
}
