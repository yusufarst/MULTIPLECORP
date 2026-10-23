<?php

use App\Modules\CAT\Models\Product;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\Location;
use App\Modules\INV\Models\ReceivingLine;
use App\Modules\INV\Models\Reservation;
use App\Modules\INV\Models\ReservationEvent;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\INV\Models\StockMovement;
use App\Modules\INV\Models\StockMovementEntry;
use App\Modules\INV\Models\UnattributedLossCase;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use App\Modules\PUR\Models\PurchaseLine;
use Database\Factories\INV\StockMovementFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-008), criteria 1, 2 and 4: the INV rows of DATABASE §18 on the ledger and current stock
// (§4.7, §5) — the sign discipline and net-zero groups of each movement type (C-07), one lot per
// acquisition keyed to its cause (C-08), exact conversions at the product's scale (C-09), one identity per
// serial (C-02), non-negative lot and scope quantities with every pending reduction backed (C-03),
// AVAILABLE ≥ 0 (C-04), the reservation bounds (C-05) and their cuts (C-06), receipts' corrections and
// identity (C-49, C-59), reversals by type (C-55), the company of every command (C-32, C-48) and a
// purchase line's first effect by receipt (DIR-090 (5); L-23).

uses(RefreshDatabase::class);

/**
 * An entry of the movement on the lot.
 *
 * @param  array<string, mixed>  $attributes
 */
function ledgerEntry(InventoryLot $lot, int $movement, string $type, string $quantity, array $attributes = []): StockMovementEntry
{
    return StockMovementEntry::factory()->create([
        'lot_id' => $lot->id, 'product_id' => $lot->product_id, 'quantity_scale' => $lot->quantity_scale,
        'stock_movement_id' => $movement, 'movement_type' => $type, 'qty_delta_base' => $quantity, ...$attributes,
    ]);
}

/** Runs the movement's deferred balance check now, as its commit would. */
function atCommit(): void
{
    DB::statement('set constraints stock_movement_entries_c07_movement_balanced immediate');
}

it('lets each movement type only add or only subtract, at the product\'s scale, lot-less only for a pending case (C-07; C-09; §5.3)', function (string $type, string $quantity, string $constraint, array $attributes = []) {
    $lot = InventoryLot::factory()->create();
    $movement = StockMovementFactory::post($type, $lot->source_company_id);

    Refusal::assert(Refusal::CHECK, $constraint, fn () => ledgerEntry($lot, $movement, $type, $quantity, $attributes));
})->with([
    'a receipt that subtracts' => ['RECEIPT', '-1.000', 'stock_movement_entries_c07_adds'],
    'an opening that subtracts' => ['OPENING', '-1.000', 'stock_movement_entries_c07_adds'],
    'a dispatch that adds' => ['DISPATCH', '1.000', 'stock_movement_entries_c07_subtracts'],
    'a loss that adds' => ['LOSS', '1.000', 'stock_movement_entries_c07_subtracts'],
    'an entry of nothing' => ['LOCATION_MOVE', '0.000', 'stock_movement_entries_c09_quantity'],
    'a fraction of a counted product' => ['OPENING', '0.500', 'stock_movement_entries_c09_quantity'],
    'a lot-less dispatch' => ['DISPATCH', '-1.000', 'stock_movement_entries_c07_lot_less_types', ['lot_id' => null, 'unattributed_case_id' => 999_999]],
    'a lot and a case' => ['OPENING', '1.000', 'stock_movement_entries_c07_lot_or_case', ['unattributed_case_id' => 999_999]],
]);

