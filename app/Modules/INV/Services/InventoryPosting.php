<?php

namespace App\Modules\INV\Services;

use App\Modules\PRJ\Services\DemandPosting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * INV's reservation service, the one path of every reservation fact (ARCHITECTURE §7; DATABASE §5.5; SF-RESERVE): a
 * reservation binds a product's usable quantity to a confirmed warehouse line — no lot, serial, movement or cost (BR-RSV-02;
 * L-03) —, its rows and events written here with the product guard's RESERVED, the line guard's reserved quantity
 * through PRJ's `DemandPosting`. Every check is re-read under the command's locks (ST-06): AVAILABLE = ON HAND − RESERVED −
 * UNUSABLE never below zero (C-04; BR-INV-01) and a line's reserved, dispatched and cancelled within its demand (C-05;
 * L-04). Nothing here runs by itself: no job, timer, completion or document releases a reservation (PX-02; FS-09).
 */
final class InventoryPosting
{
    public function __construct(
        private readonly DemandPosting $demand,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * AX-01's and AX-02's lock set (CONCURRENCY_IDEMPOTENCY §7.1): the line's project (LK-01), the product guard (LK-13), the
     * line's active reservation (LK-18) and its guard (LK-19).
     */
    public static function lineLocks(int $lineId): LockSet
    {
        $line = DB::table('project_lines')->where('id', $lineId)->first(['project_id', 'product_id'])
            ?? throw new InvalidArgumentException("No project line {$lineId}.");
        $set = (new LockSet)->add('projects', ['id' => (int) Row::text($line, 'project_id')]);

        if (DB::table('stock_product_balances as b')->where('b.product_id', (int) Row::text($line, 'product_id'))->exists()) {
            $set->add('stock_product_balances', ['product_id' => (int) Row::text($line, 'product_id')]);
        }

        foreach (DB::table('reservations')->where('project_line_id', $lineId)->where('state', 'ACTIVE')->pluck('id') as $reservation) {
            $set->add('reservations', ['id' => is_numeric($reservation) ? (int) $reservation : 0]);
        }

        if (DB::table('project_line_balances as b')->where('b.project_line_id', $lineId)->exists()) {
            $set->add('project_line_balances', ['project_line_id' => $lineId]);
        }

        return $set;
    }

    /** The line a reservation binds. */
    public static function lineOf(int $reservationId): int
    {
        $line = DB::table('reservations')->where('id', $reservationId)->value('project_line_id');

        return is_numeric($line) ? (int) $line : throw new InvalidArgumentException("No reservation {$reservationId}.");
    }

    /**
     * AX-01 (BR-RSV-01; L-02; L-04): `qty` of the product's usable supply bound to a confirmed warehouse line of an ACTIVE
     * project — the line's one ACTIVE reservation created, or increased (L-07: a consumed one is never reactivated) —,
     * refused with the current availability above AVAILABLE (C-04) and with the line's remainder above it (C-05). Returns the
     * reservation.
     */
    public function reserve(CommandContext $context, int $lineId, string $qty): int
    {
        $context->require(self::lineLocks($lineId));
        $line = self::line($lineId);

        if (Row::text($line, 'project_state') !== 'ACTIVE' || Row::nullable($line, 'pinned') === null || Row::nullable($line, 'demand') === null) {
            throw Refused::rejected('BR-RSV-01', ['reason' => 'unconfirmed']);
        }

        if (Row::text($line, 'fulfillment_mode') !== 'WAREHOUSE') {
            throw Refused::rejected('BR-RSV-01', ['reason' => 'mode']);
        }

        $available = self::available((int) Row::text($line, 'product_id'));

        if (BigDecimal::of($qty)->isGreaterThan($available)) {
            throw Refused::rejected('C-04', ['available' => (string) $available->toScale(max(0, (int) Row::text($line, 'quantity_scale')))]);
        }

        $remainder = self::remainder($line);

        if (BigDecimal::of($qty)->isGreaterThan($remainder)) {
            throw Refused::rejected('C-05', ['remainder' => (string) $remainder->toScale(max(0, (int) Row::text($line, 'quantity_scale'))), 'line' => Row::text($line, 'line_no')]);
        }

        $now = $context->now();
        $active = DB::table('reservations')->where('project_line_id', $lineId)->where('state', 'ACTIVE')->first(['id', 'remaining_qty_base']);
        $before = is_object($active) ? Row::text($active, 'remaining_qty_base') : '0';

        if (is_object($active)) {
            $id = (int) Row::text($active, 'id');
            $context->connection->update('update reservations set remaining_qty_base = remaining_qty_base + ?::numeric, lock_version = lock_version + 1, updated_at = ? where id = ?', [$qty, $now, $id]);
        } else {
            $id = (int) DB::table('reservations')->insertGetId([
                'project_line_id' => $lineId, 'company_id' => (int) Row::text($line, 'company_id'), 'product_id' => (int) Row::text($line, 'product_id'),
                'quantity_scale' => (int) Row::text($line, 'quantity_scale'), 'state' => 'ACTIVE', 'remaining_qty_base' => $qty,
                'command_id' => $context->request->commandId, 'command_line_no' => self::lineNo('reservations', $context), 'recorded_at' => $now,
                'recorded_by' => $context->request->actorUserId, 'updated_at' => $now,
            ]);
        }

        $this->event($context, $id, $line, is_object($active) ? 'INCREASE' : 'CREATE', $qty, null);
        GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => (int) Row::text($line, 'product_id')], 'deltas' => ['reserved_qty' => $qty]]]);
        $this->demand->reserved($context, [$lineId => $qty]);
        $this->audit->record(
            action: is_object($active) ? 'INV.ReservationIncreased' : 'INV.ReservationCreated',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: (int) Row::text($line, 'company_id'),
            entityType: 'reservation',
            entityId: $id,
            commandId: $context->request->commandId,
            change: is_object($active) ? AuditChange::updated(['remaining_qty_base' => $before], ['remaining_qty_base' => (string) BigDecimal::of($before)->plus($qty)])
                : AuditChange::created(['remaining_qty_base' => $qty, 'state' => 'ACTIVE']),
            metadata: ['project' => Row::text($line, 'project_public_id'), 'line' => Row::text($line, 'line_no'), 'quantity' => $qty],
        );

        return $id;
    }

    /**
     * AX-02 (BR-RSV-02; J-INV-02): "Kurangi Reservasi" by `qty`, or "Lepas" of the whole remaining quantity (`qty` null), with
     * the actor's reason — never above what remains at commit, so a release racing a dispatch of the same reservation leaves
     * the units consumed or released, never both (C-05). A reservation of a completed or cancelled project is released this
     * way as a residual command (QS-16; BR-PRJ-04).
     */
    public function reduce(CommandContext $context, int $reservationId, ?string $qty, string $reason): void
    {
        $context->require(self::lineLocks(self::lineOf($reservationId)));
        $reservation = self::reservation($reservationId);
        $remaining = Row::text($reservation, 'state') === 'ACTIVE' ? BigDecimal::of(Row::text($reservation, 'remaining_qty_base')) : BigDecimal::zero();
        $take = $qty === null ? $remaining : BigDecimal::of($qty);

        if ($remaining->isZero() || $take->isGreaterThan($remaining)) {
            throw Refused::rejected('C-05', ['remaining' => (string) $remaining->toScale(max(0, (int) Row::text($reservation, 'quantity_scale'))), 'state' => Row::text($reservation, 'state')]);
        }

        $ends = $take->isEqualTo($remaining);
        $this->lower($context, $reservation, (string) $take, $ends ? 'RELEASE' : 'DECREASE', $reason, null, null, true);
        $this->audit->record(
            action: $ends ? 'INV.ReservationReleased' : 'INV.ReservationReduced',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: (int) Row::text($reservation, 'company_id'),
            entityType: 'reservation',
            entityId: $reservationId,
            reason: $reason,
            commandId: $context->request->commandId,
            change: AuditChange::updated(['remaining_qty_base' => (string) $remaining, 'state' => 'ACTIVE'],
                ['remaining_qty_base' => (string) $remaining->minus($take), 'state' => $ends ? 'RELEASED' : 'ACTIVE']),
            metadata: ['project' => Row::text($reservation, 'project_public_id'), 'line' => Row::text($reservation, 'line_no'), 'quantity' => (string) $take],
        );
    }

    /**
     * A dispatch's CONSUME of its line's own reservation (WF-INV-03; TASK-041): up to `qty` of it consumed, CONSUMED at zero
     * and never reactivated (L-07), the line guard lowered; the product's reserved quantity consumed is returned for the
     * dispatch's one statement on the product guard with its on-hand move (GU-01).
     */
    public function consume(CommandContext $context, int $lineId, string $qty, int $dispatchLineId): string
    {
        $context->require(self::lineLocks($lineId));
        $active = DB::table('reservations')->where('project_line_id', $lineId)->where('state', 'ACTIVE')->value('id');

        if (! is_numeric($active)) {
            return '0';
        }

        $reservation = self::reservation((int) $active);
        $take = BigDecimal::min(BigDecimal::of($qty), BigDecimal::of(Row::text($reservation, 'remaining_qty_base')));

        if (! $take->isPositive()) {
            return '0';
        }

        $this->lower($context, $reservation, (string) $take, 'CONSUME', null, $dispatchLineId, null, false);

        return (string) $take;
    }

    /**
     * SF-RSV-CUT (BR-RSV-04; C-06; IP-20; UXI-35): a supply-reducing command's explicit cuts — the actor's, never the
     * system's — on the product whose usable supply its movement lowers by `usableReduction`: they must cover the shortfall,
     * RESERVED above the usable supply left, else REJECTED with it (C-06); each is within its reservation's remaining
     * quantity (C-05); a cut of a reservation whose company is outside the actor's grants is REJECTED and routed, one audit
     * event per such company committed with the refusal (AZ-08; SQ-23). Each cut is a CUT event naming the movement; the
     * line guards are lowered here and the product's reserved delta is returned for the caller's one statement on the product
     * guard with its own on-hand and unusable moves, whose CHECK refuses a reduction the cuts do not cover. The caller holds
     * the lock set of `cutLocks()` and audits the cuts with its own command.
     *
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function cut(CommandContext $context, int $productId, array $cuts, string $usableReduction, int $movementId, string $routedAction): string
    {
        $context->require(self::cutLocks($productId, array_keys($cuts)));
        $shortfall = self::shortfall($productId, $usableReduction);
        $covered = array_reduce($cuts, fn (BigDecimal $sum, string $qty): BigDecimal => $sum->plus($qty), BigDecimal::zero());

        if ($covered->isLessThan($shortfall)) {
            throw Refused::rejected('C-06', ['shortfall' => (string) $shortfall, 'covered' => (string) $covered]);
        }

        $reservations = [];
        $outside = [];

        foreach ($cuts as $id => $qty) {
            $reservation = self::reservation($id);

            if ((int) Row::text($reservation, 'product_id') !== $productId || Row::text($reservation, 'state') !== 'ACTIVE'
                || BigDecimal::of($qty)->isGreaterThan(Row::text($reservation, 'remaining_qty_base')) || ! BigDecimal::of($qty)->isPositive()) {
                throw Refused::rejected('C-05', ['reservation' => $id, 'remaining' => Row::text($reservation, 'state') === 'ACTIVE' ? Row::text($reservation, 'remaining_qty_base') : '0']);
            }

            if ($context->authority()?->scope->includes((int) Row::text($reservation, 'company_id')) !== true) {
                $outside[(int) Row::text($reservation, 'company_id')] = true;
            }

            $reservations[$id] = $reservation;
        }

        if ($outside !== []) {
            $actor = $context->request->actorUserId;
            $command = $context->request->commandId;
            throw Refused::rejected('AZ-08', ['routed' => true], function (ConnectionInterface $connection) use ($outside, $actor, $command, $productId, $routedAction): void {
                foreach (array_keys($outside) as $company) {
                    $this->audit->record(action: 'INV.ReservationCutRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $company,
                        entityType: 'product', entityId: $productId, commandId: $command, metadata: ['routed_action' => $routedAction], connection: $connection);
                }
            });
        }

        $reserved = BigDecimal::zero();

        foreach ($reservations as $id => $reservation) {
            $this->lower($context, $reservation, $cuts[$id], 'CUT', null, null, $movementId, false);
            $reserved = $reserved->plus($cuts[$id]);
        }

        return (string) $reserved->negated();
    }

    /**
     * The cut's lock set (AX-02 note): the product guard, the reservations named, their lines' guards and the projects of
     * every reservation it names (LK-01).
     *
     * @param  list<int>  $reservationIds
     */
    public static function cutLocks(int $productId, array $reservationIds): LockSet
    {
        $set = (new LockSet)->add('stock_product_balances', ['product_id' => $productId]);

        foreach (DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->whereIn('r.id', $reservationIds)
            ->get(['r.id', 'r.project_line_id', 'l.project_id']) as $row) {
            $set->add('reservations', ['id' => (int) Row::text($row, 'id')])->add('projects', ['id' => (int) Row::text($row, 'project_id')])
                ->add('project_line_balances', ['project_line_id' => (int) Row::text($row, 'project_line_id')]);
        }

        return $set;
    }

    /**
     * The reservations above a line's demand once PRJ moves it (AX-13; AX-14; AX-27): each line's active reservation lowered
     * to what its remainder leaves — `allowed`, the line's demand less dispatched and cancelled as PRJ is setting them —, a
     * release at zero, with the system reason; inside PRJ's transaction, its failure rolling the whole command back.
     *
     * @param  array<int, string>  $allowed  project line => the reserved quantity the line may still hold
     */
    public function fit(CommandContext $context, array $allowed, string $reason): void
    {
        $lowered = [];

        foreach ($allowed as $line => $max) {
            $active = DB::table('reservations')->where('project_line_id', $line)->where('state', 'ACTIVE')->value('id');

            if (! is_numeric($active)) {
                continue;
            }

            $context->require(self::lineLocks($line));
            $reservation = self::reservation((int) $active);
            $remaining = BigDecimal::of(Row::text($reservation, 'remaining_qty_base'));
            $excess = $remaining->minus(BigDecimal::max(BigDecimal::zero(), BigDecimal::of($max)));

            if ($excess->isPositive()) {
                $lowered[] = [$reservation, (string) $excess, $excess->isEqualTo($remaining) ? 'RELEASE' : 'DECREASE'];
            }
        }

        foreach ($lowered as [$reservation, $excess, $kind]) {
            $this->lower($context, $reservation, $excess, $kind, $reason, null, null, true);
        }
    }

    /** AVAILABLE for a product (CALC-01): ON HAND − RESERVED − UNUSABLE, zero without its guard. */
    public static function available(int $productId): BigDecimal
    {
        $row = DB::table('stock_product_balances as b')->where('b.product_id', $productId)->first(['on_hand_qty', 'reserved_qty', 'unusable_qty']);

        return is_object($row) ? BigDecimal::of(Row::text($row, 'on_hand_qty'))->minus(Row::text($row, 'reserved_qty'))->minus(Row::text($row, 'unusable_qty')) : BigDecimal::zero();
    }

    /** What a supply reduction leaves uncovered (BR-RSV-04): RESERVED above the usable supply once `usableReduction` leaves it. */
    public static function shortfall(int $productId, string $usableReduction): BigDecimal
    {
        $after = self::available($productId)->minus($usableReduction);

        return $after->isNegative() ? $after->negated() : BigDecimal::zero();
    }

    /**
     * One reservation lowered by `qty` with its event — RELEASED or CONSUMED at zero, never ACTIVE at zero (C-05) —, its line
     * guard lowered and, when `productGuard`, the product guard's RESERVED with it.
     */
    private function lower(CommandContext $context, object $reservation, string $qty, string $kind, ?string $reason, ?int $dispatchLineId, ?int $movementId, bool $productGuard): void
    {
        $id = (int) Row::text($reservation, 'id');
        $ends = BigDecimal::of(Row::text($reservation, 'remaining_qty_base'))->isEqualTo($qty);
        $state = $ends ? ($kind === 'CONSUME' ? 'CONSUMED' : 'RELEASED') : 'ACTIVE';
        $context->connection->update('update reservations set remaining_qty_base = remaining_qty_base - ?::numeric, state = ?, lock_version = lock_version + 1, updated_at = ? where id = ?',
            [$qty, $state, $context->now(), $id]);
        $this->event($context, $id, $reservation, $kind, '-'.$qty, $reason, $dispatchLineId, $movementId);

        if ($productGuard) {
            GuardUpdate::apply($context->connection, 'stock_product_balances', [['key' => ['product_id' => (int) Row::text($reservation, 'product_id')], 'deltas' => ['reserved_qty' => '-'.$qty]]]);
        }

        $this->demand->reserved($context, [(int) Row::text($reservation, 'project_line_id') => '-'.$qty]);
    }

    private function event(CommandContext $context, int $reservationId, object $source, string $kind, string $delta, ?string $reason, ?int $dispatchLineId = null, ?int $movementId = null): void
    {
        DB::table('reservation_events')->insert([
            'reservation_id' => $reservationId, 'product_id' => (int) Row::text($source, 'product_id'), 'quantity_scale' => (int) Row::text($source, 'quantity_scale'),
            'kind' => $kind, 'qty_delta_base' => $delta, 'reason' => $reason, 'dispatch_line_id' => $dispatchLineId, 'cut_by_movement_id' => $movementId,
            'command_id' => $context->request->commandId, 'command_line_no' => self::lineNo('reservation_events', $context),
            'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
    }

    private static function lineNo(string $table, CommandContext $context): int
    {
        return DB::table($table)->where('command_id', $context->request->commandId)->count() + 1;
    }

    /** The line with its project's state and the guard's quantities, read under the locks. */
    private static function line(int $lineId): object
    {
        return DB::table('project_lines as l')->join('projects as p', 'p.id', '=', 'l.project_id')->leftJoin('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->where('l.id', $lineId)
            ->first(['l.id', 'l.project_id', 'l.company_id', 'l.product_id', 'l.quantity_scale', 'l.fulfillment_mode', 'l.line_no', 'l.current_pin_id as pinned', 'p.state as project_state',
                'p.public_id as project_public_id', 'b.demand_qty_base as demand', 'b.reserved_qty_base as reserved', 'b.dispatched_net_qty_base as dispatched', 'b.cancelled_qty_base as cancelled'])
            ?? throw new InvalidArgumentException("No project line {$lineId}.");
    }

    /** What a warehouse line may still reserve (L-04): its demand less reserved, dispatched and cancelled. */
    private static function remainder(object $line): BigDecimal
    {
        return BigDecimal::of(Row::text($line, 'demand'))->minus(Row::text($line, 'reserved'))->minus(Row::text($line, 'dispatched'))->minus(Row::text($line, 'cancelled'));
    }

    private static function reservation(int $reservationId): object
    {
        return DB::table('reservations as r')->join('project_lines as l', 'l.id', '=', 'r.project_line_id')->join('projects as p', 'p.id', '=', 'l.project_id')
            ->where('r.id', $reservationId)
            ->first(['r.id', 'r.project_line_id', 'r.company_id', 'r.product_id', 'r.quantity_scale', 'r.state', 'r.remaining_qty_base', 'l.line_no', 'l.project_id',
                'p.public_id as project_public_id'])
            ?? throw new InvalidArgumentException("No reservation {$reservationId}.");
    }
}
