<?php

namespace Tests\Support;

use App\Modules\CAT\Models\Product;
use App\Modules\CST\Data\ReceiptPosted;
use App\Modules\CST\Queries\AllocationOverlay;
use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FUL\Models\DropshipConfirmation;
use App\Modules\FUL\Models\DropshipConfirmationLine;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\ReceivingLine;
use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockMovementEntry;
use App\Modules\INV\Models\StockRestoration;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\TaxType;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use App\Modules\PRJ\Models\ProjectStateTransition;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use App\Modules\PUR\Models\PurchaseLine;
use App\Modules\PUR\Models\PurchaseLineTaxComponent;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Costing\ChargeAllocator;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\GuardUpdate;
use App\Support\Money;
use Closure;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Database\Factories\INV\LocationFactory;
use Database\Factories\INV\StockMovementFactory;
use Database\Factories\OPS\CorrectionCaseFactory;
use Database\Factories\ORG\TaxTreatmentRuleFactory;
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

    /** Each execution of a posting's action, a restart's included (§5; RY-03). */
    public int $attempts = 0;

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

    /**
     * The world as plain ids for another session: a session restores what its closure carries before it joins the test's
     * database, so it carries no model (Sessions::run).
     *
     * @return array{company: int, product: int, location: int, actor: int}
     */
    public function ids(): array
    {
        return ['company' => $this->company->id, 'product' => $this->productId, 'location' => $this->locationId, 'actor' => $this->actorId];
    }

    /** @param  array{company: int, product: int, location: int, actor: int}  $ids */
    public static function resume(array $ids): self
    {
        $world = new self;
        $world->company = Company::query()->findOrFail($ids['company']);
        $world->productId = $ids['product'];
        $world->locationId = $ids['location'];
        $world->actorId = $ids['actor'];

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

    /**
     * A purchase line with its input tax recorded under `treatment` (DIR-024 D-1): the guard's non-creditable tax is what a
     * treatment taking the tax into acquisition cost gives it, as PUR derives it; a creditable input tax gives it nothing.
     */
    public function taxedLine(Purchase $purchase, string $qty, string $value, string $tax, string $treatment): PurchaseLine
    {
        $line = $this->line($purchase, $qty, $value, $treatment === 'INPUT_TO_ACQUISITION_COST' ? $tax : '0.00');
        $type = TaxType::factory()->create()->id;
        PurchaseLineTaxComponent::factory()->create([
            'purchase_line_id' => $line->id, 'tax_type_id' => $type, 'treatment' => $treatment, 'base_amount' => $value, 'amount' => $tax,
            'treatment_rule_id' => TaxTreatmentRuleFactory::new()->createOne(['tax_type_id' => $type, 'transaction_context' => 'PURCHASE_LINE', 'treatment' => $treatment])->id,
        ]);

        return $line;
    }

    public function project(?Company $company = null, string $state = 'DRAFT'): Project
    {
        return Project::factory()->create(['company_id' => ($company ?? $this->company)->id, 'state' => $state]);
    }

    /** The project completed normally, its transition beside its state (SM:Project). */
    public function complete(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            DB::table('projects')->where('id', $project->id)->update(['state' => 'COMPLETED_NORMAL', 'lock_version' => DB::raw('lock_version + 1')]);
            ProjectStateTransition::factory()->create([
                'project_id' => $project->id, 'from_state' => 'ACTIVE', 'to_state' => 'COMPLETED_NORMAL', 'transition_kind' => 'COMPLETE_NORMAL', 'decided_by' => $this->actorId,
            ]);
        });
    }

    /** A purchase-return case of the world's company (C-46). */
    public function returnCase(): int
    {
        return CorrectionCaseFactory::new()->createOne(['company_id' => $this->company->id, 'case_kind' => 'PURCHASE_RETURN'])->id;
    }

    /** The case closed, the supplier's refund and replacement settling its returned cost (C-46's equation). */
    public function closeCase(int $case, string $refunded, string $replaced = '0.00'): void
    {
        DB::table('correction_cases')->where('id', $case)->update([
            'refunded_value' => $refunded, 'replaced_value' => $replaced, 'state' => 'CLOSED', 'closure_outcome' => 'Dana retur sudah diterima.', 'closed_by' => $this->actorId, 'closed_at' => now(),
        ]);
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

    /**
     * A charge created and recorded in one command, as ROUTE-171 records a purchase's charges (AX-29): nothing of it
     * stays when its recording is refused.
     *
     * @param  array<int, string>  $basis
     */
    public function recordNew(Purchase $purchase, string $amount, array $basis = [], string $classification = 'ATTRIBUTABLE', ?int $billId = null): Outcome
    {
        $billId ??= $this->bill('1000000000.00');

        return $this->run(CostPosting::purchaseLocks($purchase->id)->union(EvidenceGuard::locks($billId)), function (CommandContext $context) use ($purchase, $amount, $basis, $classification, $billId): void {
            $charge = $this->charge($purchase, $amount, $basis, $classification, $billId);
            app(CostPosting::class)->chargeRecorded($context, $charge->id, BusinessDate::today());
        });
    }

    /** A non-stock line of the purchase — a service bought for a project —, one unit of `value`, its guard born (L-18). */
    public function nonStockLine(Purchase $purchase, string $value, int $lineNo = 0): PurchaseLine
    {
        $line = PurchaseLine::factory()->create([
            'purchase_id' => $purchase->id, 'product_id' => $this->productId, 'kind' => 'NONSTOCK', 'entered_qty' => '1', 'qty_base' => '1', 'unit_price' => $value, 'line_value' => $value,
            ...($lineNo > 0 ? ['line_no' => $lineNo] : []),
        ]);
        DB::table('purchase_line_balances')->insert(['purchase_line_id' => $line->id, 'ordered_qty_base' => '1', 'goods_value' => $value]);

        return $line;
    }

    /** The non-stock line's cost attributed to the project at the purchase (L-18), PUR's posted value moved by it. */
    public function attributeNonStock(PurchaseLine $line, Project $project): void
    {
        $this->expect($this->run(CostPosting::purchaseLocks($line->purchase_id)->add('projects', ['id' => $project->id]), function (CommandContext $context) use ($line, $project): void {
            $this->posted($context, $line->id, app(CostPosting::class)->nonStockLine($context, $line->id, $project->id, BusinessDate::today()));
        }));
    }

    /** A drop-ship line of the purchase for a demand line of the project, its guard born. */
    public function dropshipLine(Purchase $purchase, Project $project, string $qty, string $value, int $lineNo = 0): PurchaseLine
    {
        $demand = ProjectLine::factory()->create(['project_id' => $project->id, 'product_id' => $this->productId, 'fulfillment_mode' => 'DROPSHIP',
            'line_no' => DB::table('project_lines')->where('project_id', $project->id)->count() + 1]);
        $line = PurchaseLine::factory()->create([
            'purchase_id' => $purchase->id, 'product_id' => $this->productId, 'delivery_mode' => 'DROPSHIP', 'project_line_id' => $demand->id, 'entered_qty' => $qty, 'qty_base' => $qty,
            'unit_price' => Money::of($value)->share('1', $qty)->toString(), 'line_value' => $value, ...($lineNo > 0 ? ['line_no' => $lineNo] : []),
        ]);
        DB::table('purchase_line_balances')->insert(['purchase_line_id' => $line->id, 'ordered_qty_base' => $qty, 'goods_value' => $value]);

        return $line;
    }

    /** `qty` of the drop-ship line confirmed shipped to its project: the confirmation, its consumption guard, the line's confirmed quantity and its direct cost. */
    public function confirmDropship(PurchaseLine $line, string $qty): int
    {
        $confirmed = 0;
        $this->expect($this->run(CostPosting::purchaseLocks($line->purchase_id), function (CommandContext $context) use ($line, $qty, &$confirmed): void {
            $project = (int) DB::table('project_lines')->where('id', $line->project_line_id)->value('project_id');
            $confirmation = DropshipConfirmation::factory()->create(['company_id' => $line->company_id, 'project_id' => $project, 'purchase_id' => $line->purchase_id]);
            $confirmed = DropshipConfirmationLine::factory()->create(['dropship_confirmation_id' => $confirmation->id, 'purchase_line_id' => $line->id,
                'project_line_id' => $line->project_line_id, 'product_id' => $this->productId, 'entered_qty' => $qty, 'qty_base' => $qty])->id;
            DB::table('stock_consumption_balances')->insert(['dropship_confirmation_line_id' => $confirmed, 'qty_base' => $qty]);
            $this->moveLine($context, $line->id, ['dropship_confirmed_net_qty_base' => $qty]);
            $this->posted($context, $line->id, app(CostPosting::class)->dropshipConfirmed($context, $confirmed, BusinessDate::today()));
        }));

        return $confirmed;
    }

    /** `qty` shipped by the supplier as the case's replacement on the drop-ship line (§5.7; PX-03): the confirmation carrying the basis the case gives it. */
    public function confirmReplacement(PurchaseLine $line, string $qty, int $case, string $basis): int
    {
        $confirmed = 0;
        $this->expect($this->run(CostPosting::purchaseLocks($line->purchase_id)->union(CorrectionCases::locks($case)), function (CommandContext $context) use ($line, $qty, $case, $basis, &$confirmed): void {
            $project = (int) DB::table('project_lines')->where('id', $line->project_line_id)->value('project_id');
            $confirmation = DropshipConfirmation::factory()->create(['company_id' => $line->company_id, 'project_id' => $project, 'purchase_id' => $line->purchase_id]);
            $confirmed = DropshipConfirmationLine::factory()->create(['dropship_confirmation_id' => $confirmation->id, 'purchase_line_id' => $line->id, 'project_line_id' => $line->project_line_id,
                'product_id' => $this->productId, 'entered_qty' => $qty, 'qty_base' => $qty, 'is_replacement' => true, 'correction_case_id' => $case])->id;
            DB::table('stock_consumption_balances')->insert(['dropship_confirmation_line_id' => $confirmed, 'qty_base' => $qty]);
            app(CostPosting::class)->replacementConfirmed($context, $confirmed, Money::of($basis), BusinessDate::today());
        }));

        return $confirmed;
    }

    /**
     * `qty` of a confirmed drop-ship line restored (§5.7): returned to the warehouse into a new DROPSHIP_RETURN lot (CM-14),
     * returned to the supplier under the case (CM-15), or reversed as never confirmed (CM-27) — the restoration, the
     * return lot with its movement, the line's balance and, for a reversal, the line's confirmed quantity and posted values.
     */
    public function restoreDropship(int $confirmed, string $qty, string $kind, ?int $case = null): int
    {
        $row = DropshipConfirmationLine::query()->findOrFail($confirmed);
        $purchaseLine = PurchaseLine::query()->findOrFail($row->purchase_line_id);
        $locks = CostPosting::purchaseLocks($purchaseLine->purchase_id);
        $restoration = 0;
        $this->expect($this->run($case === null ? $locks : $locks->union(CorrectionCases::locks($case)), function (CommandContext $context) use ($row, $purchaseLine, $qty, $kind, $case, &$restoration): void {
            $returns = $kind === 'DROPSHIP_RETURN';
            $restoration = StockRestoration::factory()->create([
                'stock_consumption_id' => null, 'dropship_confirmation_line_id' => $row->id, 'source_company_id' => $row->company_id, 'product_id' => $this->productId, 'quantity_scale' => 0,
                'stock_movement_id' => $returns ? StockMovementFactory::post('DROPSHIP_RETURN_RECEIPT', $row->company_id) : null, 'returned_condition' => $returns ? 'USABLE' : null,
                'entered_qty' => $qty, 'qty_base' => $qty, 'cited_unit_id' => $row->entered_unit_id, 'cited_ratio_to_base' => 1, 'kind' => $kind,
                'after_delivery' => $kind === 'DROPSHIP_CONFIRMATION_REVERSAL' ? null : true,
                'correction_case_id' => $case, 'case_company_id' => $case === null ? null : $row->company_id,
            ])->id;

            if ($returns) {
                $lot = InventoryLot::factory()->create(['acquisition_kind' => 'DROPSHIP_RETURN', 'import_row_id' => null, 'restoration_id' => $restoration, 'source_company_id' => $row->company_id,
                    'product_id' => $this->productId, 'entered_qty' => $qty, 'acquired_qty_base' => $qty, 'business_date' => BusinessDate::today()])->id;
                $this->entry($lot, (int) StockRestoration::query()->findOrFail($restoration)->stock_movement_id, 'DROPSHIP_RETURN_RECEIPT', $qty, ['stock_restoration_id' => $restoration]);
            }

            $posted = app(CostPosting::class)->dropshipRestored($context, $restoration, BusinessDate::today());
            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['dropship_confirmation_line_id' => $row->id], 'deltas' => ['restored_qty_base' => $qty]]]);

            if ($kind === 'DROPSHIP_CONFIRMATION_REVERSAL') {
                $this->moveLine($context, $purchaseLine->id, ['dropship_confirmed_net_qty_base' => '-'.$qty]);
                $this->posted($context, $purchaseLine->id, $posted);
            }
        }));

        return $restoration;
    }

    /**
     * `qty` of a drop-ship return lot's units taken back as never returned (§5.3; CM-37): the reversal of its return
     * receipt, the cost back on the confirmation line, and the line's restored quantity given back.
     */
    public function reverseDropshipReturn(int $lot, string $qty): void
    {
        $row = InventoryLot::query()->findOrFail($lot);
        $restoration = StockRestoration::query()->findOrFail($row->restoration_id);
        $purchase = (int) DB::table('dropship_confirmation_lines as d')->join('purchase_lines as p', 'p.id', '=', 'd.purchase_line_id')
            ->where('d.id', $restoration->dropship_confirmation_line_id)->value('p.purchase_id');
        $this->expect($this->run(CostPosting::purchaseLocks($purchase), function (CommandContext $context) use ($lot, $qty, $restoration): void {
            $movement = StockMovementFactory::new()->createOne(['movement_type' => 'MOVEMENT_REVERSAL', 'company_id' => $this->company->id,
                'reverses_movement_id' => $restoration->stock_movement_id, 'reverses_movement_type' => 'DROPSHIP_RETURN_RECEIPT'])->id;
            $this->entry($lot, $movement, 'MOVEMENT_REVERSAL', '-'.$qty);
            app(CostPosting::class)->reverseBasis($context, $lot, $qty, BusinessDate::today());
            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['dropship_confirmation_line_id' => $restoration->dropship_confirmation_line_id],
                'deltas' => ['restored_qty_base' => '-'.$qty]]]);
        }));
    }

    /** An opening lot of `qty` with its OPENING_COST from the signed opening data (BR-INV-12), its movement and its guard. */
    public function openingLot(string $qty, string $cost): int
    {
        $lot = 0;
        $this->expect($this->run(new LockSet, function (CommandContext $context) use ($qty, $cost, &$lot): void {
            $row = InventoryLot::factory()->create(['source_company_id' => $this->company->id, 'product_id' => $this->productId, 'entered_qty' => $qty, 'acquired_qty_base' => $qty]);
            $lot = $row->id;
            $this->entry($lot, StockMovementFactory::post('OPENING', $this->company->id), 'OPENING', $qty);
            app(CostPosting::class)->basis($context, $lot, 'OPENING_COST', Money::of($cost), BusinessDate::today(), ['import_row_id' => (int) $row->import_row_id]);
        }));

        return $lot;
    }

    /** `qty` of a basis lot's units removed as never acquired: their movement and the cost removed with them (BR-INV-12). */
    public function removeBasis(int $lot, string $qty): void
    {
        $this->expect($this->run((new LockSet)->add('lot_cost_balances', ['lot_id' => $lot]), function (CommandContext $context) use ($lot, $qty): void {
            $this->entry($lot, StockMovementFactory::post('ADJUSTMENT_DECREASE', $this->company->id), 'ADJUSTMENT_DECREASE', '-'.$qty);
            app(CostPosting::class)->reverseBasis($context, $lot, $qty, BusinessDate::today());
        }));
    }

    /** `qty` received on the line as the case's replacement (PX-03): its REPLACEMENT receipt and lot with the basis the case gives it (§6.1). */
    public function replacementLot(PurchaseLine $line, string $qty, int $case, string $basis): int
    {
        $lot = 0;
        $this->expect($this->run(CostPosting::purchaseLocks($line->purchase_id)->union(CorrectionCases::locks($case)), function (CommandContext $context) use ($line, $qty, $case, $basis, &$lot): void {
            $receiving = ReceivingLine::factory()->create(['purchase_line_id' => $line->id, 'entered_qty' => $qty, 'qty_base' => $qty, 'usable_qty_base' => $qty,
                'receipt_kind' => 'REPLACEMENT', 'correction_case_id' => $case]);
            $lot = InventoryLot::factory()->create([
                'acquisition_kind' => 'RECEIPT', 'import_row_id' => null, 'receiving_line_id' => $receiving->id, 'source_company_id' => $line->company_id,
                'product_id' => $this->productId, 'entered_qty' => $qty, 'acquired_qty_base' => $qty, 'business_date' => BusinessDate::today(),
            ])->id;
            $this->entry($lot, (int) $receiving->stock_movement_id, 'RECEIPT', $qty);
            $this->moveLine($context, $line->id, ['replacement_expected_qty_base' => $qty, 'replacement_received_net_qty_base' => $qty]);
            app(CostPosting::class)->basis($context, $lot, 'REPLACEMENT_BASIS', Money::of($basis), BusinessDate::today(), ['correction_case_id' => $case]);
        }));

        return $lot;
    }

    /** The case's unrecovered share of a supplier return settled on the bearer project — another company's with its reason (AX-11; L-44). */
    public function settleReturn(int $consumption, string $amount, Project $bearer, ?string $reason = null): void
    {
        $this->expect($this->run(CostPosting::consumptionLocks($consumption)->add('projects', ['id' => $bearer->id]), function (CommandContext $context) use ($consumption, $amount, $bearer, $reason): void {
            app(CostPosting::class)->settleReturn($context, $consumption, Money::of($amount), $bearer->company_id, $bearer->id, $reason, BusinessDate::today());
        }));
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
            // Numbered under the project's lock, so two sessions' dispatches never share a line number.
            $line = ProjectLine::factory()->create(['project_id' => $project->id, 'product_id' => $this->productId, 'line_no' => DB::table('project_lines')->where('project_id', $project->id)->count() + 1]);
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

    /** `qty` of the lot returned to its supplier under the case: its consumption, its SUPPLIER_RETURN draw, the case's returned cost. */
    public function purchaseReturn(int $lot, string $qty, int $case): int
    {
        $consumption = 0;
        $this->expect($this->run(CostPosting::lotLocks([$lot])->union(CorrectionCases::locks($case)), function (CommandContext $context) use ($lot, $qty, $case, &$consumption): void {
            $movement = StockMovementFactory::post('PURCHASE_RETURN', $this->company->id);
            $consumption = $this->consume($lot, $qty, 'PURCHASE_RETURN', $movement, [
                'bearer_company_id' => $this->company->id, 'correction_case_id' => $case, 'case_company_id' => $this->company->id,
            ]);
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
            // The reversal names the receipt it reverses (C-55).
            $receipt = (int) ReceivingLine::query()->findOrFail(InventoryLot::query()->findOrFail($lot)->receiving_line_id)->stock_movement_id;
            $movement = StockMovementFactory::new()->createOne(['movement_type' => 'RECEIPT_REVERSAL', 'company_id' => $this->company->id,
                'reverses_movement_id' => $receipt, 'reverses_movement_type' => 'RECEIPT'])->id;
            $this->entry($lot, $movement, 'RECEIPT_REVERSAL', '-'.$qty);
            // A replacement receipt counts against the line's replacement cap, never its normal received quantity (PX-03).
            $replacement = ReceivingLine::query()->findOrFail(InventoryLot::query()->findOrFail($lot)->receiving_line_id)->receipt_kind === 'REPLACEMENT';
            $this->moveLine($context, $line, [$replacement ? 'replacement_received_net_qty_base' : 'normal_received_net_qty_base' => '-'.$qty]);
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

    /**
     * The line's goods value corrected to `value` (AX-34; CM-17); `$derived`, when given, runs between the lock set's
     * derivation and its acquisition (§5 steps 1 and 2; M-07).
     */
    public function correctPrice(PurchaseLine $line, string $value, ?Closure $derived = null): Outcome
    {
        $locks = CostPosting::purchaseLocks($line->purchase_id);

        if ($derived !== null) {
            $derived();
        }

        return $this->run($locks, function (CommandContext $context) use ($line, $value): void {
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

    /**
     * The CST guards' differences from their facts (C-21; C-26; C-57; C-61) and C-28's overlay violations: none after
     * any scenario (criterion 16).
     *
     * @return list<mixed>
     */
    public static function differences(): array
    {
        $found = AllocationOverlay::violations();

        foreach (['lot_cost_balances', 'purchase_charge_balances', 'purchase_charge_share_balances', 'correction_cases', 'purchase_line_balances'] as $table) {
            $found = [...$found, ...(new Reconciler)->differences(DB::connection(), Families::named($table) ?? throw new RuntimeException($table))];
        }

        return $found;
    }

    /** @return list<string> the consumption's cost rows by class, cause and amount, in id order */
    public static function rows(int $consumption): array
    {
        return DB::table('cost_attributions')->where('stock_consumption_id', $consumption)->orderBy('id')->get(['cost_class', 'cause', 'amount'])
            ->map(fn (object $row): string => "{$row->cost_class} {$row->cause} {$row->amount}")->values()->all();
    }

    /** A posting run in the command envelope, holding the lock set given (CONCURRENCY_IDEMPOTENCY §5). */
    public function run(LockSet $locks, Closure $work): Outcome
    {
        $request = new CommandRequest((string) Str::uuid(), 'test.costing', $this->actorId, $this->company->id, 'projects.view', Fingerprint::of('test.costing', null, ['run' => (string) Str::uuid()]));

        return app(CommandEnvelope::class)->run($request, function (CommandContext $context) use ($locks, $work): CommandResult {
            $this->attempts++;
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