it('nets a location move to zero per lot and condition, and a condition change per lot and location, at commit (C-07; L-36)', function () {
    $lot = InventoryLot::factory()->create();
    [$from, $to] = [Location::factory()->create()->id, Location::factory()->create()->id];

    Refusal::assert(Refusal::CHECK, 'stock_movement_entries_c07_movement_balanced', function () use ($lot, $from, $to): void {
        $move = StockMovementFactory::post('LOCATION_MOVE');
        ledgerEntry($lot, $move, 'LOCATION_MOVE', '-5.000', ['location_id' => $from]);
        ledgerEntry($lot, $move, 'LOCATION_MOVE', '6.000', ['location_id' => $to]);
        atCommit();
    });
    Refusal::assert(Refusal::CHECK, 'stock_movement_entries_c07_movement_balanced', function () use ($lot, $from): void {
        $change = StockMovementFactory::post('CONDITION_CHANGE');
        ledgerEntry($lot, $change, 'CONDITION_CHANGE', '-2.000', ['location_id' => $from]);
        ledgerEntry($lot, $change, 'CONDITION_CHANGE', '1.000', ['location_id' => $from, 'condition' => 'UNUSABLE']);
        atCommit();
    });

    $move = StockMovementFactory::post('LOCATION_MOVE');
    ledgerEntry($lot, $move, 'LOCATION_MOVE', '-5.000', ['location_id' => $from]);
    ledgerEntry($lot, $move, 'LOCATION_MOVE', '5.000', ['location_id' => $to]);
    atCommit();

    expect(StockMovementEntry::query()->where('stock_movement_id', $move)->sum('qty_delta_base'))->toEqual(0);
});

it('reverses a movement only with the opposite of its net at each key, and only a reversible type (C-55; C-07; §5.3)', function () {
    $lot = InventoryLot::factory()->create();
    $location = Location::factory()->create()->id;
    $change = StockMovementFactory::post('CONDITION_CHANGE');
    ledgerEntry($lot, $change, 'CONDITION_CHANGE', '-2.000', ['location_id' => $location]);
    ledgerEntry($lot, $change, 'CONDITION_CHANGE', '2.000', ['location_id' => $location, 'condition' => 'UNUSABLE']);
    $reverse = fn (): int => StockMovement::factory()->create(['movement_type' => 'MOVEMENT_REVERSAL', 'reverses_movement_id' => $change, 'reverses_movement_type' => 'CONDITION_CHANGE'])->id;

    Refusal::assert(Refusal::CHECK, 'stock_movement_entries_c07_movement_balanced', function () use ($lot, $location, $reverse): void {
        ledgerEntry($lot, $reverse(), 'MOVEMENT_REVERSAL', '-1.000', ['location_id' => $location]);
        atCommit();
    });
    Refusal::assert(Refusal::CHECK, 'stock_movements_c55_movement_reversal_types', fn () => StockMovement::factory()->create(['movement_type' => 'MOVEMENT_REVERSAL', 'reverses_movement_id' => StockMovementFactory::post('DISPATCH', $lot->source_company_id), 'reverses_movement_type' => 'DISPATCH']));
    Refusal::assert(Refusal::CHECK, 'stock_movements_c55_receipt_reversal_type', fn () => StockMovement::factory()->create(['movement_type' => 'RECEIPT_REVERSAL', 'reverses_movement_id' => $change, 'reverses_movement_type' => 'CONDITION_CHANGE']));
    Refusal::assert(Refusal::CHECK, 'stock_movements_c55_reversal_by_type', fn () => StockMovement::factory()->create(['movement_type' => 'RECEIPT_REVERSAL']));
    Refusal::assert(Refusal::CHECK, 'stock_movements_c55_reversal_pair', fn () => StockMovement::factory()->create(['reverses_movement_type' => 'RECEIPT']));
    // The typed pair names the reversed movement's own type.
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_movements_reverses_foreign', fn () => StockMovement::factory()->create(['movement_type' => 'MOVEMENT_REVERSAL', 'reverses_movement_id' => $change, 'reverses_movement_type' => 'OPENING']));

    $reversal = $reverse();
    ledgerEntry($lot, $reversal, 'MOVEMENT_REVERSAL', '2.000', ['location_id' => $location]);
    ledgerEntry($lot, $reversal, 'MOVEMENT_REVERSAL', '-2.000', ['location_id' => $location, 'condition' => 'UNUSABLE']);
    atCommit();

    expect(StockMovementEntry::query()->where('stock_movement_id', $reversal)->count())->toBe(2);
});

