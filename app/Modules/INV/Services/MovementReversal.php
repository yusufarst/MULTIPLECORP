<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\OPS\Services\Corrections;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What "Balikkan" on PG-020 records (CM-28; NX-11; C-55; DATABASE §5.3): a new movement naming what it reverses, never an edit of the ledger. A condition change, a surplus attribution and a pending case's recognition, finding or conversion by a MOVEMENT_REVERSAL equal and opposite on the same lots, serials and condition, capped by the reversal guard (FU-03) or, for lot-less entries, by the case's counters; a movement that created consumptions — an adjustment decrease, a loss, a count's loss — never by that, but by capped restorations of its consumptions with their loss reversed exactly (SF-LOSS). The reservations are re-evaluated in the same action (SF-RSV-CUT; IP-20).
 */
final class MovementReversal
{
    /** The types a MOVEMENT_REVERSAL undoes on this route; the rest of DATABASE §5.3's list have their own (AX-36; ROUTE-213). */
    public const BY_INVERSE = ['CONDITION_CHANGE', 'SURPLUS_ATTRIBUTION', 'UNATTRIBUTED_RECOGNITION', 'UNATTRIBUTED_CONVERSION', 'UNATTRIBUTED_FOUND'];

    /** The types that created consumptions: only restorations undo them (C-27). */
    public const BY_RESTORATION = ['ADJUSTMENT_DECREASE', 'LOSS', 'COUNT_VARIANCE'];

    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly Corrections $corrections,
    ) {}

    public static function reversible(string $type): bool
    {
        return in_array($type, self::BY_INVERSE, true) || in_array($type, self::BY_RESTORATION, true);
    }

    /**
     * NX-11's set, re-derived under the locks (§5): the product, the scopes and lot places its entries touch, its reversal guard rows, serials, cases and finding, its consumptions with their projects, the reservations cut.
     *
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function locks(int $movementId, array $cuts): LockSet
    {
        $set = new LockSet;
        $products = [];

        foreach (self::entries($movementId) as $entry) {
            $product = (int) Row::text($entry, 'product_id');
            $location = (int) Row::text($entry, 'location_id');
            $condition = Row::text($entry, 'condition');
            $lot = Row::nullable($entry, 'lot_id');
            $case = Row::nullable($entry, 'unattributed_case_id');
            $products[$product] = true;
            $set->add('stock_product_balances', ['product_id' => $product]);

            foreach (['USABLE', 'UNUSABLE'] as $either) {
                $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $location, 'condition' => $either]);

                if ($lot !== null) {
                    $set->add('stock_lot_balances', ['lot_id' => (int) $lot, 'location_id' => $location, 'condition' => $either]);
                    $set->add('stock_reversal_balances', ['reversed_movement_id' => $movementId, 'lot_id' => (int) $lot, 'location_id' => $location, 'condition' => $either]);
                }
            }

            if ($lot !== null) {
                $set = $set->union(CostPosting::lotLocks([(int) $lot]));
            }

            $serial = Row::nullable($entry, 'serial_key');

            if ($serial !== null) {
                $set->add('serial_units', ['product_id' => $product, 'serial_key' => $serial]);
            }

            if ($case !== null) {
                $set->add('unattributed_loss_cases', ['id' => (int) $case]);
            }

            $consumption = Row::nullable($entry, 'stock_consumption_id');

            if ($consumption !== null) {
                $set = $set->union(CostPosting::consumptionLocks((int) $consumption));
            }
        }

        foreach (array_keys($products) as $product) {
            if ($cuts !== []) {
                $set = $set->union(InventoryPosting::cutLocks($product, array_keys($cuts)));
            }
        }

        return $set;
    }

    /**
     * ROUTE-212: the reversal; its movement.
     *
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function reverse(CommandContext $context, int $movementId, array $cuts, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require($this->locks($movementId, $cuts));
        $original = DB::table('stock_movements')->where('id', $movementId)->first(['id', 'public_id', 'movement_type', 'company_id', 'project_id']);

        if ($original === null) {
            throw Refused::rejected('C-14', ['reason' => 'movement']);
        }

        $type = Row::text($original, 'movement_type');

        if (! self::reversible($type)) {
            throw Refused::rejected('C-55', ['reason' => 'type']);
        }

        [$movement, $publicId, $quantity] = in_array($type, self::BY_INVERSE, true)
            ? $this->inverse($context, $original, $cuts, $reason, $businessDate)
            : $this->restored($context, $original, $reason, $businessDate);
        $company = Row::nullable($original, 'company_id');
        $this->corrections->record($context, 'INV.MovementReversed', AuthorityClass::AdmPlus, $company === null ? null : (int) $company, 'stock_movement', $movement, $publicId, AuditRelation::Reverses, 'stock_movement', Row::text($original, 'public_id'), $reason,
            AuditChange::updated(['movement_type' => $type], ['reversed' => $quantity]), null, ['business_date' => $businessDate, 'cuts' => $cuts]);

        return $movement;
    }

    /**
     * A MOVEMENT_REVERSAL equal and opposite (C-55): each entry at most its key's original net of earlier reversals.
     *
     * @param  array<int, string>  $cuts
     * @return array{int, string, string}
     */
    private function inverse(CommandContext $context, object $original, array $cuts, string $reason, string $businessDate): array
    {
        $movementId = (int) Row::text($original, 'id');
        $type = Row::text($original, 'movement_type');
        $entries = [];
        $guards = [];
        $cases = [];
        $serials = [];
        $product = 0;
        $usable = BigDecimal::zero();
        $total = BigDecimal::zero();
        $originals = self::originals($movementId);
        $left = $this->remaining($movementId, $originals);

        foreach (self::entries($movementId) as $entry) {
            $product = (int) Row::text($entry, 'product_id');
            $qty = BigDecimal::of(Row::text($entry, 'qty_delta_base'));
            $base = ['lot' => null, 'product' => $product, 'scale' => (int) Row::text($entry, 'quantity_scale'), 'location' => (int) Row::text($entry, 'location_id'), 'condition' => Row::text($entry, 'condition')];
            $lot = Row::nullable($entry, 'lot_id');

            if ($lot === null) {
                $case = (int) Row::text($entry, 'unattributed_case_id');
                $take = $this->caseRoom($case, $type, $qty->abs());

                if (! $take->isPositive()) {
                    continue;
                }

                $signed = $qty->isNegative() ? $take : $take->negated();
                $column = self::pendingColumn($case, $qty);
                $delta = $column === 'pending_reduction_qty' ? ($qty->isNegative() ? $take->negated() : $take) : $take->negated();
                $entries[] = [...$base, 'case' => $case, 'qty' => (string) $signed, 'pending' => [$column => (string) $delta]];
                $cases[$case] = ($cases[$case] ?? BigDecimal::zero())->plus($take);
            } else {
                $key = self::key((int) $lot, $base['location'], $base['condition']);
                $take = BigDecimal::min($qty->abs(), $left[$key] ?? BigDecimal::zero());

                if (! $take->isPositive()) {
                    continue;
                }

                $left[$key] = ($left[$key] ?? BigDecimal::zero())->minus($take);
                $signed = $qty->isNegative() ? $take : $take->negated();
                $guards[$key] = ['lot' => (int) $lot, 'location' => $base['location'], 'condition' => $base['condition'], 'qty' => ($guards[$key]['qty'] ?? BigDecimal::zero())->plus($take)];
                $row = [...$base, 'lot' => (int) $lot, 'qty' => (string) $signed];
                $serial = Row::nullable($entry, 'serial_unit_id');

                if ($serial !== null) {
                    $row['serial'] = (int) $serial;
                    $serials[(int) $serial] = ['lot' => (int) $lot, 'location' => $base['location'], 'condition' => $base['condition'], 'qty' => $signed];
                }

                if ($signed->isNegative()) {
                    self::ensureHeld((int) $lot, $base['location'], $base['condition'], $take);
                }

                $entries[] = $row;
            }

            $usable = $base['condition'] === 'USABLE' ? $usable->plus($signed) : $usable;
            $total = $total->plus($take);
        }

        if ($entries === []) {
            throw Refused::rejected('C-55', ['reason' => 'reversed']);
        }

        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'MOVEMENT_REVERSAL', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => Row::nullable($original, 'company_id'), 'project_id' => Row::nullable($original, 'project_id'),
            'reverses_movement_id' => $movementId, 'reverses_movement_type' => $type, 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ]);
        $reserved = null;

        if ($usable->isNegative() && (InventoryPosting::shortfall($product, (string) $usable->negated())->isPositive() || $cuts !== [])) {
            $reserved = $this->inventory->cut($context, $product, $cuts, (string) $usable->negated(), $movement, 'INV.ReverseMovement');
        }

        $ids = $this->inventory->ledger($context, $movement, 'MOVEMENT_REVERSAL', $entries, $reserved);

        foreach ($guards as $key => $guard) {
            $identity = ['reversed_movement_id' => $movementId, 'lot_id' => $guard['lot'], 'location_id' => $guard['location'], 'condition' => $guard['condition']];
            $context->connection->table('stock_reversal_balances')->insertOrIgnore([...$identity, 'original_qty_base' => (string) ($originals[$key] ?? BigDecimal::zero()), 'recorded_at' => $now]);
            GuardUpdate::apply($context->connection, 'stock_reversal_balances', [['key' => $identity, 'deltas' => ['reversed_qty_base' => (string) $guard['qty']]]]);
        }

        foreach ($entries as $index => $entry) {
            if (isset($entry['serial']) && BigDecimal::of($entry['qty'])->isPositive()) {
                $moved = DB::table('serial_units')->where('id', $entry['serial'])->where('state', 'IN_STOCK')->where('current_lot_id', $entry['lot'])->where('current_location_id', $entry['location'])
                    ->update(['current_condition' => $entry['condition'], 'last_entry_id' => $ids[$index]]);

                if ($moved !== 1) {
                    throw Refused::rejected('C-02', ['reason' => 'serial_reversal']);
                }
            }
        }

        foreach ($cases as $case => $qty) {
            $this->moveCase($context, $case, $type, $qty);
        }

        if ($type === 'SURPLUS_ATTRIBUTION') {
            $this->surplusUndone($context, $movementId, $businessDate);
        }

        return [$movement, $publicId, (string) $total];
    }

    /**
     * Capped restorations of the movement's consumptions, each with its loss reversed exactly (SF-LOSS; C-27); the original is never MOVEMENT_REVERSAL'd.
     *
     * @return array{int, string, string}
     */
    private function restored(CommandContext $context, object $original, string $reason, string $businessDate): array
    {
        $movementId = (int) Row::text($original, 'id');
        $publicId = (string) Str::uuid7();
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'RESTORATION', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => Row::nullable($original, 'company_id'), 'project_id' => Row::nullable($original, 'project_id'),
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $entries = [];
        $total = BigDecimal::zero();
        $line = 0;

        foreach (self::entries($movementId) as $entry) {
            $consumptionId = Row::nullable($entry, 'stock_consumption_id');

            if ($consumptionId === null) {
                continue;
            }

            $balance = DB::table('stock_consumption_balances as b')->join('stock_consumptions as c', 'c.id', '=', 'b.stock_consumption_id')->where('b.stock_consumption_id', (int) $consumptionId)
                ->first(['b.qty_base', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base', 'c.source_company_id', 'c.correction_case_id', 'c.case_company_id']);

            if ($balance === null) {
                continue;
            }

            $open = BigDecimal::of(Row::text($balance, 'qty_base'))->minus(Row::text($balance, 'restored_qty_base'))->minus(Row::text($balance, 'reclassified_qty_base'))->minus(Row::text($balance, 'substituted_qty_base'));

            if (! $open->isPositive()) {
                continue;
            }

            $product = (int) Row::text($entry, 'product_id');
            $unit = DB::table('products')->where('id', $product)->value('base_unit_id');
            $restoration = (int) DB::table('stock_restorations')->insertGetId([
                'stock_movement_id' => $movement, 'stock_consumption_id' => (int) $consumptionId, 'source_company_id' => (int) Row::text($balance, 'source_company_id'), 'product_id' => $product, 'quantity_scale' => (int) Row::text($entry, 'quantity_scale'),
                'entered_qty' => (string) $open, 'entered_unit_id' => is_numeric($unit) ? (int) $unit : 0, 'ratio_to_base' => 1, 'qty_base' => (string) $open, 'kind' => 'LOSS_REVERSAL', 'returned_condition' => Row::text($entry, 'condition'),
                'correction_case_id' => Row::nullable($balance, 'correction_case_id'), 'case_company_id' => Row::nullable($balance, 'case_company_id'),
                'command_id' => $commandId, 'command_line_no' => ++$line, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $this->cost->restore($context, $restoration, $businessDate);
            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => (int) $consumptionId], 'deltas' => ['restored_qty_base' => (string) $open]]]);
            $row = ['lot' => (int) Row::text($entry, 'lot_id'), 'product' => $product, 'scale' => (int) Row::text($entry, 'quantity_scale'), 'location' => (int) Row::text($entry, 'location_id'), 'condition' => Row::text($entry, 'condition'), 'qty' => (string) $open, 'restoration' => $restoration];
            $serial = Row::nullable($entry, 'serial_unit_id');

            if ($serial !== null) {
                $row['serial'] = (int) $serial;
            }

            $entries[] = $row;
            $total = $total->plus($open);
        }

        if ($entries === []) {
            throw Refused::rejected('C-55', ['reason' => 'reversed']);
        }

        $ids = $this->inventory->ledger($context, $movement, 'RESTORATION', $entries);

        foreach ($entries as $index => $entry) {
            if (isset($entry['serial']) && DB::table('serial_units')->where('id', $entry['serial'])->where('state', '<>', 'IN_STOCK')->update(['state' => 'IN_STOCK', 'current_lot_id' => $entry['lot'], 'current_location_id' => $entry['location'], 'current_condition' => $entry['condition'], 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial_reversal']);
            }
        }

        return [$movement, $publicId, (string) $total];
    }

    /**
     * What each lot key of the movement still may reverse: the original net of earlier reversals (FU-03; C-55).
     *
     * @param  array<string, BigDecimal>  $originals
     * @return array<string, BigDecimal>
     */
    private function remaining(int $movementId, array $originals): array
    {
        $left = $originals;

        foreach (DB::table('stock_reversal_balances')->where('reversed_movement_id', $movementId)->get(['lot_id', 'location_id', 'condition', 'reversed_qty_base']) as $row) {
            $key = self::key((int) Row::text($row, 'lot_id'), (int) Row::text($row, 'location_id'), Row::text($row, 'condition'));
            $left[$key] = ($left[$key] ?? BigDecimal::zero())->minus(Row::text($row, 'reversed_qty_base'));
        }

        return $left;
    }

    /**
     * The absolute net of the movement's lot entries at each key.
     *
     * @return array<string, BigDecimal>
     */
    private static function originals(int $movementId): array
    {
        $net = [];

        foreach (self::entries($movementId) as $entry) {
            $lot = Row::nullable($entry, 'lot_id');

            if ($lot !== null) {
                $key = self::key((int) $lot, (int) Row::text($entry, 'location_id'), Row::text($entry, 'condition'));
                $net[$key] = ($net[$key] ?? BigDecimal::zero())->plus(Row::text($entry, 'qty_delta_base'));
            }
        }

        return array_map(fn (BigDecimal $qty): BigDecimal => $qty->abs(), $net);
    }

    /** A lot-less entry's cap, the case's counters (DATABASE §5.3): a recognition or conversion by what is still pending, a finding by what is still found. */
    private function caseRoom(int $case, string $type, BigDecimal $qty): BigDecimal
    {
        $row = DB::table('unattributed_loss_cases')->where('id', $case)->first(['pending_qty_base', 'found_qty_base']);

        if ($row === null) {
            return BigDecimal::zero();
        }

        return BigDecimal::min($qty, BigDecimal::of(Row::text($row, $type === 'UNATTRIBUTED_FOUND' ? 'found_qty_base' : 'pending_qty_base')));
    }

    /** Which pending column of the scope guard a lot-less entry of the case moved: a loss's reduction, a condition change's reduction leaving and addition arriving (DATABASE §5.8). */
    private static function pendingColumn(int $case, BigDecimal $qty): string
    {
        $kind = DB::table('unattributed_loss_cases')->where('id', $case)->value('case_kind');

        return $kind === 'CONDITION_CHANGE' && $qty->isPositive() ? 'pending_addition_qty' : 'pending_reduction_qty';
    }

    /** The case reversed exactly with its movement (CM-28): a recognition's or conversion's reversal raises its reversed quantity, a finding's lowers what was found, a conversion's gives back its origin (DATABASE §5.8). */
    private function moveCase(CommandContext $context, int $case, string $type, BigDecimal $qty): void
    {
        if ($type === 'UNATTRIBUTED_FOUND') {
            $context->connection->update('update unattributed_loss_cases set found_qty_base = found_qty_base - ?::numeric where id = ?', [(string) $qty, $case]);

            return;
        }

        $context->connection->update('update unattributed_loss_cases set reversed_qty_base = reversed_qty_base + ?::numeric where id = ?', [(string) $qty, $case]);

        if ($type === 'UNATTRIBUTED_CONVERSION') {
            $from = DB::table('unattributed_loss_cases')->where('id', $case)->value('converted_from_case_id');

            if (is_numeric($from)) {
                $context->connection->update('update unattributed_loss_cases set converted_qty_base = converted_qty_base - ?::numeric where id = ?', [(string) $qty, (int) $from]);
            }
        }
    }

    /** A surplus attribution undone: the lot's flagged basis leaves with its units (DATABASE §5.3). The finding stays as decided: its lot is keyed to it, once (C-08). */
    private function surplusUndone(CommandContext $context, int $movementId, string $businessDate): void
    {
        foreach (DB::table('inventory_lots as i')->join('stock_movement_entries as e', 'e.lot_id', '=', 'i.id')->where('e.stock_movement_id', $movementId)->whereNotNull('i.stock_count_finding_id')
            ->groupBy('i.id')->selectRaw('i.id, sum(e.qty_delta_base) as qty')->get() as $lot) {
            $this->cost->reverseBasis($context, (int) Row::text($lot, 'id'), Row::text($lot, 'qty'), $businessDate);
        }
    }

    /** C-03: the units taken back are still at that place; anything else refused with what is there. */
    private static function ensureHeld(int $lot, int $location, string $condition, BigDecimal $qty): void
    {
        $held = DB::table('stock_lot_balances as b')->where('lot_id', $lot)->where('location_id', $location)->where('condition', $condition)->value('qty');

        if (! is_string($held) || $qty->isGreaterThan($held)) {
            throw Refused::rejected('C-03', ['reason' => 'moved_on', 'available' => is_string($held) ? $held : '0']);
        }
    }

    private static function key(int $lot, int $location, string $condition): string
    {
        return "{$lot}|{$location}|{$condition}";
    }

    /** @return list<object> */
    private static function entries(int $movementId): array
    {
        return array_values(DB::table('stock_movement_entries as e')->leftJoin('serial_units as s', 's.id', '=', 'e.serial_unit_id')->where('e.stock_movement_id', $movementId)->orderBy('e.id')
            ->get(['e.id', 'e.product_id', 'e.quantity_scale', 'e.location_id', 'e.condition', 'e.lot_id', 'e.unattributed_case_id', 'e.serial_unit_id', 'e.qty_delta_base', 'e.stock_consumption_id', 's.serial_key'])->values()->all());
    }
}
