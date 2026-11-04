<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What "Balikkan" on PG-020 records (CM-28; NX-11; C-55; DATABASE §5.3): a new movement naming what it reverses, never an edit of the ledger. A condition change, a surplus attribution and a pending case's recognition, finding or conversion by a MOVEMENT_REVERSAL equal and opposite on the same lots, serials and condition, capped by the reversal guard (FU-03) or, for lot-less entries, by what the movement has not given back and by the case's counters; a movement that created consumptions — an adjustment decrease, a loss, a count's loss — never by that, but by capped restorations of its consumptions with their loss reversed exactly (SF-LOSS), each needing the bearer company and the case's in scope (AZ-08; XL-1). A surplus attribution is an Owner's decision: only the Owner reverses it (OD-15). The reservations are re-evaluated in the same action (SF-RSV-CUT; IP-20).
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
        private readonly Revalidation $revalidation,
        private readonly AuditRecorder $audit,
    ) {}

    /** An opening import's stock movement: reversed only by "Koreksi Data Awal" (ROUTE-213; CM-33), never by ROUTE-212. */
    public const OPENING = 'OPENING';

    public static function reversible(string $type): bool
    {
        return in_array($type, self::BY_INVERSE, true) || in_array($type, self::BY_RESTORATION, true);
    }

    /**
     * NX-11's set, re-derived under the locks (§5): the product, the scopes and lot places its entries touch, its reversal guard rows, serials, its pending cases, count and finding, a surplus lot's cost guard, its consumptions with their guards and projects, the reservations cut. Never a lot's other dependents: nothing here moves them (QB-13).
     *
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function locks(int $movementId, array $cuts): LockSet
    {
        $set = new LockSet;
        $products = [];
        $header = DB::table('stock_movements')->where('id', $movementId)->first(['movement_type', 'stock_count_id']);
        $type = $header === null ? '' : Row::text($header, 'movement_type');
        $count = $header === null ? null : Row::nullable($header, 'stock_count_id');
        $inverse = in_array($type, [...self::BY_INVERSE, self::OPENING], true);

        if ($count !== null) {
            $set->add('stock_counts', ['id' => (int) $count]);
        }

        foreach (self::entries($movementId) as $entry) {
            $product = (int) Row::text($entry, 'product_id');
            $location = (int) Row::text($entry, 'location_id');
            $lot = Row::nullable($entry, 'lot_id');
            $case = Row::nullable($entry, 'unattributed_case_id');
            $products[$product] = true;
            $set->add('stock_product_balances', ['product_id' => $product]);

            foreach (['USABLE', 'UNUSABLE'] as $either) {
                $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $location, 'condition' => $either]);

                if ($lot !== null) {
                    $set->add('stock_lot_balances', ['lot_id' => (int) $lot, 'location_id' => $location, 'condition' => $either]);

                    if ($inverse) {
                        $set->add('stock_reversal_balances', ['reversed_movement_id' => $movementId, 'lot_id' => (int) $lot, 'location_id' => $location, 'condition' => $either]);
                    }
                }
            }

            if ($lot !== null && $type === self::OPENING) {
                $set->add('lot_cost_balances', ['lot_id' => (int) $lot]);
            }

            if ($lot !== null && $type === 'SURPLUS_ATTRIBUTION') {
                $set->add('lot_cost_balances', ['lot_id' => (int) $lot]);
                $finding = DB::table('inventory_lots')->where('id', (int) $lot)->value('stock_count_finding_id');

                if (is_numeric($finding)) {
                    $set->add('stock_count_findings', ['id' => (int) $finding]);
                }
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
                $set->add('stock_consumption_balances', ['stock_consumption_id' => (int) $consumption]);
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

        $owner = $context->authority()?->ownerRole === true;

        if ($type === 'SURPLUS_ATTRIBUTION' && ! $owner) {
            throw Refused::rejected('OD-15', ['reason' => 'owner_only']);
        }

        [$movement, $publicId, $quantity] = in_array($type, self::BY_INVERSE, true)
            ? $this->inverse($context, $original, $cuts, $reason, $businessDate)
            : $this->restored($context, $original, $reason, $businessDate);
        $company = Row::nullable($original, 'company_id');
        $this->corrections->record($context, 'INV.MovementReversed', $type === 'SURPLUS_ATTRIBUTION' ? AuthorityClass::OwnerOnly : AuthorityClass::AdmPlus, $company === null ? null : (int) $company, 'stock_movement', $movement, $publicId, AuditRelation::Reverses, 'stock_movement', Row::text($original, 'public_id'), $reason,
            AuditChange::updated(['movement_type' => $type], ['reversed' => $quantity]), null, ['business_date' => $businessDate, 'cuts' => $cuts]);

        return $movement;
    }

    /**
     * ROUTE-213 (CM-33; IMP-06): a wrong opening stock fact corrected by its import identity — the OPENING movement's
     * MOVEMENT_REVERSAL within the unconsumed quantity still at its place (C-03), reservations cut first when the stock
     * they hold is taken (SF-RSV-CUT), the serials taken back, and the lot's opening basis reversed with its units —; its
     * movement. A wrong opening fact is never imported again.
     *
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function reverseOpening(CommandContext $context, int $movementId, array $cuts, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require($this->locks($movementId, $cuts));
        $original = DB::table('stock_movements')->where('id', $movementId)->first(['id', 'public_id', 'movement_type', 'company_id', 'project_id']);

        if ($original === null || Row::text($original, 'movement_type') !== self::OPENING) {
            throw Refused::rejected('C-55', ['reason' => 'type']);
        }

        [$movement, $publicId, $quantity] = $this->inverse($context, $original, $cuts, $reason, $businessDate, unconsumed: true);

        foreach (DB::table('stock_movement_entries as e')->join('inventory_lots as i', 'i.id', '=', 'e.lot_id')->where('e.stock_movement_id', $movement)
            ->groupBy('i.id', 'i.import_row_id')->selectRaw('i.id, i.import_row_id, -sum(e.qty_delta_base) as qty')->get() as $lot) {
            $this->cost->reverseBasis($context, (int) Row::text($lot, 'id'), Row::text($lot, 'qty'), $businessDate, ['import_row_id' => (int) Row::text($lot, 'import_row_id')]);
        }

        $company = Row::nullable($original, 'company_id');
        $this->corrections->record($context, 'INV.OpeningMovementReversed', AuthorityClass::AdmPlus, $company === null ? null : (int) $company, 'stock_movement', $movement, $publicId, AuditRelation::Reverses, 'stock_movement', Row::text($original, 'public_id'), $reason,
            AuditChange::updated(['movement_type' => self::OPENING], ['reversed' => $quantity]), null, ['business_date' => $businessDate, 'cuts' => $cuts]);

        return $movement;
    }

    /**
     * A MOVEMENT_REVERSAL equal and opposite (C-55): each entry at most its key's original net of earlier reversals.
     *
     * @param  array<int, string>  $cuts
     * @return array{int, string, string}
     */
    private function inverse(CommandContext $context, object $original, array $cuts, string $reason, string $businessDate, bool $unconsumed = false): array
    {
        $movementId = (int) Row::text($original, 'id');
        $type = Row::text($original, 'movement_type');
        $entries = [];
        $guards = [];
        $originals = self::originals($movementId);
        $left = $this->remaining($movementId, $originals);

        foreach (self::entries($movementId) as $entry) {
            $lot = Row::nullable($entry, 'lot_id');

            if ($lot === null) {
                continue;
            }

            $qty = BigDecimal::of(Row::text($entry, 'qty_delta_base'));
            $location = (int) Row::text($entry, 'location_id');
            $condition = Row::text($entry, 'condition');
            $key = self::key((int) $lot, $location, $condition);
            $take = BigDecimal::min($qty->abs(), $left[$key] ?? BigDecimal::zero());

            // CM-33: an opening fact is corrected within the unconsumed quantity, what is still at its place.
            if ($unconsumed && ! $qty->isNegative()) {
                $held = DB::table('stock_lot_balances as b')->where('b.lot_id', (int) $lot)->where('b.location_id', $location)->where('b.condition', $condition)->value('b.qty');
                $take = BigDecimal::min($take, BigDecimal::of(is_string($held) ? $held : '0'));
            }

            if (! $take->isPositive()) {
                continue;
            }

            $left[$key] = ($left[$key] ?? BigDecimal::zero())->minus($take);
            $signed = $qty->isNegative() ? $take : $take->negated();
            $guards[$key] = ['lot' => (int) $lot, 'location' => $location, 'condition' => $condition, 'qty' => ($guards[$key]['qty'] ?? BigDecimal::zero())->plus($take)];
            $row = ['lot' => (int) $lot, 'product' => (int) Row::text($entry, 'product_id'), 'scale' => (int) Row::text($entry, 'quantity_scale'), 'location' => $location, 'condition' => $condition, 'qty' => (string) $signed];
            $serial = Row::nullable($entry, 'serial_unit_id');

            if ($serial !== null) {
                $row['serial'] = (int) $serial;
            }

            if ($signed->isNegative()) {
                self::ensureHeld((int) $lot, $location, $condition, $take);
            }

            $entries[] = $row;
        }

        [$pending, $cases, $moved] = $this->pendingInverse($movementId, $type);
        $entries = [...$entries, ...$pending];

        if ($entries === []) {
            throw Refused::rejected('C-55', ['reason' => 'reversed']);
        }

        $product = $entries[0]['product'];
        $usable = BigDecimal::zero();
        $up = BigDecimal::zero();
        $down = BigDecimal::zero();

        foreach ($entries as $entry) {
            $qty = BigDecimal::of($entry['qty']);
            $usable = $entry['condition'] === 'USABLE' ? $usable->plus($qty) : $usable;
            $up = $qty->isPositive() ? $up->plus($qty) : $up;
            $down = $qty->isNegative() ? $down->minus($qty) : $down;
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

        self::serialsBack($entries, $ids);

        if ($cases !== []) {
            $this->moveCases($context, $type, $cases, $moved);
        }

        if ($type === 'SURPLUS_ATTRIBUTION') {
            $this->surplusUndone($context, $movementId, $businessDate);
        }

        return [$movement, $publicId, (string) BigDecimal::max($up, $down)];
    }

    /**
     * Capped restorations of the movement's consumptions, each with its loss reversed exactly (SF-LOSS; C-27); the original is never MOVEMENT_REVERSAL'd. The bearer company and the case's must be within the actor's grants (AZ-08; XL-1), the source company's never asked. A loss moved off a completed project revalidates it (SF-REVAL; BR-CR-05).
     *
     * @return array{int, string, string}
     */
    private function restored(CommandContext $context, object $original, string $reason, string $businessDate): array
    {
        $movementId = (int) Row::text($original, 'id');
        $consumptions = [];

        foreach (self::entries($movementId) as $entry) {
            $id = Row::nullable($entry, 'stock_consumption_id');
            $balance = $id === null ? null : DB::table('stock_consumption_balances as b')->join('stock_consumptions as c', 'c.id', '=', 'b.stock_consumption_id')->where('b.stock_consumption_id', (int) $id)
                ->first(['b.qty_base', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base', 'c.source_company_id', 'c.bearer_company_id', 'c.bearer_project_id', 'c.correction_case_id', 'c.case_company_id']);

            if ($id !== null && $balance !== null) {
                $consumptions[] = [(int) $id, $entry, $balance];
            }
        }

        $this->withinGrants($context, array_map(fn (array $found): object => $found[2], $consumptions), $movementId);
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
        $projects = [];

        foreach ($consumptions as [$consumptionId, $entry, $balance]) {
            $open = BigDecimal::of(Row::text($balance, 'qty_base'))->minus(Row::text($balance, 'restored_qty_base'))->minus(Row::text($balance, 'reclassified_qty_base'))->minus(Row::text($balance, 'substituted_qty_base'));

            if (! $open->isPositive()) {
                continue;
            }

            $product = (int) Row::text($entry, 'product_id');
            $unit = DB::table('products')->where('id', $product)->value('base_unit_id');
            $restoration = (int) DB::table('stock_restorations')->insertGetId([
                'stock_movement_id' => $movement, 'stock_consumption_id' => $consumptionId, 'source_company_id' => (int) Row::text($balance, 'source_company_id'), 'product_id' => $product, 'quantity_scale' => (int) Row::text($entry, 'quantity_scale'),
                'entered_qty' => (string) $open, 'entered_unit_id' => is_numeric($unit) ? (int) $unit : 0, 'ratio_to_base' => 1, 'qty_base' => (string) $open, 'kind' => 'LOSS_REVERSAL', 'returned_condition' => Row::text($entry, 'condition'),
                'correction_case_id' => Row::nullable($balance, 'correction_case_id'), 'case_company_id' => Row::nullable($balance, 'case_company_id'),
                'command_id' => $commandId, 'command_line_no' => ++$line, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $moved = $this->cost->restore($context, $restoration, $businessDate);
            $bearer = Row::nullable($balance, 'bearer_project_id');

            if ($moved !== null && $bearer !== null) {
                $projects[(int) $bearer] = true;
            }

            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $consumptionId], 'deltas' => ['restored_qty_base' => (string) $open]]]);
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

        foreach (array_keys($projects) as $project) {
            $this->revalidation->revalidate($context, $project, $publicId);
        }

        return [$movement, $publicId, (string) $total];
    }

    /**
     * AZ-08's chosen effect on another company's record: refused and routed before anything is written, one audit event per such company committed with the refusal (SQ-23).
     *
     * @param  list<object>  $consumptions
     */
    private function withinGrants(CommandContext $context, array $consumptions, int $movementId): void
    {
        $authority = $context->authority();
        $outside = [];

        foreach ($consumptions as $consumption) {
            $companies = [(int) Row::text($consumption, 'bearer_company_id')];
            $case = Row::nullable($consumption, 'case_company_id');

            if ($case !== null) {
                $companies[] = (int) $case;
            }

            foreach ($companies as $company) {
                if ($authority === null || ! $authority->scope->includes($company) || ! $authority->allows('stock.reverse', $company)) {
                    $outside[$company] = true;
                }
            }
        }

        if ($outside !== []) {
            $actor = $context->request->actorUserId;
            $command = $context->request->commandId;

            throw Refused::rejected('AZ-08', ['routed' => true, 'reason' => 'bearer'], function (ConnectionInterface $connection) use ($outside, $actor, $command, $movementId): void {
                foreach (array_keys($outside) as $company) {
                    $this->audit->record(action: 'INV.MovementReversalRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $company,
                        entityType: 'stock_movement', entityId: $movementId, commandId: $command, metadata: ['routed_action' => 'INV.ReverseMovement'], connection: $connection);
                }
            });
        }
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

        foreach (DB::table('stock_reversal_balances as g')->where('reversed_movement_id', $movementId)->get(['lot_id', 'location_id', 'condition', 'reversed_qty_base']) as $row) {
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

    /**
     * The inverse of the movement's lot-less entries (DATABASE §5.8): one quantity across them all — a condition change's pair, a conversion's rows, never part of one —, at most what each key has not given back (C-55) and its counter: a recognition's what is pending, a finding's what was found, a conversion's what its successor has pending.
     *
     * @return array{list<array{lot: null, case: int, product: int, scale: int, location: int, condition: string, qty: string, pending: array<string, string>}>, list<int>, string}
     */
    private function pendingInverse(int $movementId, string $type): array
    {
        $rows = array_values(array_filter(self::entries($movementId), fn (object $entry): bool => Row::nullable($entry, 'lot_id') === null));

        if ($rows === []) {
            return [[], [], '0'];
        }

        $given = [];

        foreach (DB::table('stock_movement_entries as e')->join('stock_movements as r', 'r.id', '=', 'e.stock_movement_id')->where('r.reverses_movement_id', $movementId)->whereNotNull('e.unattributed_case_id')->get(['e.unattributed_case_id', 'e.location_id', 'e.condition', 'e.qty_delta_base']) as $row) {
            $key = self::key((int) Row::text($row, 'unattributed_case_id'), (int) Row::text($row, 'location_id'), Row::text($row, 'condition'));
            $given[$key] = ($given[$key] ?? BigDecimal::zero())->plus(BigDecimal::of(Row::text($row, 'qty_delta_base'))->abs());
        }

        $ids = array_values(array_unique(array_map(fn (object $entry): int => (int) Row::text($entry, 'unattributed_case_id'), $rows)));
        $cases = DB::table('unattributed_loss_cases')->whereIn('id', $ids)->get(['id', 'case_kind', 'condition', 'pending_qty_base', 'found_qty_base', 'converted_from_case_id'])->keyBy('id')->all();
        $caps = [];

        foreach ($rows as $entry) {
            $key = self::key((int) Row::text($entry, 'unattributed_case_id'), (int) Row::text($entry, 'location_id'), Row::text($entry, 'condition'));
            $caps[] = BigDecimal::of(Row::text($entry, 'qty_delta_base'))->abs()->minus($given[$key] ?? BigDecimal::zero());
        }

        foreach ($cases as $case) {
            $counter = match (true) {
                $type === 'UNATTRIBUTED_FOUND' => Row::text($case, 'found_qty_base'),
                $type !== 'UNATTRIBUTED_CONVERSION' => Row::text($case, 'pending_qty_base'),
                Row::nullable($case, 'converted_from_case_id') !== null => Row::text($case, 'pending_qty_base'),
                default => null,
            };

            if ($counter !== null) {
                $caps[] = BigDecimal::of($counter);
            }
        }

        $take = BigDecimal::min(...$caps);

        if (! $take->isPositive()) {
            return [[], [], '0'];
        }

        $entries = [];

        foreach ($rows as $entry) {
            $id = (int) Row::text($entry, 'unattributed_case_id');
            $case = $cases[$id];
            $condition = Row::text($entry, 'condition');
            $signed = BigDecimal::of(Row::text($entry, 'qty_delta_base'))->isNegative() ? $take : $take->negated();
            // A condition change's quantity arrives in the other condition: the scope's addition; every other entry its reduction (DATABASE §5.8).
            $arriving = Row::text($case, 'case_kind') === 'CONDITION_CHANGE' && $condition !== Row::text($case, 'condition');
            $entries[] = ['lot' => null, 'case' => $id, 'product' => (int) Row::text($entry, 'product_id'), 'scale' => (int) Row::text($entry, 'quantity_scale'), 'location' => (int) Row::text($entry, 'location_id'), 'condition' => $condition, 'qty' => (string) $signed,
                'pending' => $arriving ? ['pending_addition_qty' => (string) $signed] : ['pending_reduction_qty' => (string) $signed->negated()]];
        }

        return [$entries, $ids, (string) $take];
    }

    /**
     * The cases reversed exactly with their movement (CM-28; DATABASE §5.8): a recognition's reversal raises its reversed quantity, a finding's lowers what was found, a conversion's lowers the old case's converted quantity and raises its successor's reversed quantity.
     *
     * @param  list<int>  $cases
     */
    private function moveCases(CommandContext $context, string $type, array $cases, string $qty): void
    {
        foreach ($cases as $case) {
            $column = match (true) {
                $type === 'UNATTRIBUTED_FOUND' => 'found_qty_base = found_qty_base - ?::numeric',
                $type !== 'UNATTRIBUTED_CONVERSION' => 'reversed_qty_base = reversed_qty_base + ?::numeric',
                DB::table('unattributed_loss_cases')->where('id', $case)->whereNotNull('converted_from_case_id')->exists() => 'reversed_qty_base = reversed_qty_base + ?::numeric',
                default => 'converted_qty_base = converted_qty_base - ?::numeric',
            };
            $context->connection->update("update unattributed_loss_cases set {$column} where id = ?", [$qty, $case]);
        }
    }

    /**
     * Each serial back with its movement (DATABASE §3): in a condition change's inverse to the condition it came from, having left the one it was moved to — another unit's balance never stands in for it (C-02; L-13) —; taken back out of the pool altogether, to the state before the movement.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  list<int>  $ids
     */
    private static function serialsBack(array $entries, array $ids): void
    {
        $leaving = [];
        $arriving = [];

        foreach ($entries as $entry) {
            $serial = $entry['serial'] ?? null;

            if (is_int($serial)) {
                if (BigDecimal::of(self::scalar($entry['qty']))->isNegative()) {
                    $leaving[$serial] = self::scalar($entry['condition']);
                } else {
                    $arriving[$serial] = true;
                }
            }
        }

        foreach ($entries as $index => $entry) {
            $serial = $entry['serial'] ?? null;

            if (! is_int($serial)) {
                continue;
            }

            $unit = DB::table('serial_units')->where('id', $serial)->where('state', 'IN_STOCK')->where('current_lot_id', $entry['lot'])->where('current_location_id', $entry['location']);

            if (! isset($arriving[$serial])) {
                $moved = $unit->where('current_condition', $entry['condition'])->update(['state' => 'NEVER_RECEIVED', 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null, 'last_entry_id' => $ids[$index]]);
            } elseif (BigDecimal::of(self::scalar($entry['qty']))->isPositive()) {
                $moved = (isset($leaving[$serial]) ? $unit->where('current_condition', $leaving[$serial]) : $unit)->update(['current_condition' => $entry['condition'], 'last_entry_id' => $ids[$index]]);
            } else {
                $moved = 1;
            }

            if ($moved !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial_reversal']);
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

    private static function key(int $owner, int $location, string $condition): string
    {
        return "{$owner}|{$location}|{$condition}";
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '0';
    }

    /** @return list<object> */
    private static function entries(int $movementId): array
    {
        return array_values(DB::table('stock_movement_entries as e')->leftJoin('serial_units as s', 's.id', '=', 'e.serial_unit_id')->where('e.stock_movement_id', $movementId)->orderBy('e.id')
            ->get(['e.id', 'e.product_id', 'e.quantity_scale', 'e.location_id', 'e.condition', 'e.lot_id', 'e.unattributed_case_id', 'e.serial_unit_id', 'e.qty_delta_base', 'e.stock_consumption_id', 's.serial_key'])->values()->all());
    }
}