it('signs a case\'s lot-less entries by its kind, a clearing entry opposing its recognition (C-07; §5.3; §5.8)', function () {
    $lot = InventoryLot::factory()->create();
    $location = Location::factory()->create()->id;
    $case = fn (string $kind): UnattributedLossCase => UnattributedLossCase::factory()->create(['case_kind' => $kind, 'product_id' => $lot->product_id, 'location_id' => $location]);
    [$loss, $changed, $successor] = [$case('LOSS'), $case('CONDITION_CHANGE'), $case('LOSS')];
    $movement = fn (string $type, array $attributes = []): int => StockMovement::factory()->create(['movement_type' => $type, ...$attributes])->id;
    $resolution = $movement('UNATTRIBUTED_RESOLUTION');
    $reversal = ['reverses_movement_id' => $resolution, 'reverses_movement_type' => 'UNATTRIBUTED_RESOLUTION'];
    // A movement's lot-less entries, [case, condition, quantity], and its entries on the lot, [condition, quantity].
    $write = function (int $movement, string $type, array $caseEntries, array $lotEntries = []) use ($lot, $location): void {
        foreach ($caseEntries as [$of, $condition, $quantity]) {
            StockMovementEntry::factory()->create([
                'lot_id' => null, 'unattributed_case_id' => $of->id, 'product_id' => $of->product_id, 'quantity_scale' => $of->quantity_scale,
                'location_id' => $location, 'condition' => $condition, 'stock_movement_id' => $movement, 'movement_type' => $type, 'qty_delta_base' => $quantity,
            ]);
        }
        foreach ($lotEntries as [$condition, $quantity]) {
            ledgerEntry($lot, $movement, $type, $quantity, ['location_id' => $location, 'condition' => $condition]);
        }
        atCommit();
        DB::statement('set constraints stock_movement_entries_c07_movement_balanced deferred');
    };
    $refused = fn (Closure $write) => Refusal::assert(Refusal::CHECK, 'stock_movement_entries_c07_movement_balanced', $write);

    $refused(fn () => $write($movement('UNATTRIBUTED_RECOGNITION'), 'UNATTRIBUTED_RECOGNITION', [[$loss, 'UNUSABLE', '-1.000']]));
    $refused(fn () => $write($movement('UNATTRIBUTED_RECOGNITION', ['unattributed_case_id' => $changed->id]), 'UNATTRIBUTED_RECOGNITION', [[$changed, 'USABLE', '1.000'], [$changed, 'UNUSABLE', '-1.000']]));
    // A resolution that deepens the pending loss and adds to the lot, net zero.
    $refused(fn () => $write($movement('UNATTRIBUTED_RESOLUTION'), 'UNATTRIBUTED_RESOLUTION', [[$loss, 'USABLE', '-1.000']], [['USABLE', '1.000']]));
    $refused(fn () => $write($movement('UNATTRIBUTED_RESOLUTION_REVERSAL', $reversal), 'UNATTRIBUTED_RESOLUTION_REVERSAL', [[$loss, 'USABLE', '1.000']], [['USABLE', '-1.000']]));
    // A conversion with its old and new case's usable entries swapped.
    $refused(fn () => $write($movement('UNATTRIBUTED_CONVERSION', ['unattributed_case_id' => $successor->id]), 'UNATTRIBUTED_CONVERSION', [[$changed, 'USABLE', '-1.000'], [$changed, 'UNUSABLE', '-1.000'], [$successor, 'USABLE', '1.000']]));

    $write($movement('UNATTRIBUTED_RECOGNITION', ['unattributed_case_id' => $changed->id]), 'UNATTRIBUTED_RECOGNITION', [[$changed, 'USABLE', '-1.000'], [$changed, 'UNUSABLE', '1.000']]);
    $write($resolution, 'UNATTRIBUTED_RESOLUTION', [[$loss, 'USABLE', '1.000']], [['USABLE', '-1.000']]);
    $write($movement('UNATTRIBUTED_RESOLUTION_REVERSAL', $reversal), 'UNATTRIBUTED_RESOLUTION_REVERSAL', [[$loss, 'USABLE', '-1.000']], [['USABLE', '1.000']]);
    $write($movement('UNATTRIBUTED_CONVERSION', ['unattributed_case_id' => $successor->id]), 'UNATTRIBUTED_CONVERSION', [[$changed, 'USABLE', '1.000'], [$changed, 'UNUSABLE', '-1.000'], [$successor, 'USABLE', '-1.000']]);

    expect(StockMovementEntry::query()->whereNotNull('unattributed_case_id')->count())->toBe(7);
});

