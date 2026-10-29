<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\CST\Data\ReceiptPosted;
use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\ReceivingLine;
use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockMovementEntry;
use App\Modules\INV\Models\StockRestoration;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Costing\ChargeAllocator;
use App\Support\GuardUpdate;
use App\Support\Money;
use Closure;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\INV\LocationFactory;
use Database\Factories\INV\StockMovementFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TASK-036's synthetic callers of `CostPosting` (TEST_STRATEGY §5): until the purchasing, receiving, dispatch, loss and
 * correction commands exist (TASK-037–TASK-044), each fact is recorded here as its command will record it — the
 * receiving lot and the line's received quantity, the consumption and its movement, the restoration, the reversal, the
 * closed remainder, the adjustment and the corrected value, the charge and its shares — and then posted through CST's
 * one entry point inside the command envelope, holding the lock set CST names; PUR's own guard moves by what CST
 * returns, as `PurchasePosting` will move it.
 */
final class CostWorld
{
    public Company $company;

    public int $productId;

    public int $locationId;

    public int $actorId;

    private function __construct() {}

    public static function make(?Company $company = null): self
    {
        $world = new self;
        $world->company = $company ?? Company::factory()->create();
        $world->productId = Product::factory()->create(['quantity_scale' => 0])->id;
        $world->locationId = LocationFactory::new()->createOne()->id;
        $world->actorId = Authorization::admin([$world->company->id], ['projects.view'])->id;

        return $world;
    }

    public function purchase(?Project $project = null): Purchase
    {
        return Purchase::factory()->create(['company_id' => $this->company->id, 'project_id' => $project?->id]);
    }

    /** A purchase line of the world's product: its ordered quantity, value and non-creditable tax, its guard born. */
    public function line(Purchase $purchase, string $qty, string $value, string $tax = '0.00', int $lineNo = 0): PurchaseLine
    {
        $line = PurchaseLine::factory()->create([
            'purchase_id' => $purchase->id, 'product_id' => $this->productId, 'entered_qty' => $qty, 'qty_base' => $qty,
            'unit_price' => Money::of($value)->share('1', $qty)->toString(), 'line_value' => $value,
            ...($lineNo > 0 ? ['line_no' => $lineNo] : []),
        ]);
        DB::table('purchase_line_balances')->insert(['purchase_line_id' => $line->id, 'ordered_qty_base' => $qty, 'goods_value' => $value, 'noncreditable_tax_value' => $tax]);

        return $line;
    }

    public function project(?Company $company = null): Project
    {
        return Project::factory()->create(['company_id' => ($company ?? $this->company)->id]);
    }

    /**
     * A charge of the purchase with its source bill of `billTotal`, its shares at version 1 split on the basis given.
     *
     * @param  array<int, string>  $basis  purchase line id => basis value, in line order
     */
    public function charge(Purchase $purchase, string $amount, array $basis = [], string $classification = 'ATTRIBUTABLE', ?int $billId = null, string $billTotal = '1000000000.00'): PurchaseCharge
    {
        $billId ??= $this->bill($billTotal);
        $charge = PurchaseCharge::factory()->create([
            'purchase_id' => $purchase->id, 'company_id' => $this->company->id, 'source_bill_evidence_id' => $billId, 'classification' => $classification,
            'amount' => $amount, 'allocation_basis' => $classification === 'ATTRIBUTABLE' ? 'LINE_VALUE' : null,
        ]);
        $this->shares($charge, $amount, $basis, 1);

        return $charge;
    }

    /** A supplier bill of the world's company, its proven total on its guard. */
    public function bill(string $total): int
    {
        $bill = EvidenceDocumentFactory::new()->supplierBill()->createOne(['company_id' => $this->company->id])->id;
        DB::table('evidence_balances')->insert(['evidence_document_id' => $bill, 'proven_amount' => $total]);

        return $bill;
    }

    /** @param  array<int, string>  $basis */
    public function shares(PurchaseCharge $charge, string $amount, array $basis, int $version): void
    {
        if ($basis === []) {
            return;
        }

        foreach (ChargeAllocator::shares(Money::of($amount), $basis) as $line => $share) {
            PurchaseChargeLineShare::factory()->create([
                'purchase_charge_id' => $charge->id, 'company_id' => $this->company->id, 'purchase_line_id' => $line, 'basis_value' => $basis[$line],
                'share_amount' => $share->toString(), 'allocation_version' => $version,
            ]);
        }
    }

