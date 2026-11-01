<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Data\StockFigures;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Services\DemandPosting;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SF-RESTORE on a dispatch (AX-10; CM-05; CM-06; L-07; L-31; PX-08; DATABASE §6.2): a RESTORATION movement citing each consumption, entered in the dispatch line's unit at its snapshot ratio or in the base unit, at most the consumption's open quantity and net dispatched − net delivered, its serials matched, each unit back in its lot in the condition chosen at the contra of its HPP, the allocation reversed with it. The consumed reservation is never reactivated: a new one may be made for the restored units in the same command with `stock.reserve`. TASK-046 and TASK-048 reuse this service for their returns to the warehouse.
 */
final class DispatchRestoration
{
    public const KINDS = ['DISPATCH_REVERSAL', 'UNDELIVERED_RETURN'];

    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly DemandPosting $demand,
        private readonly Corrections $corrections,
        private readonly Revalidation $revalidation,
    ) {}

    /**
     * AX-10's set: the line's project, reservation and guard, the product, each rack's scope and lot place, each serial, each consumption with its cost guard and project.
     *
     * @param  list<array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int}>  $items
     */
    public function locks(int $dispatchLineId, array $items): LockSet
    {
        $line = self::dispatchLine($dispatchLineId);
        $product = (int) Row::text($line, 'product_id');
        $set = InventoryPosting::lineLocks((int) Row::text($line, 'project_line_id'))->add('stock_product_balances', ['product_id' => $product]);
        $serials = [];

        foreach ($items as $item) {
            $lot = DB::table('stock_consumptions')->where('id', $item['consumption'])->value('lot_id');
            $lot = is_numeric($lot) ? (int) $lot : 0;
            $set = $set->union(CostPosting::consumptionLocks($item['consumption']));
            $set->add('stock_consumption_balances', ['stock_consumption_id' => $item['consumption']]);
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $item['location'], 'condition' => $item['condition']]);
            $set->add('stock_lot_balances', ['lot_id' => $lot, 'location_id' => $item['location'], 'condition' => $item['condition']]);

            if ($item['serial'] !== null) {
                $serials[] = $item['serial'];
            }
        }

        foreach ($serials === [] ? [] : DB::table('serial_units')->whereIn('id', $serials)->orderBy('id')->pluck('serial_key') as $key) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => is_string($key) ? $key : '']);
        }

        return $set;
    }

    /**
     * ROUTE-188: the restoration committed; its movement.
     *
     * @param  list<array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int}>  $items
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     */
    public function restore(CommandContext $context, int $dispatchLineId, string $kind, array $items, ?string $reserve, string $reason, array $acknowledged, ?string $duplicateReason, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require($this->locks($dispatchLineId, $items));

        if (! in_array($kind, self::KINDS, true) || $items === []) {
            throw Refused::rejected('C-27', ['reason' => 'kind']);
        }

        $line = self::dispatchLine($dispatchLineId);
        $lineId = (int) Row::text($line, 'project_line_id');
        $product = (int) Row::text($line, 'product_id');
        $scale = max(0, (int) Row::text($line, 'quantity_scale'));
        $citedRatio = (int) Row::text($line, 'ratio_to_base');
        $policy = DB::table('products')->where('id', $product)->first(['base_unit_id', 'is_serialized', 'quantity_scale']);

        if ($policy === null || (bool) Row::text($policy, 'is_serialized') !== $serializedSeen || (int) Row::text($policy, 'quantity_scale') !== $scaleSeen) {
            throw Refused::conflict('ST-09', ['serialized' => $policy !== null && (bool) Row::text($policy, 'is_serialized'), 'scale' => $policy === null ? 0 : (int) Row::text($policy, 'quantity_scale')]);
        }

        $baseUnit = Row::text($policy, 'base_unit_id');
        $rows = [];
        $seen = [];
        $total = BigDecimal::zero();
        $usable = BigDecimal::zero();

        foreach ($items as $item) {
            $consumption = DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->where('c.id', $item['consumption'])->where('c.dispatch_line_id', $dispatchLineId)
                ->first(['c.lot_id', 'c.source_company_id', 'c.bearer_project_id', 'b.qty_base', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base']);

            if ($consumption === null) {
                throw Refused::rejected('C-14', ['reason' => 'consumption']);
            }

            $ratio = $item['base_unit'] ? 1 : $citedRatio;
            $qty = ReceivingRecords::base($item['qty'], $ratio, $scale);
            $open = BigDecimal::of(Row::text($consumption, 'qty_base'))->minus(Row::text($consumption, 'restored_qty_base'))->minus(Row::text($consumption, 'reclassified_qty_base'))->minus(Row::text($consumption, 'substituted_qty_base'));
            $already = $seen[$item['consumption']] ?? BigDecimal::zero();

            if (! $qty->isPositive() || $qty->plus($already)->isGreaterThan($open)) {
                throw Refused::rejected('C-27', ['reason' => 'above_open', 'open' => StockFigures::plain((string) $open->minus($already))]);
            }

            if ($item['serial'] !== null) {
                if (! self::stillOut($item['consumption'], $item['serial']) || ! $qty->isEqualTo(1)) {
                    throw Refused::rejected('C-02', ['reason' => 'serial_return']);
                }
            } elseif ((bool) DB::table('stock_movement_entries')->where('stock_consumption_id', $item['consumption'])->whereNotNull('serial_unit_id')->exists()) {
                throw Refused::rejected('C-02', ['reason' => 'serial_return']);
            }

            $rows[] = ['item' => $item, 'qty' => $qty, 'ratio' => $ratio, 'lot' => (int) Row::text($consumption, 'lot_id'), 'source' => (int) Row::text($consumption, 'source_company_id'), 'project' => Row::nullable($consumption, 'bearer_project_id')];
            $seen[$item['consumption']] = $qty->plus($already);
            $total = $total->plus($qty);
            $usable = $item['condition'] === 'USABLE' ? $usable->plus($qty) : $usable;
        }

        // PX-08: what has left and has not been delivered is all that can come back this way (CM-06); after delivery a return is TASK-048's.
        $outstanding = BigDecimal::of(Row::text($line, 'dispatched'))->minus(Row::text($line, 'delivered'));

        if ($total->isGreaterThan($outstanding)) {
            throw Refused::rejected('C-30', ['reason' => 'above_undelivered', 'remainder' => StockFigures::plain((string) $outstanding)]);
        }

        $matches = self::likelyDuplicates($lineId, (string) $total, $businessDate);
        DuplicateWarning::check('L-49', $matches, $acknowledged, $duplicateReason);
        $company = (int) Row::text($line, 'company_id');
        $project = (int) Row::text($line, 'project_id');

        $offered = $reserve === null ? BigDecimal::zero() : ReceivingRecords::base($reserve, 1, $scale);

        if ($offered->isPositive()) {
            $authority = $context->authority();

            if ($authority === null || ! $authority->allows('stock.reserve', $company)) {
                throw Refused::rejected('AZ-01', ['reason' => 'reserve']);
            }

            if ($offered->isGreaterThan($usable)) {
                throw Refused::rejected('BR-RSV-03', ['reason' => 'above_usable', 'usable' => StockFigures::plain((string) $usable)]);
            }
        }

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'RESTORATION', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => $company, 'project_id' => $project,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $entries = [];
        $projects = [];
        $number = 0;

        foreach ($rows as $row) {
            $item = $row['item'];
            $restoration = (int) DB::table('stock_restorations')->insertGetId([
                'stock_movement_id' => $movement, 'stock_consumption_id' => $item['consumption'], 'source_company_id' => $row['source'], 'product_id' => $product, 'quantity_scale' => $scale,
                'cited_dispatch_line_id' => $dispatchLineId, 'cited_unit_id' => (int) Row::text($line, 'entered_unit_id'), 'cited_ratio_to_base' => $citedRatio,
                'entered_qty' => $item['qty'], 'entered_unit_id' => $item['base_unit'] ? (int) $baseUnit : (int) Row::text($line, 'entered_unit_id'), 'ratio_to_base' => $row['ratio'], 'qty_base' => (string) $row['qty'],
                'kind' => $kind, 'after_delivery' => false, 'returned_condition' => $item['condition'],
                'command_id' => $commandId, 'command_line_no' => ++$number, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $moved = $this->cost->restore($context, $restoration, $businessDate);
            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $item['consumption']], 'deltas' => ['restored_qty_base' => (string) $row['qty']]]]);

            if ($moved !== null && $row['project'] !== null) {
                $projects[(int) $row['project']] = true;
            }

            $entries[] = ['lot' => $row['lot'], 'product' => $product, 'scale' => $scale, 'location' => $item['location'], 'condition' => $item['condition'], 'qty' => (string) $row['qty'], 'restoration' => $restoration, ...($item['serial'] === null ? [] : ['serial' => $item['serial']])];
        }

        $this->demand->dispatched($context, $lineId, (string) $total->negated());
        // The consumed reservation stays CONSUMED: the offer is a new reservation, bound with the restored units counted (L-07; CM-05).
        $reserved = $offered->isPositive() ? $this->inventory->reserveReceived($context, $lineId, (string) $offered, (string) $usable) : null;
        $ids = $this->inventory->ledger($context, $movement, 'RESTORATION', $entries, $reserved);

        foreach ($entries as $index => $entry) {
            if (isset($entry['serial']) && DB::table('serial_units')->where('id', $entry['serial'])->where('state', 'DISPATCHED')->update(['state' => 'IN_STOCK', 'current_lot_id' => $entry['lot'], 'current_location_id' => $entry['location'], 'current_condition' => $entry['condition'], 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial_return']);
            }
        }

        foreach (array_keys($projects) as $bearer) {
            $this->revalidation->revalidate($context, $bearer, $publicId);
        }

        $this->corrections->record($context, $matches === [] ? 'INV.DispatchReversed' : 'INV.DispatchReversalDuplicateConfirmed', AuthorityClass::AdmPlus, $company, 'stock_movement', $movement, $publicId, AuditRelation::Reverses, 'stock_movement', Row::text($line, 'movement_public_id'), $reason,
            AuditChange::updated(['quantity' => Row::text($line, 'qty_base')], ['restored' => (string) $total, 'kind' => $kind, ...(array_filter($rows, fn (array $row): bool => $row['source'] !== $company) === [] ? [] : ['allocation_reversed' => true]), ...($matches === [] ? [] : ['matches' => $matches])]), null, ['business_date' => $businessDate, 'reserved' => $reserved]);

        return $movement;
    }

    /**
     * BD-13: a restoration of the same project line, quantity and business date, the case being TASK-048's (L-49).
     *
     * @return list<string> the movements' public identifiers
     */
    public static function likelyDuplicates(int $lineId, string $base, string $businessDate): array
    {
        $found = [];

        foreach (DB::select(<<<'SQL'
            select m.public_id from stock_restorations r join stock_movements m on m.id = r.stock_movement_id join dispatch_lines d on d.id = r.cited_dispatch_line_id
            where d.project_line_id = ? and m.business_date = ?::date and r.kind in ('DISPATCH_REVERSAL', 'UNDELIVERED_RETURN')
            group by m.public_id having sum(r.qty_base) = ?::numeric
            order by m.public_id
            SQL, [$lineId, $businessDate, $base]) as $row) {
            if (is_object($row)) {
                $found[] = Row::text($row, 'public_id');
            }
        }

        return $found;
    }

    /** The serial's last entry is this consumption's: it left on it and has not come back since (AX-10; AX-35). */
    public static function stillOut(int $consumptionId, int $serialId): bool
    {
        return DB::table('serial_units as s')->join('stock_movement_entries as e', 'e.id', '=', 's.last_entry_id')->where('s.id', $serialId)->where('s.state', 'DISPATCHED')->where('e.stock_consumption_id', $consumptionId)->exists();
    }

    /**
     * A replayed refusal's current state (CI-05): the likely duplicates, what the consumption and the line still hold.
     *
     * @param  list<array{consumption: int, qty: string, base_unit: bool, condition: string, location: int, serial: ?int}>  $items
     * @return array<string, mixed>|null
     */
    public static function refusalState(?string $code, int $dispatchLineId, array $items, string $businessDate): ?array
    {
        $line = self::dispatchLine($dispatchLineId);
        $ratio = (int) Row::text($line, 'ratio_to_base');
        $total = BigDecimal::zero();

        foreach ($items as $item) {
            $total = $total->plus(BigDecimal::of($item['qty'])->multipliedBy($item['base_unit'] ? 1 : $ratio));
        }

        return match ($code) {
            'L-49' => ['matches' => self::likelyDuplicates((int) Row::text($line, 'project_line_id'), (string) $total, $businessDate)],
            'C-27' => ['open' => self::openOf($items[0]['consumption'] ?? 0)],
            'C-30' => ['remainder' => StockFigures::remainder(Row::nullable($line, 'dispatched') ?? '0', Row::nullable($line, 'delivered') ?? '0')],
            default => null,
        };
    }

    /** What of the consumption is still out, as words show it. */
    public static function openOf(int $consumptionId): string
    {
        $row = DB::table('stock_consumption_balances as b')->where('stock_consumption_id', $consumptionId)->first(['qty_base', 'restored_qty_base', 'reclassified_qty_base', 'substituted_qty_base']);

        return $row === null ? '0' : StockFigures::remainder(Row::text($row, 'qty_base'), Row::text($row, 'restored_qty_base'), Row::text($row, 'reclassified_qty_base'), Row::text($row, 'substituted_qty_base'));
    }

    private static function dispatchLine(int $dispatchLineId): object
    {
        return DB::table('dispatch_lines as d')->join('stock_movements as m', 'm.id', '=', 'd.stock_movement_id')->leftJoin('project_line_balances as b', 'b.project_line_id', '=', 'd.project_line_id')->where('d.id', $dispatchLineId)
            ->first(['d.id', 'd.project_line_id', 'd.project_id', 'd.company_id', 'd.product_id', 'd.quantity_scale', 'd.entered_unit_id', 'd.ratio_to_base', 'd.qty_base', 'm.public_id as movement_public_id', 'b.dispatched_net_qty_base as dispatched', 'b.delivered_net_qty_base as delivered'])
            ?? throw Refused::rejected('C-14', ['reason' => 'dispatch']);
    }
}
