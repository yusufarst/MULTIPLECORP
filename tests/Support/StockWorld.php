<?php

namespace Tests\Support;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Actions\ReverseMovement;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockCount;
use App\Modules\INV\Models\StockCountFinding;
use App\Modules\INV\Models\UnattributedLossCandidate;
use App\Modules\INV\Models\UnattributedLossCase;
use App\Modules\INV\Services\InventoryPosting;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\Money;
use Database\Factories\INV\StockMovementFactory;
use Database\Factories\INV\UnattributedLossCaseFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TASK-040's world (TL-04–TL-08): lots from real receipts, and fixture movements of each reversible type posted through `InventoryPosting` until TASK-042 and TASK-043 add the commands that record them. Static, so the closures of the two-connection harness can call them.
 */
final class StockWorld
{
    public const CAPABILITIES = ['cost.view', 'purchase.manage', 'purchase.correct_cost'];

    /** A lot of `qty` received at the rack through ROUTE-181's action. */
    public static function lot(int $actor, Company $company, int $product, int $unit, int $rack, string $qty = '10', string $price = '1000'): int
    {
        $purchase = ReceivingWorld::purchase($company, $product, $unit, $qty, $price, actor: $actor);
        $line = ReceivingWorld::line($purchase);
        $word = ReceivingWorld::receive($actor, $company->id, $purchase->id, $line->id, $unit, $rack, $qty);

        if ($word !== 'COMMITTED') {
            throw new RuntimeException("The receipt was {$word}.");
        }

        return ReceivingWorld::lotOf($line->id);
    }