    public function recordCharge(PurchaseCharge $charge): Outcome
    {
        return $this->run(CostPosting::chargeLocks($charge->id), function (CommandContext $context) use ($charge): void {
            app(CostPosting::class)->chargeRecorded($context, $charge->id, BusinessDate::today());
        });
    }

    /** A receiving event of `qty` on the line: its lot, its movement, the line's received quantity, its cost basis. */
    public function receive(PurchaseLine $line, string $qty): int
    {
        $lot = 0;
        $this->expect($this->run(CostPosting::purchaseLocks($line->purchase_id), function (CommandContext $context) use ($line, $qty, &$lot): void {
            $receiving = ReceivingLine::factory()->create(['purchase_line_id' => $line->id, 'entered_qty' => $qty, 'qty_base' => $qty, 'usable_qty_base' => $qty]);
            $lot = InventoryLot::factory()->create([
                'acquisition_kind' => 'RECEIPT', 'import_row_id' => null, 'receiving_line_id' => $receiving->id, 'source_company_id' => $line->company_id,
                'product_id' => $this->productId, 'entered_qty' => $qty, 'acquired_qty_base' => $qty, 'business_date' => BusinessDate::today(),
            ])->id;
            $this->entry($lot, (int) $receiving->stock_movement_id, 'RECEIPT', $qty);
            $this->moveLine($context, $line->id, ['normal_received_net_qty_base' => $qty]);
            $this->posted($context, $line->id, app(CostPosting::class)->receipt($context, $lot, BusinessDate::today()));
        }));

        return $lot;
    }

    /** A dispatch of `qty` from the lot to a project — another company's, with its allocation reason. */
    public function dispatch(int $lot, string $qty, ?Project $project = null, ?string $reason = null): int
    {
        $project ??= $this->project();
        $consumption = 0;
        $locks = CostPosting::lotLocks([$lot])->add('projects', ['id' => $project->id]);
        $this->expect($this->run($locks, function (CommandContext $context) use ($lot, $qty, $project, $reason, &$consumption): void {
            $line = ProjectLine::factory()->create(['project_id' => $project->id, 'product_id' => $this->productId]);
            $dispatch = DispatchLine::factory()->create(['project_line_id' => $line->id, 'entered_qty' => $qty, 'qty_base' => $qty, 'unreserved_qty_base' => $qty]);
            $consumption = $this->consume($lot, $qty, 'DISPATCH', (int) $dispatch->stock_movement_id, [
                'dispatch_line_id' => $dispatch->id, 'bearer_company_id' => $project->company_id, 'bearer_project_id' => $project->id,
                'allocation_reason' => $reason, 'allocation_authorized_by' => $reason === null ? null : $this->actorId,
            ]);
            app(CostPosting::class)->draw($context, $consumption, BusinessDate::today());
        }));

        return $consumption;
    }

    /** A loss of `qty` from the lot at its condition, borne by the project given or by the world's company. */
    public function loss(int $lot, string $qty, ?Project $project = null, string $condition = 'USABLE'): int
    {
        $consumption = 0;
        $locks = CostPosting::lotLocks([$lot]);
        $locks = $project === null ? $locks : $locks->add('projects', ['id' => $project->id]);
        $this->expect($this->run($locks, function (CommandContext $context) use ($lot, $qty, $project, $condition, &$consumption): void {
            $movement = StockMovementFactory::post('LOSS', $this->company->id);
            $consumption = $this->consume($lot, $qty, 'LOSS', $movement, ['bearer_company_id' => $this->company->id, 'bearer_project_id' => $project?->id], $condition);
            app(CostPosting::class)->draw($context, $consumption, BusinessDate::today());
        }));

        return $consumption;
    }

