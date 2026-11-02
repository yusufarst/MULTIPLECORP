<?php

namespace App\Modules\FUL\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\FUL\Data\ShipmentFigures;
use App\Modules\INV\Services\LossPosting;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\DemandPosting;
use App\Modules\PRJ\Services\LineScope;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * NX-15 and AX-30 for a shipment line (WF-FUL-02; CM-06; CM-07; CM-27; PX-04; PX-08): "Tutup Pengiriman" closes the
 * shipped quantity that will not be delivered — refused, or returned undelivered — with its reason, the goods coming back
 * only through the dispatch's restoration (AX-10), never through the closure; a closure recorded in error superseded by
 * its correct one. "Catat Hilang di Perjalanan" closes the lost quantity with SF-LOSS's reclassification on the shipment's
 * project in the same action: the dispatch's HPP contra'd and a loss of the same amount, its allocation kept, net
 * dispatched lowered by the lost quantity, a lost serial LOST (annex 15; C-27). Never above net shipped − net delivered −
 * what other closures hold back (C-30); a likely duplicate of the same line, kind, quantity and date REJECTED with its
 * matches until the actor confirms them with a reason (L-49; BD-13). No stock moves (BR-INV-02).
 */
final class DeliveryClosures
{
    public const KINDS = ['REFUSED', 'RETURNED_UNDELIVERED', 'LOST_IN_TRANSIT'];

    public const LOST = 'LOST_IN_TRANSIT';

    /** A consumption's quantity still out on its shipment. */
    private const OPEN = 'b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base';