it('caps what reversals undo of a movement at a key, and reverses a resolution once (C-55; C-37)', function () {
    $lot = InventoryLot::factory()->create();
    $movement = StockMovementFactory::post('OPENING', $lot->source_company_id);
    $key = ['reversed_movement_id' => $movement, 'lot_id' => $lot->id, 'location_id' => Location::factory()->create()->id, 'condition' => 'USABLE'];
    DB::table('stock_reversal_balances')->insert([...$key, 'original_qty_base' => 5]);

    Refusal::assert(Refusal::CHECK, 'stock_reversal_balances_c55_cap', fn () => DB::table('stock_reversal_balances')->where($key)->update(['reversed_qty_base' => 6]));

    $resolution = StockMovementFactory::post('UNATTRIBUTED_RESOLUTION');
    $reversal = ['movement_type' => 'UNATTRIBUTED_RESOLUTION_REVERSAL', 'reverses_movement_id' => $resolution, 'reverses_movement_type' => 'UNATTRIBUTED_RESOLUTION'];
    StockMovement::factory()->create($reversal);

    Refusal::assert(Refusal::UNIQUE, 'stock_movements_c37_one_resolution_reversal', fn () => StockMovement::factory()->create($reversal));
});

it('keys a movement\'s project, purchase and case in its company, one movement per command line (C-32; C-48; §1)', function () {
    $company = Company::factory()->create()->id;
    $command = (string) Str::uuid7();
    StockMovement::factory()->create(['company_id' => $company, 'command_id' => $command, 'command_line_no' => 1]);

    Refusal::assert(Refusal::CHECK, 'stock_movements_c32_company_members', fn () => StockMovement::factory()->create(['project_id' => Project::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_movements_c32_project_foreign', fn () => StockMovement::factory()->create(['company_id' => $company, 'project_id' => Project::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_movements_c32_correction_case_foreign', fn () => StockMovement::factory()->create(['company_id' => $company, 'correction_case_id' => CorrectionCase::factory()->create()->id]));
    Refusal::assert(Refusal::UNIQUE, 'stock_movements_c48_command_identity', fn () => StockMovement::factory()->create(['company_id' => $company, 'command_id' => $command, 'command_line_no' => 1]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table stock_movements', fn () => DB::table('stock_movements')->where('company_id', $company)->update(['reason' => 'Diubah']));

    expect(StockMovement::factory()->create(['company_id' => $company, 'command_id' => $command, 'command_line_no' => 2])->exists)->toBeTrue();
});

it('creates one lot per acquisition, its source company keyed to the acquisition that created it (C-08; C-28; BR-INV-04)', function () {
    $row = ImportRow::factory()->create(['company_id' => Company::factory()->create()->id]);
    $opening = ['acquisition_kind' => 'OPENING', 'import_row_id' => $row->id, 'source_company_id' => $row->company_id];
    InventoryLot::factory()->create($opening);
    $receipt = ReceivingLine::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'inventory_lots_import_row_id_unique', fn () => InventoryLot::factory()->create($opening));
    Refusal::assert(Refusal::FOREIGN_KEY, 'inventory_lots_c08_import_row_foreign', fn () => InventoryLot::factory()->create([...$opening, 'import_row_id' => ImportRow::factory()->create(['company_id' => Company::factory()->create()->id])->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'inventory_lots_c08_receiving_line_foreign', fn () => InventoryLot::factory()->create(['acquisition_kind' => 'RECEIPT', 'import_row_id' => null, 'receiving_line_id' => $receipt->id]));
    Refusal::assert(Refusal::CHECK, 'inventory_lots_c08_source_by_kind', fn () => InventoryLot::factory()->create(['acquisition_kind' => 'RECEIPT']));
    Refusal::assert(Refusal::CHECK, 'inventory_lots_c08_source_by_kind', fn () => InventoryLot::factory()->create(['receiving_line_id' => $receipt->id, 'source_company_id' => $receipt->company_id]));
    Refusal::assert(Refusal::CHECK, 'inventory_lots_c09_conversion', fn () => InventoryLot::factory()->create(['entered_qty' => '2.000', 'ratio_to_base' => 12, 'acquired_qty_base' => '20.000']));
    Refusal::assert(Refusal::CHECK, 'inventory_lots_c09_scale', fn () => InventoryLot::factory()->create(['entered_qty' => '0.500', 'acquired_qty_base' => '0.500']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table inventory_lots', fn () => DB::table('inventory_lots')->where('import_row_id', $row->id)->update(['source_company_id' => $receipt->company_id]));

    expect(InventoryLot::factory()->create(['acquisition_kind' => 'RECEIPT', 'import_row_id' => null, 'receiving_line_id' => $receipt->id, 'source_company_id' => $receipt->company_id])->receivingLine?->is($receipt))->toBeTrue();
});

it('keeps one identity per serial, in stock only with its lot, location and condition, moved one at a time and once per sign (C-02; §8)', function () {
    $serial = SerialUnit::factory()->create();
    $lot = InventoryLot::factory()->create(['product_id' => $serial->product_id]);
    $location = Location::factory()->create()->id;
    $row = fn () => DB::table('serial_units')->where('id', $serial->id);
    $move = StockMovementFactory::post('LOCATION_MOVE');
    $scan = ['serial_unit_id' => $serial->id, 'location_id' => $location];

    Refusal::assert(Refusal::UNIQUE, 'serial_units_c02_one_identity', fn () => SerialUnit::factory()->create(['product_id' => $serial->product_id, 'serial_key' => $serial->serial_key]));
    Refusal::assert(Refusal::CHECK, 'serial_units_c02_in_stock', fn () => $row()->update(['state' => 'IN_STOCK']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'serial_units_current_lot_foreign', fn () => $row()->update(['state' => 'IN_STOCK', 'current_lot_id' => InventoryLot::factory()->create()->id, 'current_location_id' => $location, 'current_condition' => 'USABLE']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table serial_units', fn () => $row()->update(['serial_number' => 'SN-LAIN']));
    Refusal::assert(Refusal::CHECK, 'stock_movement_entries_c02_serial_one', fn () => ledgerEntry($lot, $move, 'LOCATION_MOVE', '2.000', $scan));

    ledgerEntry($lot, $move, 'LOCATION_MOVE', '1.000', $scan);

    Refusal::assert(Refusal::UNIQUE, 'stock_movement_entries_c02_serial_once', fn () => ledgerEntry($lot, $move, 'LOCATION_MOVE', '1.000', $scan));

    // The opposite sign in the same movement is the other half of its move.
    ledgerEntry($lot, $move, 'LOCATION_MOVE', '-1.000', [...$scan, 'location_id' => Location::factory()->create()->id]);
    $row()->update(['state' => 'IN_STOCK', 'current_lot_id' => $lot->id, 'current_location_id' => $location, 'current_condition' => 'USABLE']);

    expect($serial->refresh()->state)->toBe('IN_STOCK');
});

it('keeps AVAILABLE, lot and scope quantities from going below zero, every pending reduction backed by claims (C-03; C-04; §5.2)', function () {
    $product = Product::factory()->create()->id;
    $lot = InventoryLot::factory()->create(['product_id' => $product]);
    $location = Location::factory()->create()->id;
    DB::table('stock_product_balances')->insert(['product_id' => $product, 'on_hand_qty' => 2, 'reserved_qty' => 1, 'unusable_qty' => 1]);
    DB::table('stock_lot_balances')->insert(['lot_id' => $lot->id, 'location_id' => $location, 'condition' => 'USABLE', 'product_id' => $product, 'qty' => 2]);
    DB::table('stock_scope_balances')->insert(['product_id' => $product, 'location_id' => $location, 'condition' => 'USABLE', 'claims_qty' => 2, 'pending_reduction_qty' => 1]);
    $guard = fn () => DB::table('stock_product_balances')->where('product_id', $product);

    // The Owner's race resolved: a dispatch of the last available unit would leave RESERVED + UNUSABLE above ON HAND.
    Refusal::assert(Refusal::CHECK, 'stock_product_balances_c04_available', fn () => $guard()->update(['on_hand_qty' => 1]));
    Refusal::assert(Refusal::CHECK, 'stock_product_balances_c04_available', fn () => $guard()->update(['reserved_qty' => 2]));
    Refusal::assert(Refusal::CHECK, 'stock_lot_balances_c03_not_negative', fn () => DB::table('stock_lot_balances')->where('lot_id', $lot->id)->update(['qty' => -1]));
    Refusal::assert(Refusal::CHECK, 'stock_scope_balances_c03_claims', fn () => DB::table('stock_scope_balances')->where('product_id', $product)->update(['claims_qty' => '0.500']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_lot_balances_lot_foreign', fn () => DB::table('stock_lot_balances')->insert(['lot_id' => $lot->id, 'location_id' => $location, 'condition' => 'UNUSABLE', 'product_id' => Product::factory()->create()->id]));

    expect(DB::table('stock_scope_balances')->where('product_id', $product)->value('qty'))->toBe('1.000');
});

it('receives a purchase line of its company in three outcomes, a correction citing a receipt of the same line, the delivery note once (C-09; C-32; C-49; C-59; L-13)', function (Closure $write, string $constraint, string $state = Refusal::CHECK) {
    Refusal::assert($state, $constraint, $write);
})->with([
    'outcomes not summing to the receipt' => [fn () => ReceivingLine::factory()->create(['usable_qty_base' => '9.000']), 'receiving_lines_outcomes'],
    'a fraction of a counted product' => [fn () => ReceivingLine::factory()->create(['entered_qty' => '9.500', 'qty_base' => '9.500', 'usable_qty_base' => '9.500']), 'receiving_lines_c09_scale'],
    'a supplement citing nothing' => [fn () => ReceivingLine::factory()->create(['receipt_kind' => 'SUPPLEMENT']), 'receiving_lines_c59_correction_by_kind'],
    'a replacement without its case' => [fn () => ReceivingLine::factory()->create(['receipt_kind' => 'REPLACEMENT']), 'receiving_lines_replacement_case'],
    'a correction of another line\'s receipt' => [fn () => ReceivingLine::factory()->create(['receipt_kind' => 'SUPPLEMENT', 'corrects_receiving_line_id' => ReceivingLine::factory()->create()->id]), 'receiving_lines_c59_corrects_foreign', Refusal::FOREIGN_KEY],
    'another company\'s movement' => [fn () => ReceivingLine::factory()->create(['stock_movement_id' => StockMovementFactory::post('RECEIPT', Company::factory()->create()->id)]), 'receiving_lines_c32_movement_foreign', Refusal::FOREIGN_KEY],
    'another product than its line\'s' => [fn () => ReceivingLine::factory()->create(['product_id' => Product::factory()->create()->id]), 'receiving_lines_c09_purchase_line_foreign', Refusal::FOREIGN_KEY],
]);

it('identifies an original delivery once per line, its corrections citing it instead, a re-receipt only after the reversal (C-49; C-59; CM-02/04)', function () {
    $original = ReceivingLine::factory()->create();
    $same = ['purchase_line_id' => $original->purchase_line_id, 'supplier_delivery_reference' => $original->supplier_delivery_reference];

    Refusal::assert(Refusal::UNIQUE, 'receiving_lines_c49_original_delivery', fn () => ReceivingLine::factory()->create($same));
    Refusal::assert(Refusal::CHECK, 'receiving_lines_c59_re_receipt_after_reversal', fn () => ReceivingLine::factory()->create([...$same, 'receipt_kind' => 'RE_RECEIPT', 'corrects_receiving_line_id' => $original->id]));

    expect(ReceivingLine::factory()->create([...$same, 'receipt_kind' => 'SUPPLEMENT', 'corrects_receiving_line_id' => $original->id])->corrects?->is($original))->toBeTrue();
});

it('makes a receipt, an all-rejected one too, a purchase line\'s first effect (DIR-090 (5); L-23)', function (array $outcome) {
    $line = PurchaseLine::factory()->create();
    ReceivingLine::factory()->create(['purchase_line_id' => $line->id, ...$outcome]);

    Refusal::assert(Refusal::CHECK, 'purchase_lines_draft_until_first_effect', fn () => DB::table('purchase_lines')->where('id', $line->id)->update(['unit_price' => '1.00']));
})->with([
    'a usable receipt' => [[]],
    'an all-rejected receipt' => [['usable_qty_base' => '0.000', 'rejected_qty_base' => '10.000']],
]);

it('dispatches a line of its project and company, its reserved part from its own line\'s reservation (C-05; C-09; C-32; WF-INV-03)', function () {
    $line = ProjectLine::factory()->create();
    $reservation = Reservation::factory()->create(['project_line_id' => $line->id]);
    $own = ['project_line_id' => $line->id];

    Refusal::assert(Refusal::CHECK, 'dispatch_lines_c05_reserved_parts', fn () => DispatchLine::factory()->create([...$own, 'reserved_qty_base' => '1.000', 'reservation_id' => $reservation->id]));
    Refusal::assert(Refusal::CHECK, 'dispatch_lines_c05_reservation_when_reserved', fn () => DispatchLine::factory()->create([...$own, 'reserved_qty_base' => '2.000', 'unreserved_qty_base' => '0.000']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'dispatch_lines_c05_reservation_foreign', fn () => DispatchLine::factory()->create([...$own, 'reserved_qty_base' => '2.000', 'unreserved_qty_base' => '0.000', 'reservation_id' => Reservation::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'dispatch_lines_c32_line_foreign', fn () => DispatchLine::factory()->create([...$own, 'project_id' => Project::factory()->create(['company_id' => $line->company_id])->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'dispatch_lines_c32_movement_foreign', fn () => DispatchLine::factory()->create([...$own, 'stock_movement_id' => StockMovementFactory::post('DISPATCH', Company::factory()->create()->id)]));
    Refusal::assert(Refusal::CHECK, 'dispatch_lines_c09_conversion', fn () => DispatchLine::factory()->create([...$own, 'entered_qty' => '1.000', 'ratio_to_base' => 12]));

    expect(DispatchLine::factory()->create([...$own, 'reserved_qty_base' => '2.000', 'unreserved_qty_base' => '0.000', 'reservation_id' => $reservation->id])->reservation?->is($reservation))->toBeTrue();
});

it('reserves a line once at a time, never below zero, and never reactivates an ended reservation (C-05; C-06; L-07)', function () {
    $reservation = Reservation::factory()->create();
    $row = fn () => DB::table('reservations')->where('id', $reservation->id);

    Refusal::assert(Refusal::UNIQUE, 'reservations_c05_one_active_per_line', fn () => Reservation::factory()->create(['project_line_id' => $reservation->project_line_id]));
    Refusal::assert(Refusal::CHECK, 'reservations_c05_active_not_empty', fn () => $row()->update(['remaining_qty_base' => 0]));
    Refusal::assert(Refusal::CHECK, 'reservations_c05_remaining', fn () => $row()->update(['state' => 'RELEASED', 'remaining_qty_base' => -1]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'reservations_c09_line_foreign', fn () => Reservation::factory()->create(['company_id' => Company::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'reservation_events_c06_cut_names_movement', fn () => ReservationEvent::factory()->create(['reservation_id' => $reservation->id, 'kind' => 'CUT', 'qty_delta_base' => '-1.000']));

    ReservationEvent::factory()->create(['reservation_id' => $reservation->id, 'kind' => 'CUT', 'qty_delta_base' => '-1.000', 'cut_by_movement_id' => StockMovementFactory::post('CONDITION_CHANGE')]);
    $row()->update(['state' => 'CONSUMED', 'remaining_qty_base' => 0]);

    Refusal::assert(Refusal::CHECK, 'reservations_c05_ended_once', fn () => $row()->update(['state' => 'ACTIVE', 'remaining_qty_base' => 1]));

    // An ended reservation frees its line for a new one.
    expect(Reservation::factory()->create(['project_line_id' => $reservation->project_line_id])->exists)->toBeTrue();
});