    /** A CONDITION_CHANGE of `qty` out of `from` at the lot's place, of one serial when it names one; its movement. */
    public static function conditionChange(int $actor, Company $company, int $product, int $lot, int $rack, string $qty, string $from = 'USABLE', ?int $serial = null): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($company, $product, $lot, $rack, $qty, $from, $serial, &$movement): void {
            $movement = StockMovementFactory::post('CONDITION_CHANGE', $company->id);
            $base = ['lot' => $lot, 'product' => $product, 'scale' => 0, 'location' => $rack, ...($serial === null ? [] : ['serial' => $serial])];
            $to = $from === 'USABLE' ? 'UNUSABLE' : 'USABLE';
            $ids = app(InventoryPosting::class)->ledger($context, $movement, 'CONDITION_CHANGE', [[...$base, 'condition' => $from, 'qty' => '-'.$qty], [...$base, 'condition' => $to, 'qty' => $qty]]);

            if ($serial !== null) {
                DB::table('serial_units')->where('id', $serial)->update(['current_condition' => $to, 'last_entry_id' => $ids[1]]);
            }
        });

        return $movement;
    }

    /** A unit of the lot at the rack, in stock: its serial. */
    public static function serial(int $product, int $lot, int $rack, string $condition = 'USABLE'): int
    {
        return SerialUnit::factory()->create(['product_id' => $product, 'state' => 'IN_STOCK', 'current_lot_id' => $lot, 'current_location_id' => $rack, 'current_condition' => $condition])->id;
    }

    /** A movement that created a consumption — an adjustment decrease, a loss, a count's loss — with its loss drawn; the movement. */
    public static function decrease(int $actor, Company $company, int $product, int $lot, int $rack, string $qty, string $type = 'ADJUSTMENT_DECREASE', string $condition = 'USABLE', ?int $bearerProject = null, bool $pool = false): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, CostPosting::lotLocks([$lot]), function (CommandContext $context) use ($company, $product, $lot, $rack, $qty, $type, $condition, $bearerProject, $pool, &$movement): void {
            $movement = StockMovementFactory::post($type, $pool ? null : $company->id);
            $consumption = StockConsumption::factory()->create(['lot_id' => $lot, 'qty_base' => $qty, 'kind' => 'LOSS', 'stock_movement_id' => $movement, 'bearer_company_id' => $company->id, 'bearer_project_id' => $bearerProject, 'source_company_id' => $company->id, 'product_id' => $product, 'quantity_scale' => 0])->id;
            DB::table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption, 'qty_base' => $qty]);
            app(InventoryPosting::class)->ledger($context, $movement, $type, [['lot' => $lot, 'product' => $product, 'scale' => 0, 'location' => $rack, 'condition' => $condition, 'qty' => '-'.$qty, 'consumption' => $consumption]]);
            app(CostPosting::class)->draw($context, $consumption, BusinessDate::today());
        });

        return $movement;
    }

    /** A pending LOSS case's recognition at the scope: a lot-less entry, the claims unchanged (DATABASE §5.8); the movement. */
    public static function recognition(int $actor, Company $company, int $product, int $lot, int $rack, string $qty, string $exposure, ?int $group = null): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($actor, $company, $product, $lot, $rack, $qty, $exposure, $group, &$movement): void {
            // A test's one transaction keeps an earlier command's `set constraints all immediate`: C-36 reads the rows written together.
            DB::statement('set constraints all deferred');
            $movement = StockMovementFactory::post('UNATTRIBUTED_RECOGNITION', null);
            $case = UnattributedLossCase::factory()->create(['case_kind' => 'LOSS', 'product_id' => $product, 'quantity_scale' => 0, 'location_id' => $rack, 'condition' => 'USABLE', 'recognized_qty_base' => $qty, 'exposure_qty_base' => $exposure, 'recognition_movement_id' => $movement, 'exposure_group_case_id' => $group, 'recognized_by' => $actor])->id;
            UnattributedLossCaseFactory::expose($case, $company->id, $exposure);
            UnattributedLossCandidate::factory()->create(['unattributed_loss_case_id' => $case, 'lot_id' => $lot, 'candidate_company_id' => $company->id, 'qty_in_scope_at_recognition' => $exposure]);
            app(InventoryPosting::class)->ledger($context, $movement, 'UNATTRIBUTED_RECOGNITION', [['lot' => null, 'case' => $case, 'product' => $product, 'scale' => 0, 'location' => $rack, 'condition' => 'USABLE', 'qty' => '-'.$qty, 'pending' => ['pending_reduction_qty' => $qty]]]);
        });

        return $movement;
    }

    /** A pending CONDITION_CHANGE case's recognition: − usable and + unusable, lot-less, the claims unchanged (DATABASE §5.8); the movement. */
    public static function pendingChange(int $actor, Company $company, int $product, int $lot, int $rack, string $qty, string $exposure): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($company, $product, $lot, $rack, $qty, $exposure, &$movement): void {
            DB::statement('set constraints all deferred');
            [$movement, $case] = self::movementWithCase('UNATTRIBUTED_RECOGNITION', ['case_kind' => 'CONDITION_CHANGE', 'product_id' => $product, 'quantity_scale' => 0, 'location_id' => $rack, 'condition' => 'USABLE', 'recognized_qty_base' => $qty, 'exposure_qty_base' => $exposure]);
            UnattributedLossCaseFactory::expose($case, $company->id, $exposure);
            UnattributedLossCandidate::factory()->create(['unattributed_loss_case_id' => $case, 'lot_id' => $lot, 'candidate_company_id' => $company->id, 'qty_in_scope_at_recognition' => $exposure]);
            $base = ['lot' => null, 'case' => $case, 'product' => $product, 'scale' => 0, 'location' => $rack];
            app(InventoryPosting::class)->ledger($context, $movement, 'UNATTRIBUTED_RECOGNITION', [[...$base, 'condition' => 'USABLE', 'qty' => '-'.$qty, 'pending' => ['pending_reduction_qty' => $qty]], [...$base, 'condition' => 'UNUSABLE', 'qty' => $qty, 'pending' => ['pending_addition_qty' => $qty]]]);
        });

        return $movement;
    }

    /** A finding on a pending LOSS case: + lot-less, its found quantity raised (DATABASE §5.8); the movement. */
    public static function found(int $actor, Company $company, int $product, int $rack, int $case, string $qty): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($product, $rack, $case, $qty, &$movement): void {
            $movement = StockMovementFactory::post('UNATTRIBUTED_FOUND', null);
            app(InventoryPosting::class)->ledger($context, $movement, 'UNATTRIBUTED_FOUND', [['lot' => null, 'case' => $case, 'product' => $product, 'scale' => 0, 'location' => $rack, 'condition' => 'USABLE', 'qty' => $qty, 'pending' => ['pending_reduction_qty' => '-'.$qty]]]);
            $context->connection->update('update unattributed_loss_cases set found_qty_base = found_qty_base + ?::numeric where id = ?', [$qty, $case]);
        });

        return $movement;
    }

    /**
     * A disposal converting `qty` of a pending condition change (DATABASE §5.8): under the old case + usable and − unusable, under its new LOSS successor − usable; the movement, whose header names the successor.
     *
     * @return array{int, int} the movement and the successor
     */
    public static function conversion(int $actor, Company $company, int $product, int $lot, int $rack, int $old, string $qty, string $exposure): array
    {
        $movement = 0;
        $successor = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($company, $product, $lot, $rack, $old, $qty, $exposure, &$movement, &$successor): void {
            DB::statement('set constraints all deferred');
            [$movement, $successor] = self::movementWithCase('UNATTRIBUTED_CONVERSION', ['case_kind' => 'LOSS', 'product_id' => $product, 'quantity_scale' => 0, 'location_id' => $rack, 'condition' => 'USABLE', 'recognized_qty_base' => $qty, 'exposure_qty_base' => $exposure, 'converted_from_case_id' => $old]);
            UnattributedLossCaseFactory::expose($successor, $company->id, $exposure);
            UnattributedLossCandidate::factory()->create(['unattributed_loss_case_id' => $successor, 'lot_id' => $lot, 'candidate_company_id' => $company->id, 'qty_in_scope_at_recognition' => $exposure]);
            $base = ['lot' => null, 'product' => $product, 'scale' => 0, 'location' => $rack];
            app(InventoryPosting::class)->ledger($context, $movement, 'UNATTRIBUTED_CONVERSION', [
                [...$base, 'case' => $old, 'condition' => 'USABLE', 'qty' => $qty, 'pending' => ['pending_reduction_qty' => '-'.$qty]],
                [...$base, 'case' => $old, 'condition' => 'UNUSABLE', 'qty' => '-'.$qty, 'pending' => ['pending_addition_qty' => '-'.$qty]],
                [...$base, 'case' => $successor, 'condition' => 'USABLE', 'qty' => '-'.$qty, 'pending' => ['pending_reduction_qty' => $qty]],
            ]);
            $context->connection->update('update unattributed_loss_cases set converted_qty_base = converted_qty_base + ?::numeric where id = ?', [$qty, $old]);
        });

        return [$movement, $successor];
    }

    /**
     * C-61 for a reversal: the registered guards, and the three this Task moves whose families are not registered yet, each equal to the ledger (DATABASE §3): the scope's claims and pending columns, the reversal guard, the consumption guard.
     *
     * @return list<mixed>
     */
    public static function ledgerDifferences(): array
    {
        $found = [];

        foreach (['stock_product_balances', 'stock_lot_balances', 'lot_cost_balances'] as $table) {
            $found = [...$found, ...(new Reconciler)->differences(DB::connection(), Families::named($table) ?? throw new RuntimeException($table))];
        }

        return [...$found,
            ...DB::select(<<<'SQL'
                select 'scope' as guard, s.product_id, s.location_id, s.condition from stock_scope_balances s
                cross join lateral (
                    select coalesce(sum(e.qty_delta_base) filter (where e.lot_id is not null), 0) as claims,
                        coalesce(-sum(e.qty_delta_base) filter (where e.lot_id is null and not (k.case_kind = 'CONDITION_CHANGE' and e.condition <> k.condition)), 0) as reduction,
                        coalesce(sum(e.qty_delta_base) filter (where e.lot_id is null and k.case_kind = 'CONDITION_CHANGE' and e.condition <> k.condition), 0) as addition
                    from stock_movement_entries e left join unattributed_loss_cases k on k.id = e.unattributed_case_id
                    where e.product_id = s.product_id and e.location_id = s.location_id and e.condition = s.condition
                ) t
                where s.claims_qty <> t.claims or s.pending_reduction_qty <> t.reduction or s.pending_addition_qty <> t.addition
                SQL),
            ...DB::select(<<<'SQL'
                select 'reversal' as guard, g.reversed_movement_id, g.lot_id from stock_reversal_balances g
                cross join lateral (
                    select abs(coalesce((select sum(o.qty_delta_base) from stock_movement_entries o where o.stock_movement_id = g.reversed_movement_id and o.lot_id = g.lot_id and o.location_id = g.location_id and o.condition = g.condition), 0)) as original,
                        abs(coalesce((select sum(e.qty_delta_base) from stock_movement_entries e join stock_movements m on m.id = e.stock_movement_id where m.reverses_movement_id = g.reversed_movement_id and e.lot_id = g.lot_id and e.location_id = g.location_id and e.condition = g.condition), 0)) as reversed
                ) t
                where g.original_qty_base <> t.original or g.reversed_qty_base <> t.reversed
                SQL),
            ...DB::select(<<<'SQL'
                select 'consumption' as guard, b.stock_consumption_id from stock_consumption_balances b
                where b.stock_consumption_id is not null and b.restored_qty_base <> coalesce((select sum(r.qty_base) from stock_restorations r where r.stock_consumption_id = b.stock_consumption_id and r.kind = 'LOSS_REVERSAL'), 0)
                SQL),
        ];
    }

    /** A surplus attributed to the company: a flagged lot with its basis, its finding ATTRIBUTED (AX-07); the movement. */
    public static function surplus(int $actor, Company $company, int $product, int $rack, string $qty, string $basis, bool $serialized = false): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($company, $product, $rack, $qty, $basis, $serialized, &$movement): void {
            $movement = StockMovementFactory::post('SURPLUS_ATTRIBUTION', $company->id);
            $count = StockCount::factory()->create(['location_id' => $rack, 'condition' => 'USABLE']);
            $finding = StockCountFinding::factory()->create(['stock_count_id' => $count->id, 'product_id' => $product, 'quantity_scale' => 0, 'surplus_qty_base' => $qty, 'state' => 'ATTRIBUTED', 'attributed_company_id' => $company->id, 'flagged_cost_basis' => $basis]);
            $lot = InventoryLot::factory()->create(['acquisition_kind' => 'SURPLUS', 'import_row_id' => null, 'stock_count_finding_id' => $finding->id, 'source_company_id' => $company->id, 'product_id' => $product, 'quantity_scale' => 0, 'entered_qty' => $qty, 'acquired_qty_base' => $qty, 'business_date' => BusinessDate::today()])->id;
            app(CostPosting::class)->basis($context, $lot, 'SURPLUS_FLAGGED_BASIS', Money::of($basis), BusinessDate::today(), []);
            $serial = $serialized ? self::serial($product, $lot, $rack) : null;
            $ids = app(InventoryPosting::class)->ledger($context, $movement, 'SURPLUS_ATTRIBUTION', [['lot' => $lot, 'product' => $product, 'scale' => 0, 'location' => $rack, 'condition' => 'USABLE', 'qty' => $qty, ...($serial === null ? [] : ['serial' => $serial])]]);

            if ($serial !== null) {
                DB::table('serial_units')->where('id', $serial)->update(['last_entry_id' => $ids[0]]);
            }
        });

        return $movement;
    }

    /**
     * ROUTE-212's action as its own command; its word.
     *
     * @param  array<int, string>  $cuts
     */
    public static function reverse(int $actor, ?int $company, int $movement, array $cuts = []): string
    {
        Numbering::goLive(substr(BusinessDate::today(), 0, 4).'-01-01');

        return InvoiceWorld::word(app(ReverseMovement::class)->run((string) Str::uuid(), $actor, $company, $movement, $cuts, 'Pergerakan keliru', BusinessDate::today(), false));
    }

    /**
     * A header naming its case, as C-07 reads it, and the case naming its movement (DATABASE §5.8): both keys immediate and the header never updated, so they are written by one statement; identities always generated, so the fixture chooses them.
     *
     * @param  array<string, mixed>  $case
     * @return array{int, int} the movement and the case
     */
    private static function movementWithCase(string $type, array $case): array
    {
        $movement = (int) DB::table('stock_movements')->max('id') + 1000;
        $id = (int) DB::table('unattributed_loss_cases')->max('id') + 1000;
        $row = [...$case, 'recognition_movement_id' => $movement, 'business_date' => BusinessDate::today(), 'recognized_by' => DB::table('users')->value('id'), 'reason' => 'Selisih opname tanpa bukti perusahaan'];
        DB::statement('with header as (insert into stock_movements (id, movement_type, business_date, unattributed_case_id) overriding system value values (?, ?, ?, ?)) insert into unattributed_loss_cases (id, '.implode(', ', array_keys($row)).') overriding system value values (?'.str_repeat(', ?', count($row)).')', [$movement, $type, BusinessDate::today(), $id, $id, ...array_values($row)]);

        return [$movement, $id];
    }

    private static function post(int $actor, Company $company, int $product, int $rack, LockSet $locks, \Closure $work): void
    {
        $world = CostWorld::resume(['company' => $company->id, 'product' => $product, 'location' => $rack, 'actor' => $actor]);
        $world->expect($world->run($locks, $work));
    }
}