    public function __construct(
        private readonly DemandPosting $demand,
        private readonly LossPosting $losses,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * NX-15's set — the project and its line's guard (LK-19); a loss in transit also AX-30's: each open consumption's lot
     * cost, bearer and guard, and the lost serials (LK-23; LK-24).
     *
     * @param  list<int>  $serials
     */
    public static function locks(int $dispatchLineId, bool $lost, array $serials = []): LockSet
    {
        $line = DB::table('dispatch_lines')->where('id', $dispatchLineId)->first(['project_id', 'project_line_id']);

        if ($line === null) {
            return new LockSet;
        }

        $set = LineScope::locks((int) Row::text($line, 'project_id'), [(int) Row::text($line, 'project_line_id')]);

        foreach ($lost ? self::consumptions($dispatchLineId) : [] as $consumption) {
            $set = $set->union(CostPosting::consumptionLocks($consumption['id']))->add('stock_consumption_balances', ['stock_consumption_id' => $consumption['id']]);
        }

        // A serial is locked by its natural key, as the substitution and the count lock it (LK-24).
        foreach ($lost && $serials !== [] ? DB::table('serial_units')->whereIn('id', $serials)->orderBy('id')->get(['product_id', 'serial_key'])->all() : [] as $serial) {
            $set = $set->add('serial_units', ['product_id' => (int) Row::text($serial, 'product_id'), 'serial_key' => Row::text($serial, 'serial_key')]);
        }

        return $set;
    }

    /**
     * The closure, a loss in transit's reclassification with it.
     *
     * @param  list<int>  $serials  a serialized shipment's lost serials
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms
     * @return int the closure
     */
    public function close(CommandContext $context, ProjectRef $project, int $dispatchLineId, string $kind, string $quantity, array $serials, ?int $supersedes, string $reason, string $businessDate, bool $prefilled, array $acknowledged = [], ?string $duplicateReason = null): int
    {
        // The consumptions and serials re-read under the line's lock: one a substitution added since is locked before it is written (§5).
        $context->require(self::locks($dispatchLineId, $kind === self::LOST, $serials));
        DeliveryPosting::open($project->id);
        BusinessDateRule::check($businessDate, $prefilled);

        if (! in_array($kind, self::KINDS, true)) {
            throw Refused::rejected('C-07', ['reason' => 'kind']);
        }

        if (trim($reason) === '') {
            throw Refused::rejected('C-14', ['reason' => 'reason']);
        }

        $shipment = DeliveryPosting::shipments([$dispatchLineId])[$dispatchLineId] ?? null;

        if ($shipment === null || (int) Row::text($shipment, 'project_id') !== $project->id) {
            throw Refused::rejected('C-14', ['reason' => 'shipment']);
        }

        $no = Row::text($shipment, 'line_no');
        $base = ShipmentFigures::base($quantity, (int) Row::text($shipment, 'ratio_to_base'), (int) Row::text($shipment, 'quantity_scale'))
            ?? throw Refused::rejected('C-09', ['reason' => 'scale', 'line' => $no]);
        $room = DeliveryPosting::remainder($shipment);

        // A closure recorded in error gives way to its correct one (CM-27); a loss in transit is reversed only by the goods found (ROUTE-196).
        if ($supersedes !== null) {
            $previous = DB::table('delivery_closures')->where('id', $supersedes)->where('dispatch_line_id', $dispatchLineId)->first(['closure_kind', 'closed_qty_base']);

            if ($previous === null || Row::text($previous, 'closure_kind') === self::LOST || $kind === self::LOST || DB::table('delivery_closures')->where('supersedes_id', $supersedes)->exists()) {
                throw Refused::rejected('C-07', ['reason' => 'supersedes']);
            }

            $room = $room->plus(BigDecimal::min(BigDecimal::of(Row::text($previous, 'closed_qty_base')), DeliveryPosting::held($shipment)));
        }

        if ($base->isGreaterThan($room)) {
            throw Refused::rejected('C-30', ['reason' => 'remainder', 'line' => $no, 'remainder' => (string) $room]);
        }

        $matches = $supersedes === null ? self::likelyDuplicates($dispatchLineId, $kind, (string) $base, $businessDate) : [];
        DuplicateWarning::check('L-49', $matches, $acknowledged, $duplicateReason);
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $closure = DB::table('delivery_closures')->insertGetId([
            'project_line_id' => (int) Row::text($shipment, 'project_line_id'), 'company_id' => $project->companyId,
            'product_id' => (int) Row::text($shipment, 'product_id'), 'quantity_scale' => (int) Row::text($shipment, 'quantity_scale'),
            'dispatch_line_id' => $dispatchLineId, 'dropship_confirmation_line_id' => null, 'closed_qty_base' => (string) $base, 'closure_kind' => $kind,
            'reason' => trim($reason), 'correction_case_id' => null, 'decided_by' => $actor, 'business_date' => $businessDate, 'supersedes_id' => $supersedes,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);

        if ($kind === self::LOST) {
            $this->lose($context, $dispatchLineId, (int) Row::text($shipment, 'project_line_id'), $base, $serials, $no, $businessDate);
        }

        $movement = DB::table('dispatch_lines as d')->join('stock_movements as m', 'm.id', '=', 'd.stock_movement_id')->where('d.id', $dispatchLineId)->value('m.public_id');
        $action = match (true) {
            $kind === self::LOST => 'FUL.LossInTransitRecorded',
            $supersedes !== null => 'FUL.DeliveryClosureSuperseded',
            default => 'FUL.DeliveryClosed',
        };
        // A confirmed likely duplicate commits under the acknowledged variant, listed in QS-21 and the Owner's review (QS-14).
        $this->audit->record(
            action: $matches === [] ? $action : ($kind === self::LOST ? 'FUL.LossInTransitDuplicateConfirmed' : 'FUL.DeliveryClosedDuplicateConfirmed'),
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $actor,
            companyId: $project->companyId,
            entityType: 'project',
            entityId: $project->id,
            entityPublicId: $project->publicId,
            reason: trim($reason),
            commandId: $commandId,
            change: AuditChange::created(['kind' => $kind, 'line_'.$no => (string) $base, ...($supersedes === null ? [] : ['supersedes' => $supersedes]), ...($matches === [] ? [] : ['matches' => $matches, 'duplicate_reason' => $duplicateReason])]),
            linkage: is_string($movement) ? AuditLinkage::make()->link(AuditRelation::Affects, 'stock_movements', $movement) : null,
            businessDate: $businessDate,
        );

        return $closure;
    }

    /**
     * The closures already standing for the same shipment line, kind, quantity and date (BD-13).
     *
     * @return list<string> their handles
     */
    public static function likelyDuplicates(int $dispatchLineId, string $kind, string $base, string $businessDate): array
    {
        return array_values(array_map(fn (mixed $id): string => RowHandle::of('delivery_closures', is_numeric($id) ? (int) $id : 0),
            DB::table('delivery_closures as k')->where('k.dispatch_line_id', $dispatchLineId)->where('k.closure_kind', $kind)->where('k.closed_qty_base', $base)->where('k.business_date', $businessDate)
                ->whereRaw('not exists (select 1 from delivery_closures s where s.supersedes_id = k.id)')->orderBy('k.id')->pluck('k.id')->all()));
    }

    /**
     * The lost quantity reclassified on its consumptions — for a serialized shipment each named serial's own consumption,
     * a consumption carrying several of them reclassified by their count; otherwise in their order — and net dispatched
     * lowered by it (PX-08; DIR-024 D-3).
     *
     * @param  list<int>  $serials
     */
    private function lose(CommandContext $context, int $dispatchLineId, int $projectLineId, BigDecimal $base, array $serials, string $no, string $businessDate): void
    {
        $consumptions = self::consumptions($dispatchLineId);
        $serialized = array_filter($consumptions, fn (array $consumption): bool => $consumption['serials'] !== []) !== [];
        $named = array_values(array_unique($serials));
        $parts = [];

        if ($serialized) {
            $found = 0;

            foreach ($consumptions as $consumption) {
                $count = count(array_intersect($consumption['serials'], $named));

                if ($count > 0) {
                    $parts[] = ['id' => $consumption['id'], 'qty' => BigDecimal::of($count)];
                    $found += $count;
                }
            }

            if ($found !== count($named) || ! BigDecimal::of($found)->isEqualTo($base)) {
                throw Refused::rejected('C-02', ['reason' => 'serial', 'line' => $no]);
            }
        } else {
            $left = $base;

            foreach ($consumptions as $consumption) {
                $part = BigDecimal::min($left, $consumption['open']);

                if ($part->isPositive()) {
                    $parts[] = ['id' => $consumption['id'], 'qty' => $part];
                    $left = $left->minus($part);
                }
            }

            if ($left->isPositive()) {
                throw Refused::rejected('C-27', ['reason' => 'above_open', 'line' => $no]);
            }
        }

        foreach ($parts as $part) {
            $this->losses->reclassify($context, $part['id'], (string) $part['qty'], $businessDate);
        }

        $this->demand->dispatched($context, $projectLineId, (string) $base->negated());

        if ($serialized && DB::table('serial_units')->whereIn('id', $named)->where('state', 'DISPATCHED')->update(['state' => 'LOST']) !== count($named)) {
            throw Refused::rejected('C-02', ['reason' => 'serial', 'line' => $no]);
        }
    }

    /** @return list<array{id: int, open: BigDecimal, serials: list<int>}> the shipment line's consumptions still out, in their order, each with the serials it carries */
    private static function consumptions(int $dispatchLineId): array
    {
        $rows = DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')
            ->where('c.dispatch_line_id', $dispatchLineId)->whereRaw(self::OPEN.' > 0')->orderBy('c.id')->get(['c.id', DB::raw(self::OPEN.' as open')])->all();
        $carried = [];

        // The serials whose last movement left on the consumption: one restored or substituted away is no longer on the shipment.
        foreach (DB::table('stock_movement_entries as e')->join('serial_units as s', 's.last_entry_id', '=', 'e.id')->where('s.state', 'DISPATCHED')
            ->whereIn('e.stock_consumption_id', array_map(fn (object $row): int => (int) Row::text($row, 'id'), $rows))->orderBy('s.id')->get(['e.stock_consumption_id', 's.id'])->all() as $serial) {
            $carried[(int) Row::text($serial, 'stock_consumption_id')][] = (int) Row::text($serial, 'id');
        }

        return array_values(array_map(fn (object $row): array => ['id' => (int) Row::text($row, 'id'), 'open' => BigDecimal::of(Row::text($row, 'open')), 'serials' => $carried[(int) Row::text($row, 'id')] ?? []], $rows));
    }
}
