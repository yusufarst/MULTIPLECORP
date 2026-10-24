<?php

use App\Modules\CAT\Models\Product;
use App\Modules\FUL\Models\Delivery;
use App\Modules\FUL\Models\DeliveryClosure;
use App\Modules\FUL\Models\DeliveryLine;
use App\Modules\FUL\Models\DropshipConfirmation;
use App\Modules\FUL\Models\DropshipConfirmationLine;
use App\Modules\FUL\Models\ServiceHandoverLine;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use Database\Factories\FUL\DropshipConfirmationFactory;
use Database\Factories\PRJ\ProjectLineFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-009), criterion 2 for DATABASE §4.9's FUL tables: a drop-ship confirmation's project and
// purchase are its company's and its shipment reference identifies it once per purchase (C-32; C-49), one
// per command (C-48); its lines hold their scale and conversion (C-09), one serial per row and a
// replacement's case (C-59); a delivery or closure cites one shipment of its company (C-32); a reversal
// line is exact and once (C-30); a closure decided in error is superseded once (C-59).

uses(RefreshDatabase::class);

it('confirms a drop-ship shipment once per purchase and command, of its company\'s project and purchase (C-32; C-48; C-49)', function () {
    $confirmation = DropshipConfirmation::factory()->create();
    $parts = DropshipConfirmationFactory::partsOf($confirmation);

    Refusal::assert(Refusal::UNIQUE, 'dropship_confirmations_c49_shipment', fn () => DropshipConfirmation::factory()->create([
        ...$parts, 'supplier_shipment_reference' => $confirmation->supplier_shipment_reference,
    ]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'dropship_confirmations_c32_project_foreign', fn () => DropshipConfirmation::factory()->create([...$parts, 'project_id' => Project::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'dropship_confirmations_c32_purchase_foreign', fn () => DropshipConfirmation::factory()->create([...$parts, 'purchase_id' => Purchase::factory()->create()->id]));
    $command = (string) Str::uuid7();
    DropshipConfirmation::factory()->create(['command_id' => $command, 'command_line_no' => 1]);
    Refusal::assert(Refusal::UNIQUE, 'dropship_confirmations_one_per_command', fn () => DropshipConfirmation::factory()->create(['command_id' => $command, 'command_line_no' => 2]));

    expect(DropshipConfirmation::factory()->create($parts)->purchase_id)->toBe($parts['purchase_id']);
});

it('keeps a confirmed line at its ratio and scale, one serial per row, a replacement with its case, all of its company (C-09; C-32; C-59; §8)', function (Closure $attributes, string $constraint, string $state = Refusal::CHECK) {
    $line = DropshipConfirmationLine::factory()->create();

    Refusal::assert($state, $constraint, fn () => DropshipConfirmationLine::factory()->create(['dropship_confirmation_id' => $line->dropship_confirmation_id, ...$attributes($line)]));
})->with([
    'a quantity off its conversion' => [fn () => ['qty_base' => '3.000'], 'dropship_confirmation_lines_c09_conversion'],
    'a fraction of a counted product' => [fn () => ['entered_qty' => '0.500', 'qty_base' => '0.500'], 'dropship_confirmation_lines_c09_scale'],
    'two pieces on one serial' => [fn () => ['serial_unit_id' => SerialUnit::factory()->create()->id], 'dropship_confirmation_lines_c02_serial_one'],
    'a replacement without its case' => [fn () => ['is_replacement' => true], 'dropship_confirmation_lines_c59_replacement_case'],
    'a case without the replacement' => [fn (DropshipConfirmationLine $line) => ['correction_case_id' => CorrectionCase::factory()->create(['company_id' => $line->company_id])->id], 'dropship_confirmation_lines_c59_replacement_case'],
    'another company\'s case' => [fn () => ['is_replacement' => true, 'correction_case_id' => CorrectionCase::factory()->create()->id], 'dropship_confirmation_lines_c32_correction_case_foreign', Refusal::FOREIGN_KEY],
    'another company\'s purchase line of the product' => [fn (DropshipConfirmationLine $line) => ['product_id' => $line->product_id, 'project_line_id' => $line->project_line_id,
        'purchase_line_id' => PurchaseLine::factory()->create(['product_id' => $line->product_id])->id], 'dropship_confirmation_lines_c09_purchase_line_foreign', Refusal::FOREIGN_KEY],
    'another product than its demand line\'s' => [fn (DropshipConfirmationLine $line) => ['project_line_id' => $line->project_line_id, 'purchase_line_id' => $line->purchase_line_id, 'product_id' => Product::factory()->create()->id], 'dropship_confirmation_lines_c09_purchase_line_foreign', Refusal::FOREIGN_KEY],
]);

it('delivers from one shipment of its company, a reversal exact and once (C-09; C-30; C-32; CM-08)', function () {
    $line = DeliveryLine::factory()->create();
    $dispatch = DispatchLine::query()->whereKey($line->dispatch_line_id)->firstOrFail();
    $own = ['delivery_id' => $line->delivery_id, 'project_line_id' => $line->project_line_id, 'dispatch_line_id' => $dispatch->id];

    Refusal::assert(Refusal::CHECK, 'delivery_lines_one_shipment', fn () => DeliveryLine::factory()->create([...$own, 'dispatch_line_id' => null]));
    Refusal::assert(Refusal::CHECK, 'delivery_lines_one_shipment', fn () => DeliveryLine::factory()->create([...$own, 'dropship_confirmation_line_id' => DropshipConfirmationLine::factory()->create(['dropship_confirmation_id' => DropshipConfirmation::factory()->create(['company_id' => $line->company_id])->id])->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'delivery_lines_c32_dispatch_line_foreign', fn () => DeliveryLine::factory()->create([...$own, 'dispatch_line_id' => DispatchLine::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'delivery_lines_c09_conversion', fn () => DeliveryLine::factory()->create([...$own, 'qty_base' => '3.000']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'delivery_lines_c30_reverses_foreign', fn () => DeliveryLine::factory()->create([...$own, 'reverses_delivery_line_id' => $line->id, 'entered_qty' => '1.000', 'qty_base' => '1.000']));

    DeliveryLine::factory()->create([...$own, 'reverses_delivery_line_id' => $line->id]);

    Refusal::assert(Refusal::UNIQUE, 'delivery_lines_c30_reversed_once', fn () => DeliveryLine::factory()->create([...$own, 'reverses_delivery_line_id' => $line->id]));

    expect(DeliveryLine::query()->where('reverses_delivery_line_id', $line->id)->count())->toBe(1)
        ->and(Delivery::query()->whereKey($line->delivery_id)->firstOrFail()->company_id)->toBe($line->company_id);
});

it('closes a shipped quantity of one shipment, superseded once within its line (C-09; C-32; C-59; CM-06/07)', function () {
    $closure = DeliveryClosure::factory()->create();
    $own = ['dispatch_line_id' => $closure->dispatch_line_id];

    Refusal::assert(Refusal::CHECK, 'delivery_closures_one_shipment', fn () => DeliveryClosure::factory()->create([...$own, 'dropship_confirmation_line_id' => DropshipConfirmationLine::factory()->create(['dropship_confirmation_id' => DropshipConfirmation::factory()->create(['company_id' => $closure->company_id])->id])->id]));
    Refusal::assert(Refusal::CHECK, 'delivery_closures_kind_code', fn () => DeliveryClosure::factory()->create([...$own, 'closure_kind' => 'DAMAGED']));
    Refusal::assert(Refusal::CHECK, 'delivery_closures_c09_quantity', fn () => DeliveryClosure::factory()->create([...$own, 'closed_qty_base' => '0.500']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'delivery_closures_c32_correction_case_foreign', fn () => DeliveryClosure::factory()->create([...$own, 'correction_case_id' => CorrectionCase::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'delivery_closures_c59_supersedes_foreign', fn () => DeliveryClosure::factory()->create(['supersedes_id' => $closure->id]));

    DeliveryClosure::factory()->create([...$own, 'supersedes_id' => $closure->id]);

    Refusal::assert(Refusal::UNIQUE, 'delivery_closures_c59_linear_chain', fn () => DeliveryClosure::factory()->create([...$own, 'supersedes_id' => $closure->id]));

    expect(DeliveryClosure::query()->where('project_line_id', $closure->project_line_id)->count())->toBe(2);
});

it('hands a service over within its company, a reversal exact and once (C-09; C-30; C-32; L-39)', function () {
    $line = ServiceHandoverLine::factory()->create();
    $own = ['service_handover_id' => $line->service_handover_id, 'project_line_id' => $line->project_line_id];

    Refusal::assert(Refusal::FOREIGN_KEY, 'service_handover_lines_c09_project_line_foreign', fn () => ServiceHandoverLine::factory()->create([...$own, 'project_line_id' => ProjectLineFactory::newOn(Project::factory()->create()->id)]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'service_handover_lines_c32_handover_foreign', fn () => ServiceHandoverLine::factory()->create([...$own, 'service_handover_id' => 999_999, 'company_id' => $line->company_id]));
    Refusal::assert(Refusal::CHECK, 'service_handover_lines_c09_scale', fn () => ServiceHandoverLine::factory()->create([...$own, 'entered_qty' => '0.000', 'qty_base' => '0.000']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'service_handover_lines_c30_reverses_foreign', fn () => ServiceHandoverLine::factory()->create([...$own, 'reverses_line_id' => $line->id, 'entered_qty' => '2.000', 'qty_base' => '2.000']));

    ServiceHandoverLine::factory()->create([...$own, 'reverses_line_id' => $line->id]);

    Refusal::assert(Refusal::UNIQUE, 'service_handover_lines_c30_reversed_once', fn () => ServiceHandoverLine::factory()->create([...$own, 'reverses_line_id' => $line->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'deliveries_c32_project_foreign', fn () => Delivery::factory()->create(['company_id' => Company::factory()->create()->id, 'project_id' => Project::factory()->create()->id]));

    expect(DB::table('service_handover_lines')->where('reverses_line_id', $line->id)->count())->toBe(1);
});
