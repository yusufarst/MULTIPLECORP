<?php

namespace Tests\Support;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Actions\ReverseMovement;
use App\Modules\INV\Models\InventoryLot;
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
        $purchase = ReceivingWorld::purchase($company, $product, $unit, $qty, $price);
        $line = ReceivingWorld::line($purchase);
        $word = ReceivingWorld::receive($actor, $company->id, $purchase->id, $line->id, $unit, $rack, $qty);

        if ($word !== 'COMMITTED') {
            throw new RuntimeException("The receipt was {$word}.");
        }

        return ReceivingWorld::lotOf($line->id);
    }

    /** A CONDITION_CHANGE of `qty` from USABLE to UNUSABLE at the lot's place; its movement. */
    public static function conditionChange(int $actor, Company $company, int $product, int $lot, int $rack, string $qty): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($company, $product, $lot, $rack, $qty, &$movement): void {
            $movement = StockMovementFactory::post('CONDITION_CHANGE', $company->id);
            $base = ['lot' => $lot, 'product' => $product, 'scale' => 0, 'location' => $rack];
            app(InventoryPosting::class)->ledger($context, $movement, 'CONDITION_CHANGE', [[...$base, 'condition' => 'USABLE', 'qty' => '-'.$qty], [...$base, 'condition' => 'UNUSABLE', 'qty' => $qty]]);
        });

        return $movement;
    }

    /** A movement that created a consumption — an adjustment decrease, a loss, a count's loss — with its loss drawn; the movement. */
    public static function decrease(int $actor, Company $company, int $product, int $lot, int $rack, string $qty, string $type = 'ADJUSTMENT_DECREASE', string $condition = 'USABLE'): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, CostPosting::lotLocks([$lot]), function (CommandContext $context) use ($company, $product, $lot, $rack, $qty, $type, $condition, &$movement): void {
            $movement = StockMovementFactory::post($type, $company->id);
            $consumption = StockConsumption::factory()->create(['lot_id' => $lot, 'qty_base' => $qty, 'kind' => 'LOSS', 'stock_movement_id' => $movement, 'bearer_company_id' => $company->id, 'bearer_project_id' => null, 'source_company_id' => $company->id, 'product_id' => $product, 'quantity_scale' => 0])->id;
            DB::table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption, 'qty_base' => $qty]);
            app(InventoryPosting::class)->ledger($context, $movement, $type, [['lot' => $lot, 'product' => $product, 'scale' => 0, 'location' => $rack, 'condition' => $condition, 'qty' => '-'.$qty, 'consumption' => $consumption]]);
            app(CostPosting::class)->draw($context, $consumption, BusinessDate::today());
        });

        return $movement;
    }

    /** A pending LOSS case's recognition at the scope: a lot-less entry, the claims unchanged (DATABASE §5.8); the movement. */
    public static function recognition(int $actor, Company $company, int $product, int $lot, int $rack, string $qty, string $exposure): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($company, $product, $lot, $rack, $qty, $exposure, &$movement): void {
            // A test's one transaction keeps an earlier command's `set constraints all immediate`: C-36 reads the rows written together.
            DB::statement('set constraints all deferred');
            $movement = StockMovementFactory::post('UNATTRIBUTED_RECOGNITION', null);
            $case = UnattributedLossCase::factory()->create(['case_kind' => 'LOSS', 'product_id' => $product, 'quantity_scale' => 0, 'location_id' => $rack, 'condition' => 'USABLE', 'recognized_qty_base' => $qty, 'exposure_qty_base' => $exposure, 'recognition_movement_id' => $movement])->id;
            UnattributedLossCaseFactory::expose($case, $company->id, $exposure);
            UnattributedLossCandidate::factory()->create(['unattributed_loss_case_id' => $case, 'lot_id' => $lot, 'candidate_company_id' => $company->id, 'qty_in_scope_at_recognition' => $exposure]);
            app(InventoryPosting::class)->ledger($context, $movement, 'UNATTRIBUTED_RECOGNITION', [['lot' => null, 'case' => $case, 'product' => $product, 'scale' => 0, 'location' => $rack, 'condition' => 'USABLE', 'qty' => '-'.$qty, 'pending' => ['pending_reduction_qty' => $qty]]]);
        });

        return $movement;
    }

    /** A surplus attributed to the company: a flagged lot with its basis, its finding ATTRIBUTED (AX-07); the movement. */
    public static function surplus(int $actor, Company $company, int $product, int $rack, string $qty, string $basis): int
    {
        $movement = 0;
        self::post($actor, $company, $product, $rack, new LockSet, function (CommandContext $context) use ($company, $product, $rack, $qty, $basis, &$movement): void {
            $movement = StockMovementFactory::post('SURPLUS_ATTRIBUTION', $company->id);
            $count = StockCount::factory()->create(['location_id' => $rack, 'condition' => 'USABLE']);
            $finding = StockCountFinding::factory()->create(['stock_count_id' => $count->id, 'product_id' => $product, 'quantity_scale' => 0, 'surplus_qty_base' => $qty, 'state' => 'ATTRIBUTED', 'attributed_company_id' => $company->id, 'flagged_cost_basis' => $basis]);
            $lot = InventoryLot::factory()->create(['acquisition_kind' => 'SURPLUS', 'import_row_id' => null, 'stock_count_finding_id' => $finding->id, 'source_company_id' => $company->id, 'product_id' => $product, 'quantity_scale' => 0, 'entered_qty' => $qty, 'acquired_qty_base' => $qty, 'business_date' => BusinessDate::today()])->id;
            app(CostPosting::class)->basis($context, $lot, 'SURPLUS_FLAGGED_BASIS', Money::of($basis), BusinessDate::today(), []);
            app(InventoryPosting::class)->ledger($context, $movement, 'SURPLUS_ATTRIBUTION', [['lot' => $lot, 'product' => $product, 'scale' => 0, 'location' => $rack, 'condition' => 'USABLE', 'qty' => $qty]]);
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

    private static function post(int $actor, Company $company, int $product, int $rack, LockSet $locks, \Closure $work): void
    {
        $world = CostWorld::resume(['company' => $company->id, 'product' => $product, 'location' => $rack, 'actor' => $actor]);
        $world->expect($world->run($locks, $work));
    }
}
