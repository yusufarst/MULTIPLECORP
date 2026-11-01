<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Data\DispatchEntry;
use App\Modules\PRJ\Services\DemandPosting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What a dispatch records (WF-INV-03; WF-INV-07; AX-03; BR-INV-02; BR-INV-05; BR-FIN-12; DATABASE §4.7; §5.3; §6.5): the single physical subtraction of warehouse fulfilment — the DISPATCH movement with its usable entries per lot, rack and serial, the dispatch line keyed to its project line, a consumption per lot with its bearer keyed to that line, HPP drawn once per unit at each lot's current net cost, the line's own reservation consumed and the rest taken from AVAILABLE, the line guard's net dispatched. Another company's lot is the same consumption with its allocation overlay: reason and authorizing actor, ADM+ authority with a grant on the consuming company only (XL-1; L-44), never a second consumption, an invoice or a journal.
 */
final class DispatchPosting
{
    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly DemandPosting $demand,
        private readonly AuditRecorder $audit,
    ) {}

    /** AX-03's set (CONCURRENCY_IDEMPOTENCY §7.1): the line's project, reservation and guard, the product, each rack's scope and lot place, each serial, each lot's cost guard. */
    public function locks(DispatchEntry $entry): LockSet
    {
        $set = InventoryPosting::lineLocks($entry->lineId);
        $product = (int) DB::table('project_lines')->where('id', $entry->lineId)->value('product_id');
        $set->add('stock_product_balances', ['product_id' => $product]);
        $serials = [];

        foreach ($entry->picks as $pick) {
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $pick['location'], 'condition' => 'USABLE']);
            $set->add('stock_lot_balances', ['lot_id' => $pick['lot'], 'location_id' => $pick['location'], 'condition' => 'USABLE']);
            $set->add('lot_cost_balances', ['lot_id' => $pick['lot']]);

            if ($pick['serial'] !== null) {
                $serials[] = $pick['serial'];
            }
        }

        foreach ($serials === [] ? [] : DB::table('serial_units')->whereIn('id', $serials)->orderBy('id')->pluck('serial_key') as $key) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => is_string($key) ? $key : '']);
        }

        return $set;
    }

    /** The dispatch committed; its movement. */
    public function record(CommandContext $context, DispatchEntry $entry): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $context->require($this->locks($entry));
        $line = self::line($entry->lineId);
        $product = (int) Row::text($line, 'product_id');
        $scale = max(0, (int) Row::text($line, 'quantity_scale'));
        $serialized = (bool) $line->is_serialized;

        if ($serialized !== $entry->serializedSeen || (int) Row::text($line, 'product_scale') !== $entry->scaleSeen) {
            throw Refused::conflict('ST-09', ['serialized' => $serialized, 'scale' => (int) Row::text($line, 'product_scale')]);
        }

        // A dispatch is no residual command (L-11; WF-PRJ-03); a drop-ship or service line has no warehouse event (BR-INV-11).
        if (Row::text($line, 'project_state') !== 'ACTIVE') {
            throw Refused::rejected('L-11', ['reason' => 'project_state']);
        }

        if (Row::text($line, 'fulfillment_mode') !== 'WAREHOUSE') {
            throw Refused::rejected('BR-INV-11', ['reason' => 'not_a_warehouse_line']);
        }

        if (Row::nullable($line, 'pinned') === null || Row::nullable($line, 'demand') === null) {
            throw Refused::rejected('PX-03', ['reason' => 'unconfirmed']);
        }

        $ratio = DB::table('product_units')->where('product_id', $product)->where('unit_id', $entry->unitId)->where('is_active', true)->value('ratio_to_base');

        if (! is_numeric($ratio)) {
            throw Refused::rejected('C-09', ['reason' => 'unit']);
        }

        $base = ReceivingRecords::base($entry->quantity, (int) $ratio, $scale);

        if (! $base->isPositive()) {
            throw Refused::rejected('C-09', ['reason' => 'quantity']);
        }

        $remainder = BigDecimal::of(Row::text($line, 'demand'))->minus(Row::text($line, 'dispatched'))->minus(Row::text($line, 'cancelled'));

        if ($base->isGreaterThan($remainder)) {
            throw Refused::rejected('PX-03', ['reason' => 'above_remainder', 'remainder' => (string) $remainder]);
        }

        // The line's own reservation first, the rest from AVAILABLE: another project's reserved supply is never taken (M-03; BR-RSV-02).
        $reservation = DB::table('reservations')->where('project_line_id', $entry->lineId)->where('state', 'ACTIVE')->first(['id', 'remaining_qty_base']);
        $own = $reservation === null ? BigDecimal::zero() : BigDecimal::of(Row::text($reservation, 'remaining_qty_base'));
        $reservedPart = BigDecimal::min($base, $own);
        $unreservedPart = $base->minus($reservedPart);
        $available = InventoryPosting::available($product);

        if ($unreservedPart->isGreaterThan($available)) {
            throw Refused::rejected('C-04', ['available' => (string) $available->plus($own)]);
        }

        $company = (int) Row::text($line, 'company_id');
        $project = (int) Row::text($line, 'project_id');
        $lots = $this->ensurePicks($entry, $product, $serialized, $base);
        $crossing = array_filter($lots, fn (array $lot): bool => $lot['company'] !== $company);
        $this->ensureAllocation($context, $entry, $company, $project, $crossing);
        $matches = self::likelyDuplicates($entry->lineId, (string) $base, $entry->businessDate);
        DuplicateWarning::check('L-49', $matches, $entry->acknowledged, $entry->duplicateReason);

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'DISPATCH', 'business_date' => $entry->businessDate, 'company_id' => $company, 'project_id' => $project,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $dispatch = (int) DB::table('dispatch_lines')->insertGetId([
            'stock_movement_id' => $movement, 'project_line_id' => $entry->lineId, 'project_id' => $project, 'company_id' => $company, 'product_id' => $product, 'quantity_scale' => $scale,
            'entered_qty' => $entry->quantity, 'entered_unit_id' => $entry->unitId, 'ratio_to_base' => (int) $ratio, 'qty_base' => (string) $base,
            'reserved_qty_base' => (string) $reservedPart, 'unreserved_qty_base' => (string) $unreservedPart, 'reservation_id' => $reservedPart->isPositive() && $reservation !== null ? (int) Row::text($reservation, 'id') : null,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $reserved = $reservedPart->isPositive() ? $this->inventory->consumeHeld($context, $entry->lineId, (string) $reservedPart, $dispatch) : '0';
        $consumptions = [];
        $number = 0;

        foreach ($lots as $lot => $held) {
            $crossed = $held['company'] !== $company;
            $consumption = (int) DB::table('stock_consumptions')->insertGetId([
                'stock_movement_id' => $movement, 'lot_id' => $lot, 'source_company_id' => $held['company'], 'product_id' => $product, 'quantity_scale' => $scale, 'qty_base' => (string) $held['qty'], 'kind' => 'DISPATCH', 'dispatch_line_id' => $dispatch,
                'bearer_company_id' => $company, 'bearer_project_id' => $project, 'allocation_reason' => $crossed ? $entry->allocationReason : null, 'allocation_authorized_by' => $crossed ? $actor : null,
                'command_id' => $commandId, 'command_line_no' => ++$number, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $context->connection->table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption, 'qty_base' => (string) $held['qty']]);
            $consumptions[$lot] = $consumption;
        }

        $rows = array_map(fn (array $pick): array => ['lot' => $pick['lot'], 'product' => $product, 'scale' => $scale, 'location' => $pick['location'], 'condition' => 'USABLE', 'qty' => (string) BigDecimal::of($pick['qty'])->negated(), 'consumption' => $consumptions[$pick['lot']], ...($pick['serial'] === null ? [] : ['serial' => $pick['serial']])], $entry->picks);
        $ids = $this->inventory->ledger($context, $movement, 'DISPATCH', $rows, BigDecimal::of($reserved)->isPositive() ? '-'.$reserved : null);

        foreach ($rows as $index => $row) {
            // Conditional on what it was just read to be: one serial dispatched by two commands leaves one success (C-02; M-05).
            if (isset($row['serial']) && DB::table('serial_units')->where('id', $row['serial'])->where('state', 'IN_STOCK')->where('current_lot_id', $row['lot'])->update(['state' => 'DISPATCHED', 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null, 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial']);
            }
        }

        foreach ($consumptions as $consumption) {
            $this->cost->draw($context, $consumption, $entry->businessDate);
        }

        $this->demand->dispatched($context, $entry->lineId, (string) $base, '-'.$reserved);
        // A confirmed likely duplicate commits under the acknowledged variant, listed in QS-21 and the Owner's review (QS-14); an allocation is ADM+'s (AZ-10).
        $this->audit->record(
            action: $matches === [] ? 'INV.GoodsDispatched' : 'INV.GoodsDispatchDuplicateConfirmed',
            authorityClass: $crossing === [] ? AuthorityClass::Adm : AuthorityClass::AdmPlus,
            actorUserId: $actor,
            companyId: $company,
            entityType: 'stock_movement',
            entityId: $movement,
            entityPublicId: $publicId,
            reason: $matches === [] ? null : $entry->duplicateReason,
            commandId: $commandId,
            change: AuditChange::created(['quantity' => (string) $base, 'reserved' => (string) $reservedPart, 'unreserved' => (string) $unreservedPart, ...($crossing === [] ? [] : ['allocated' => true, 'allocation_reason' => $entry->allocationReason]), ...($matches === [] ? [] : ['matches' => $matches])]),
            metadata: ['project' => Row::text($line, 'project_public_id'), 'line' => Row::text($line, 'line_no'), 'business_date' => $entry->businessDate],
        );

        return $movement;
    }

    /**
     * The lots the operator confirmed, read under their locks: each of this product, each place holding what is drawn (C-03), a serial in stock, usable and at its stated lot and rack, named once (C-02; BR-INV-09); together exactly the quantity.
     *
     * @return array<int, array{company: int, qty: BigDecimal}>
     */
    private function ensurePicks(DispatchEntry $entry, int $product, bool $serialized, BigDecimal $base): array
    {
        $lots = [];
        $places = [];
        $named = [];
        $total = BigDecimal::zero();

        foreach ($entry->picks as $pick) {
            $qty = BigDecimal::of($pick['qty']);
            $lot = DB::table('inventory_lots')->where('id', $pick['lot'])->where('product_id', $product)->value('source_company_id');

            if (! is_numeric($lot) || ! $qty->isPositive()) {
                throw Refused::rejected('C-14', ['reason' => 'lot']);
            }

            $place = "{$pick['lot']}|{$pick['location']}";
            $places[$place] = BigDecimal::of($places[$place] ?? '0')->plus($qty);
            $held = DB::table('stock_lot_balances as b')->where('lot_id', $pick['lot'])->where('location_id', $pick['location'])->where('condition', 'USABLE')->value('qty');

            if (! is_string($held) || $places[$place]->isGreaterThan($held)) {
                throw Refused::rejected('C-03', ['reason' => 'lot_short', 'available' => is_string($held) ? $held : '0']);
            }

            if ($serialized !== ($pick['serial'] !== null)) {
                throw Refused::rejected('C-02', ['reason' => 'serial']);
            }

            if ($pick['serial'] !== null) {
                $present = DB::table('serial_units')->where('id', $pick['serial'])->where('product_id', $product)->where('state', 'IN_STOCK')->where('current_lot_id', $pick['lot'])->where('current_location_id', $pick['location'])->where('current_condition', 'USABLE')->exists();

                if (! $present || ! $qty->isEqualTo(1) || isset($named[$pick['serial']])) {
                    throw Refused::rejected('C-02', ['reason' => 'serial']);
                }

                $named[$pick['serial']] = true;
            }

            $lots[$pick['lot']] = ['company' => (int) $lot, 'qty' => isset($lots[$pick['lot']]) ? $lots[$pick['lot']]['qty']->plus($qty) : $qty];
            $total = $total->plus($qty);
        }

        if (! $total->isEqualTo($base)) {
            throw Refused::rejected('C-03', ['reason' => 'picks', 'expected' => (string) $base]);
        }

        return $lots;
    }

    /**
     * XL-1 and AZ-08: another company's lot needs ADM+ inter-company authority with a grant on the consuming company only, and its reason; otherwise refused and routed before anything is written, one audit event per affected company committed with the refusal (SQ-23).
     *
     * @param  array<int, array{company: int, qty: BigDecimal}>  $crossing
     */
    private function ensureAllocation(CommandContext $context, DispatchEntry $entry, int $company, int $project, array $crossing): void
    {
        if ($crossing === []) {
            return;
        }

        $authority = $context->authority();
        $reason = $entry->allocationReason === null ? '' : trim($entry->allocationReason);

        if ($authority !== null && $authority->scope->includes($company) && $authority->allows('stock.allocate_intercompany', $company) && $reason !== '') {
            return;
        }

        $actor = $context->request->actorUserId;
        $command = $context->request->commandId;
        $sources = array_values(array_unique(array_map(fn (array $lot): int => $lot['company'], $crossing)));

        throw Refused::rejected('AZ-08', ['routed' => true, 'reason' => $reason === '' && $authority !== null && $authority->allows('stock.allocate_intercompany', $company) ? 'allocation_reason' : 'allocation'], function (ConnectionInterface $connection) use ($sources, $company, $project, $actor, $command): void {
            $this->audit->record(action: 'INV.AllocationRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $company, entityType: 'project', entityId: $project, commandId: $command, metadata: ['routed_action' => 'INV.Dispatch'], connection: $connection);

            foreach ($sources as $source) {
                $this->audit->record(action: 'INV.AllocationRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $source, entityType: 'company', entityId: $source, commandId: $command, metadata: ['routed_action' => 'INV.Dispatch'], connection: $connection);
            }
        });
    }

    /**
     * BD-13's likely duplicates: a dispatch of the same line, quantity and business date, not wholly restored since, read under LK-19 (L-49).
     *
     * @return list<string> the movements' public identifiers
     */
    public static function likelyDuplicates(int $lineId, string $base, string $businessDate): array
    {
        $found = [];

        foreach (DB::select(<<<'SQL'
            select m.public_id from dispatch_lines d join stock_movements m on m.id = d.stock_movement_id
            where d.project_line_id = ? and d.qty_base = ?::numeric and m.business_date = ?::date
                and exists (select 1 from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id where c.dispatch_line_id = d.id and b.qty_base - b.restored_qty_base - b.substituted_qty_base > 0)
            order by m.public_id
            SQL, [$lineId, $base, $businessDate]) as $row) {
            if (is_object($row)) {
                $found[] = Row::text($row, 'public_id');
            }
        }

        return $found;
    }

    /**
     * A replayed refusal's current state (CI-05): the likely duplicates, what the line and the pool hold now.
     *
     * @return array<string, mixed>|null
     */
    public static function refusalState(?string $code, DispatchEntry $entry): ?array
    {
        $line = self::line($entry->lineId);
        $ratio = DB::table('product_units')->where('product_id', (int) Row::text($line, 'product_id'))->where('unit_id', $entry->unitId)->value('ratio_to_base');

        return match ($code) {
            'L-49' => ['matches' => self::likelyDuplicates($entry->lineId, (string) BigDecimal::of($entry->quantity)->multipliedBy(is_numeric($ratio) ? (int) $ratio : 1), $entry->businessDate)],
            'PX-03' => ['remainder' => (string) BigDecimal::of(Row::nullable($line, 'demand') ?? '0')->minus(Row::nullable($line, 'dispatched') ?? '0')->minus(Row::nullable($line, 'cancelled') ?? '0')],
            'C-04' => ['available' => (string) InventoryPosting::available((int) Row::text($line, 'product_id'))->plus(Row::nullable($line, 'reserved') ?? '0')],
            default => null,
        };
    }

    private static function line(int $lineId): object
    {
        return DB::table('project_lines as l')->join('projects as p', 'p.id', '=', 'l.project_id')->join('products as r', 'r.id', '=', 'l.product_id')->leftJoin('project_line_balances as b', 'b.project_line_id', '=', 'l.id')->where('l.id', $lineId)
            ->first(['l.id', 'l.project_id', 'l.company_id', 'l.product_id', 'l.quantity_scale', 'l.fulfillment_mode', 'l.line_no', 'l.current_pin_id as pinned', 'p.state as project_state', 'p.public_id as project_public_id', 'r.is_serialized', 'r.quantity_scale as product_scale',
                'b.demand_qty_base as demand', 'b.reserved_qty_base as reserved', 'b.dispatched_net_qty_base as dispatched', 'b.cancelled_qty_base as cancelled'])
            ?? throw Refused::rejected('C-14', ['reason' => 'line']);
    }
}