    /** A restoration of `qty` of the consumption into its lot: the contra posted before its quantity moves the balance. */
    public function restore(int $consumption, string $qty, string $kind = 'SALES_RETURN', string $condition = 'USABLE'): int
    {
        $row = StockConsumption::query()->findOrFail($consumption);
        $restoration = 0;
        $this->expect($this->run(CostPosting::lotLocks([$row->lot_id]), function (CommandContext $context) use ($row, $qty, $kind, $condition, &$restoration): void {
            // A dispatch's restoration cites its dispatch line, at that line's unit and ratio (L-31).
            $dispatch = $row->dispatch_line_id === null ? null : DispatchLine::query()->findOrFail($row->dispatch_line_id);
            $restoration = StockRestoration::factory()->create([
                'stock_consumption_id' => $row->id, 'entered_qty' => $qty, 'qty_base' => $qty, 'kind' => $kind, 'returned_condition' => $condition,
                'after_delivery' => match ($kind) {
                    'SALES_RETURN' => true,
                    'DISPATCH_REVERSAL', 'UNDELIVERED_RETURN' => false,
                    default => null,
                },
                'cited_dispatch_line_id' => $dispatch?->id, 'cited_unit_id' => $dispatch?->entered_unit_id, 'cited_ratio_to_base' => $dispatch?->ratio_to_base,
            ])->id;
            app(CostPosting::class)->restore($context, $restoration, BusinessDate::today());
            $column = $kind === 'SUBSTITUTION' ? 'substituted_qty_base' : 'restored_qty_base';
            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $row->id], 'deltas' => [$column => $qty]]]);
            $movement = (int) StockRestoration::query()->findOrFail($restoration)->stock_movement_id;
            $this->entry($row->lot_id, $movement, 'RESTORATION', $qty, ['stock_restoration_id' => $restoration], $condition);
        }));

        return $restoration;
    }

    /** `qty` of a dispatch lost in transit: the reclassification pair, then the balance's reclassified quantity. */
    public function reclassify(int $consumption, string $qty): void
    {
        $row = StockConsumption::query()->findOrFail($consumption);
        $this->expect($this->run(CostPosting::lotLocks([$row->lot_id]), function (CommandContext $context) use ($row, $qty): void {
            app(CostPosting::class)->reclassify($context, $row->id, $qty, BusinessDate::today());
            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $row->id], 'deltas' => ['reclassified_qty_base' => $qty]]]);
        }));
    }

    /** A receipt reversed for `qty` of the lot's unconsumed units: its movement, the line's quantity, the cost. */
    public function reverseReceipt(int $lot, string $qty): void
    {
        $row = InventoryLot::query()->findOrFail($lot);
        $line = (int) ReceivingLine::query()->findOrFail($row->receiving_line_id)->purchase_line_id;
        $purchase = (int) PurchaseLine::query()->findOrFail($line)->purchase_id;
        $this->expect($this->run(CostPosting::purchaseLocks($purchase), function (CommandContext $context) use ($lot, $qty, $line): void {
            $movement = StockMovementFactory::post('RECEIPT_REVERSAL', $this->company->id);
            $this->entry($lot, $movement, 'RECEIPT_REVERSAL', '-'.$qty);
            $this->moveLine($context, $line, ['normal_received_net_qty_base' => '-'.$qty]);
            $this->posted($context, $line, app(CostPosting::class)->reverseReceipt($context, $lot, $qty, BusinessDate::today()));
        }));
    }

    /** The line's unreceived remainder of `qty` closed (AX-32). */
    public function closeRemainder(PurchaseLine $line, string $qty): void
    {
        $this->expect($this->run(CostPosting::purchaseLocks($line->purchase_id), function (CommandContext $context) use ($line, $qty): void {
            $this->moveLine($context, $line->id, ['closed_qty_base' => $qty]);
            app(CostPosting::class)->remainderEnded($context, $line->id, BusinessDate::today());
        }));
    }

    /** The line's goods value corrected to `value` (AX-34; CM-17). */
    public function correctPrice(PurchaseLine $line, string $value): Outcome
    {
        return $this->run(CostPosting::purchaseLocks($line->purchase_id), function (CommandContext $context) use ($line, $value): void {
            $before = (string) DB::table('purchase_line_balances')->where('purchase_line_id', $line->id)->value('goods_value');
            $delta = Money::of($value)->minus(Money::of($before))->toString();
            $adjustment = (int) DB::table('purchase_line_adjustments')->insertGetId([
                'purchase_line_id' => $line->id, 'company_id' => $line->company_id, 'kind' => 'PRICE_CORRECTION', 'amount_delta' => $delta, 'reason' => 'Harga salah catat',
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->actorId,
            ]);
            $this->moveLine($context, $line->id, ['goods_value' => $delta]);
            $this->posted($context, $line->id, app(CostPosting::class)->lineCorrected($context, $adjustment, BusinessDate::today()));
        });
    }

    /**
     * The charge's amount, basis or both corrected (AX-34; CM-35): its adjustment for an amount, its next version's shares.
     *
     * @param  array<int, string>  $basis
     */
    public function correctCharge(PurchaseCharge $charge, string $amount, array $basis): Outcome
    {
        return $this->run(CostPosting::chargeLocks($charge->id), function (CommandContext $context) use ($charge, $amount, $basis): void {
            $before = (string) DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge->id)->value('amount');
            $delta = Money::of($amount)->minus(Money::of($before));
            $adjustment = $delta->isZero() ? null : (int) DB::table('purchase_line_adjustments')->insertGetId([
                'purchase_charge_id' => $charge->id, 'company_id' => $charge->company_id, 'kind' => 'CHARGE_CORRECTION', 'amount_delta' => $delta->toString(), 'reason' => 'Ongkos salah catat',
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->actorId,
            ]);
            $version = (int) DB::table('purchase_charge_line_shares')->where('purchase_charge_id', $charge->id)->max('allocation_version') + 1;
            $this->shares($charge, $amount, $basis, $version);
            app(CostPosting::class)->chargeCorrected($context, $charge->id, $adjustment, BusinessDate::today());
        });
    }

    /** A posting run in the command envelope, holding the lock set given (CONCURRENCY_IDEMPOTENCY §5). */
    public function run(LockSet $locks, Closure $work): Outcome
    {
        $request = new CommandRequest((string) Str::uuid(), 'test.costing', $this->actorId, $this->company->id, 'projects.view', Fingerprint::of('test.costing', null, ['run' => (string) Str::uuid()]));

        return app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($locks, $work): CommandResult {
            $context->lock($locks);
            $work($context);

            return new CommandResult('test', 0);
        });
    }

    public function expect(Outcome $outcome): void
    {
        if ($outcome->kind !== Outcome::COMMITTED) {
            throw new RuntimeException("The synthetic posting was {$outcome->kind} {$outcome->code}: ".json_encode($outcome->state));
        }
    }

    /** @param  array<string, mixed>  $columns */
    private function consume(int $lot, string $qty, string $kind, int $movement, array $columns, string $condition = 'USABLE'): int
    {
        $consumption = StockConsumption::factory()->create(['lot_id' => $lot, 'qty_base' => $qty, 'kind' => $kind, 'stock_movement_id' => $movement, ...$columns])->id;
        DB::table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption, 'qty_base' => $qty]);
        $this->entry($lot, $movement, (string) DB::table('stock_movements')->where('id', $movement)->value('movement_type'), '-'.$qty, ['stock_consumption_id' => $consumption], $condition);

        return $consumption;
    }

    /**
     * A movement entry on the lot, with the stock guards it moves as INV's `InventoryPosting` will move them — the
     * product's on-hand and unusable quantities and the lot's quantity at its place —, so the reconciliation suite reads
     * the synthetic stock as it reads posted stock.
     *
     * @param  array<string, int>  $references
     */
    private function entry(int $lot, int $movement, string $type, string $qty, array $references = [], string $condition = 'USABLE'): void
    {
        StockMovementEntry::factory()->create([
            'lot_id' => $lot, 'product_id' => $this->productId, 'stock_movement_id' => $movement, 'movement_type' => $type, 'location_id' => $this->locationId,
            'condition' => $condition, 'qty_delta_base' => $qty, ...$references,
        ]);
        $place = ['lot_id' => $lot, 'location_id' => $this->locationId, 'condition' => $condition];

        if (! DB::table('stock_lot_balances')->where($place)->exists()) {
            DB::table('stock_lot_balances')->insert([...$place, 'product_id' => $this->productId]);
        }

        if (! DB::table('stock_product_balances')->where('product_id', $this->productId)->exists()) {
            DB::table('stock_product_balances')->insert(['product_id' => $this->productId]);
        }

        DB::table('stock_lot_balances')->where($place)->update(['qty' => DB::raw("qty + cast('{$qty}' as numeric)")]);
        DB::table('stock_product_balances')->where('product_id', $this->productId)->update([
            'on_hand_qty' => DB::raw("on_hand_qty + cast('{$qty}' as numeric)"),
            'unusable_qty' => DB::raw($condition === 'UNUSABLE' ? "unusable_qty + cast('{$qty}' as numeric)" : 'unusable_qty'),
        ]);
    }

    /** @param  array<string, string>  $deltas */
    private function moveLine(CommandContext $context, int $line, array $deltas): void
    {
        GuardUpdate::apply($context->connection, 'purchase_line_balances', [['key' => ['purchase_line_id' => $line], 'deltas' => $deltas]]);
    }

    private function posted(CommandContext $context, int $line, ReceiptPosted $posted): void
    {
        if (! $posted->goods->isZero() || ! $posted->tax->isZero()) {
            $this->moveLine($context, $line, ['posted_goods_value' => $posted->goods->toString(), 'posted_noncreditable_tax_value' => $posted->tax->toString()]);
        }
    }
}
