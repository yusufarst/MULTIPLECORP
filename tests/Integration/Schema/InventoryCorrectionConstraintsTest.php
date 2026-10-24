<?php

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use App\Modules\FUL\Models\DropshipConfirmationLine;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\SerialUnit;
use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockCount;
use App\Modules\INV\Models\StockCountFinding;
use App\Modules\INV\Models\StockCountLine;
use App\Modules\INV\Models\StockRestoration;
use App\Modules\INV\Models\UnattributedLossCandidate;
use App\Modules\INV\Models\UnattributedLossCase;
use App\Modules\INV\Models\UnattributedLossResolution;
use App\Modules\INV\Models\UnattributedLossResolutionLine;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use Database\Factories\INV\StockCountFactory;
use Database\Factories\INV\StockMovementFactory;
use Database\Factories\INV\UnattributedLossCaseFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-008), criteria 1 and 2: the INV rows of DATABASE §18 on consumptions, returns, counts and
// pending cases (§4.7, §5.6–§5.8) — a consumption's source and bearer keyed to their causes (C-28), never
// more restored than consumed (C-27, C-55), a return in its cited unit or the base unit (L-31), a drop-ship
// error reversal exact and once (C-55, C-59), a count's basis written when it opens (C-33) with one open
// finding per scope (C-34), and a pending case's quantities, snapshot and exposures (C-35–C-37).

uses(RefreshDatabase::class);

it('keys a consumption\'s bearer to its cause and its source to its lot (C-28; C-09; D-DB-14)', function () {
    $line = DispatchLine::factory()->create();
    $lot = InventoryLot::factory()->create(['source_company_id' => $line->company_id, 'product_id' => $line->product_id]);
    $dispatch = ['lot_id' => $lot->id, 'kind' => 'DISPATCH', 'dispatch_line_id' => $line->id, 'bearer_company_id' => $line->company_id, 'bearer_project_id' => $line->project_id];
    $other = Company::factory()->create()->id;

    Refusal::assert(Refusal::CHECK, 'stock_consumptions_c28_cause_by_kind', fn () => StockConsumption::factory()->create(['lot_id' => $lot->id, 'kind' => 'DISPATCH']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_consumptions_c28_dispatch_foreign', fn () => StockConsumption::factory()->create([...$dispatch, 'bearer_project_id' => Project::factory()->create(['company_id' => $line->company_id])->id]));
    Refusal::assert(Refusal::CHECK, 'stock_consumptions_c28_purchase_return_bearer', fn () => StockConsumption::factory()->create(['kind' => 'PURCHASE_RETURN', 'bearer_company_id' => $other, 'allocation_reason' => 'Retur ke pemasok']));
    Refusal::assert(Refusal::CHECK, 'stock_consumptions_c28_allocation_reason', fn () => StockConsumption::factory()->create(['bearer_company_id' => $other]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_consumptions_lot_product_foreign', fn () => StockConsumption::factory()->create(['lot_id' => $lot->id, 'product_id' => Product::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'stock_consumptions_case_side', fn () => StockConsumption::factory()->create(['correction_case_id' => CorrectionCase::factory()->create(['company_id' => $other])->id, 'case_company_id' => $other]));
    Refusal::assert(Refusal::CHECK, 'stock_consumptions_c09_quantity', fn () => StockConsumption::factory()->create(['qty_base' => '0.500']));

    expect(StockConsumption::factory()->create($dispatch)->bearerProject?->is($line->project))->toBeTrue()
        // Another company's lot carries the overlay with its reason (L-44).
        ->and(StockConsumption::factory()->create([...$dispatch, 'lot_id' => InventoryLot::factory()->create(['product_id' => $line->product_id])->id, 'allocation_reason' => 'Stok PT lain dipakai'])->exists)->toBeTrue();
});

it('bears an unattributed loss by its resolution line\'s company and project (C-28; C-37)', function () {
    $resolutionLine = UnattributedLossResolutionLine::factory()->create();
    $loss = [
        'kind' => 'UNATTRIBUTED_LOSS', 'unattributed_resolution_line_id' => $resolutionLine->id, 'bearer_company_id' => $resolutionLine->assigned_company_id,
        'lot_id' => InventoryLot::factory()->create(['source_company_id' => $resolutionLine->assigned_company_id, 'product_id' => $resolutionLine->product_id])->id,
    ];

    Refusal::assert(Refusal::CHECK, 'stock_consumptions_c28_resolution_bearer_project', fn () => StockConsumption::factory()->create([...$loss, 'bearer_project_id' => Project::factory()->create(['company_id' => $resolutionLine->assigned_company_id])->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_consumptions_c28_resolution_line_foreign', fn () => StockConsumption::factory()->create([...$loss, 'bearer_company_id' => Company::factory()->create()->id, 'allocation_reason' => 'Penugasan']));

    expect(StockConsumption::factory()->create($loss)->resolutionLine?->is($resolutionLine))->toBeTrue();
});

it('restores, reclassifies and substitutes no more of a consumption than was consumed (C-27; AX-10)', function () {
    $consumption = StockConsumption::factory()->create();
    DB::table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption->id, 'qty_base' => 2]);
    $guard = fn () => DB::table('stock_consumption_balances')->where('stock_consumption_id', $consumption->id);

    Refusal::assert(Refusal::CHECK, 'stock_consumption_balances_c27_cap', fn () => $guard()->update(['restored_qty_base' => 2, 'reclassified_qty_base' => 1]));
    Refusal::assert(Refusal::CHECK, 'stock_consumption_balances_one_source', fn () => DB::table('stock_consumption_balances')->insert(['qty_base' => 1]));
    Refusal::assert(Refusal::UNIQUE, 'stock_consumption_balances_stock_consumption_id_unique', fn () => DB::table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption->id, 'qty_base' => 2]));

    $guard()->update(['restored_qty_base' => 1, 'substituted_qty_base' => 1]);

    expect($guard()->value('restored_qty_base'))->toBe('1.000');
});

it('returns dispatched units in the dispatch\'s own unit at its ratio or in the base unit, citing its own dispatch line (L-31; C-28)', function () {
    $box = Unit::factory()->create()->id;
    $line = DispatchLine::factory()->create(['entered_unit_id' => $box, 'entered_qty' => '2.000', 'ratio_to_base' => 12, 'qty_base' => '24.000', 'unreserved_qty_base' => '24.000']);
    $consumption = StockConsumption::factory()->create([
        'lot_id' => InventoryLot::factory()->create(['source_company_id' => $line->company_id, 'product_id' => $line->product_id])->id, 'kind' => 'DISPATCH',
        'dispatch_line_id' => $line->id, 'bearer_company_id' => $line->company_id, 'bearer_project_id' => $line->project_id, 'qty_base' => '24.000',
    ]);
    $return = [
        'stock_consumption_id' => $consumption->id, 'kind' => 'DISPATCH_REVERSAL', 'after_delivery' => false, 'cited_dispatch_line_id' => $line->id,
        'cited_unit_id' => $box, 'cited_ratio_to_base' => 12, 'entered_unit_id' => $box, 'ratio_to_base' => 12, 'entered_qty' => '1.000', 'qty_base' => '12.000',
    ];
    $other = DispatchLine::factory()->create(['entered_unit_id' => $box, 'entered_qty' => '1.000', 'ratio_to_base' => 12, 'qty_base' => '12.000', 'unreserved_qty_base' => '12.000']);

    Refusal::assert(Refusal::CHECK, 'stock_restorations_l31_cited_or_base_unit', fn () => StockRestoration::factory()->create([...$return, 'ratio_to_base' => 6, 'qty_base' => '6.000']));
    Refusal::assert(Refusal::CHECK, 'stock_restorations_l31_cited_unit_by_source', fn () => StockRestoration::factory()->create([...$return, 'cited_dispatch_line_id' => null, 'cited_unit_id' => null, 'cited_ratio_to_base' => null, 'entered_unit_id' => Unit::factory()->create()->id, 'ratio_to_base' => 1, 'qty_base' => '1.000']));
    Refusal::assert(Refusal::CHECK, 'stock_restorations_l31_cited_unit_by_source', fn () => StockRestoration::factory()->create([...$return, 'entered_unit_id' => Unit::factory()->create()->id, 'ratio_to_base' => 1, 'qty_base' => '1.000']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_restorations_l31_cited_dispatch_foreign', fn () => StockRestoration::factory()->create([...$return, 'cited_dispatch_line_id' => $other->id]));
    Refusal::assert(Refusal::CHECK, 'stock_restorations_after_delivery_by_kind', fn () => StockRestoration::factory()->create([...$return, 'kind' => 'SALES_RETURN']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_restorations_c28_consumption_foreign', fn () => StockRestoration::factory()->create([...$return, 'source_company_id' => Company::factory()->create()->id]));

    expect(StockRestoration::factory()->create($return)->qty_base)->toBe('12.000')
        // The base unit is the other way back (L-31).
        ->and(StockRestoration::factory()->create([...$return, 'entered_unit_id' => DB::table('products')->where('id', $line->product_id)->value('base_unit_id'), 'ratio_to_base' => 1, 'qty_base' => '1.000'])->exists)->toBeTrue();
});

it('records a drop-ship return without a movement, its error reversal exact, of its kind and once (C-55; C-59; CM-15/37)', function () {
    // A drop-ship line of TASK-009, its unit snapshot, company and product the restoration's (DIR-091 (1)).
    $line = DropshipConfirmationLine::factory()->create();
    $dropship = [
        'stock_consumption_id' => null, 'dropship_confirmation_line_id' => $line->id, 'stock_movement_id' => null, 'returned_condition' => null,
        'source_company_id' => $line->company_id, 'product_id' => $line->product_id, 'quantity_scale' => $line->quantity_scale,
        'cited_unit_id' => $line->entered_unit_id, 'cited_ratio_to_base' => $line->ratio_to_base,
        'entered_unit_id' => $line->entered_unit_id, 'ratio_to_base' => 1, 'entered_qty' => '1.000', 'qty_base' => '1.000',
    ];
    $return = StockRestoration::factory()->create([...$dropship, 'kind' => 'DROPSHIP_SUPPLIER_RETURN', 'after_delivery' => true]);
    $undo = [...$dropship, 'kind' => 'DROPSHIP_ERROR_REVERSAL', 'reverses_restoration_id' => $return->id, 'reverses_restoration_kind' => 'DROPSHIP_SUPPLIER_RETURN'];

    Refusal::assert(Refusal::CHECK, 'stock_restorations_movement_by_kind', fn () => StockRestoration::factory()->create([...$dropship, 'kind' => 'DROPSHIP_SUPPLIER_RETURN', 'after_delivery' => true, 'stock_movement_id' => StockMovementFactory::post('RESTORATION')]));
    Refusal::assert(Refusal::CHECK, 'stock_restorations_c27_source_by_kind', fn () => StockRestoration::factory()->create(['kind' => 'DROPSHIP_RETURN', 'after_delivery' => false]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_restorations_c55_reverses_foreign', fn () => StockRestoration::factory()->create([...$undo, 'entered_qty' => '2.000', 'qty_base' => '2.000']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_restorations_c55_reverses_foreign', fn () => StockRestoration::factory()->create([...$undo, 'reverses_restoration_kind' => 'DROPSHIP_CONFIRMATION_REVERSAL']));

    StockRestoration::factory()->create($undo);

    Refusal::assert(Refusal::UNIQUE, 'stock_restorations_c55_one_error_reversal', fn () => StockRestoration::factory()->create($undo));
});

it('writes a count\'s basis only when it opens, one open finding per scope and product (C-33; C-34; L-14; AX-07)', function () {
    $count = StockCount::factory()->create();
    $finding = StockCountFinding::factory()->create(['stock_count_id' => $count->id]);
    $late = StockCount::factory()->create(['started_at' => now()->subHour()]);
    $scope = ['product_id' => Product::factory()->create()->id, 'quantity_scale' => 0, 'location_id' => $late->location_id, 'condition' => 'USABLE', 'basis_version' => 0, 'system_qty_at_start' => 0];

    Refusal::assert(Refusal::CHECK, 'stock_count_scopes_c33_opened_with_count', fn () => DB::table('stock_count_scopes')->insert(['stock_count_id' => $late->id, ...$scope]));
    Refusal::assert(Refusal::UNIQUE, 'stock_count_findings_c34_one_open', fn () => StockCountFinding::factory()->create(['stock_count_id' => StockCount::factory()->create(['location_id' => $count->location_id])->id, 'product_id' => $finding->product_id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'stock_count_findings_scope_foreign', fn () => DB::table('stock_count_findings')->insert(['stock_count_id' => $count->id, 'product_id' => $scope['product_id'], 'quantity_scale' => 0, 'location_id' => $count->location_id, 'condition' => 'USABLE', 'surplus_qty_base' => 1]));
    Refusal::assert(Refusal::CHECK, 'stock_count_findings_c09_surplus', fn () => StockCountFinding::factory()->create(['surplus_qty_base' => '0.000']));
    Refusal::assert(Refusal::CHECK, 'stock_count_lines_c09_counted', fn () => StockCountLine::factory()->create(['counted_qty' => '1.500']));
    Refusal::assert(Refusal::CHECK, 'stock_count_lines_one_serial', fn () => StockCountLine::factory()->create(['serial_unit_id' => SerialUnit::factory()->create()->id, 'unregistered_serial_text' => 'SN-TIDAK-TERDAFTAR']));

    // An attributed finding frees its scope for the next one.
    DB::table('stock_count_findings')->where('id', $finding->id)->update(['state' => 'ATTRIBUTED', 'attributed_company_id' => Company::factory()->create()->id]);

    expect(StockCountFinding::factory()->create(['stock_count_id' => $count->id, 'product_id' => $finding->product_id])->exists)->toBeTrue();
});

it('holds a count\'s product set to distinct goods products of the shared catalogue, and its scope within it (DIR-091 (3), (4); §4.4)', function () {
    [$goods, $service] = [Product::factory()->create(), Product::factory()->create(['kind' => 'SERVICE'])];
    $set = fn (array $products): StockCount => StockCount::factory()->create(['product_ids' => $products]);

    Refusal::assert(Refusal::CHECK, 'stock_counts_product_set', fn () => $set([$goods->id, 999_999]));
    Refusal::assert(Refusal::CHECK, 'stock_counts_product_set', fn () => $set([$goods->id, $goods->id]));
    Refusal::assert(Refusal::CHECK, 'stock_counts_product_set', fn () => $set([$service->id]));
    Refusal::assert(Refusal::CHECK, 'stock_counts_product_set', fn () => DB::table('stock_counts')->insert([...StockCount::factory()->raw(), 'product_ids' => "{{$goods->id},NULL}"]));

    $count = $set([$goods->id]);

    Refusal::assert(Refusal::CHECK, 'stock_count_scopes_within_count', fn () => StockCountFactory::openScope($count->id, Product::factory()->create()->id));

    StockCountFactory::openScope($count->id, $goods->id);
    // An empty set, as no set, counts every product of the location and condition.
    StockCountFactory::openScope($set([])->id, $service->id);

    expect(DB::table('stock_count_scopes')->count())->toBe(2);
});

it('cites a restoration\'s correction case of its source or of its consumption\'s bearer, as a consumption does (DIR-091 (2); §17)', function () {
    $bearer = Company::factory()->create()->id;
    $consumption = StockConsumption::factory()->create(['bearer_company_id' => $bearer, 'allocation_reason' => 'Stok PT lain dipakai']);
    $restore = fn (int $company): StockRestoration => StockRestoration::factory()->create([
        'stock_consumption_id' => $consumption->id, 'correction_case_id' => CorrectionCase::factory()->create(['company_id' => $company])->id, 'case_company_id' => $company,
    ]);

    Refusal::assert(Refusal::CHECK, 'stock_restorations_case_side', fn () => $restore(Company::factory()->create()->id));

    expect($restore($bearer)->exists)->toBeTrue()
        ->and($restore($consumption->source_company_id)->exists)->toBeTrue();
});

it('recognizes a pending case within the claims not already missing, its counters never overrunning it (C-35; C-36; DIR-027)', function () {
    $case = UnattributedLossCase::factory()->create();
    $row = fn () => DB::table('unattributed_loss_cases')->where('id', $case->id);

    Refusal::assert(Refusal::CHECK, 'unattributed_loss_cases_c36_within_exposure', fn () => UnattributedLossCase::factory()->create(['recognized_qty_base' => '2.000', 'prior_pending_qty_base' => '1.000']));
    Refusal::assert(Refusal::CHECK, 'unattributed_loss_cases_not_serialized', fn () => UnattributedLossCase::factory()->create(['product_id' => Product::factory()->create(['is_serialized' => true])->id]));
    Refusal::assert(Refusal::CHECK, 'unattributed_loss_cases_c35_found_only_loss', fn () => DB::table('unattributed_loss_cases')->where('id', UnattributedLossCase::factory()->create(['case_kind' => 'CONDITION_CHANGE'])->id)->update(['found_qty_base' => 1]));
    Refusal::assert(Refusal::CHECK, 'unattributed_loss_cases_c35_pending', fn () => $row()->update(['resolved_qty_base' => 2]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table unattributed_loss_cases', fn () => $row()->update(['recognized_qty_base' => 2]));

    $row()->update(['resolved_qty_base' => 1]);

    expect($row()->value('pending_qty_base'))->toBe('0.000');
});

it('fixes a case\'s snapshot at recognition: candidates summing to its exposure and each company\'s to its exposure row (C-36; DIR-027 items 3, 8)', function () {
    $case = UnattributedLossCase::factory()->create();
    $atCommit = fn () => DB::statement('set constraints unattributed_loss_candidates_c36_snapshot_sums, unattributed_loss_exposures_c36_snapshot_sums immediate');
    $candidate = fn (string $quantity): UnattributedLossCandidate => UnattributedLossCandidate::factory()->create(['unattributed_loss_case_id' => $case->id, 'qty_in_scope_at_recognition' => $quantity]);

    Refusal::assert(Refusal::CHECK, 'unattributed_loss_candidates_c36_snapshot_sums', function () use ($case, $candidate, $atCommit): void {
        UnattributedLossCaseFactory::expose($case->id, $candidate('1.000')->candidate_company_id, '1.000');
        $atCommit();
    });
    Refusal::assert(Refusal::CHECK, 'unattributed_loss_candidates_c36_snapshot_sums', function () use ($case, $candidate, $atCommit): void {
        UnattributedLossCaseFactory::expose($case->id, $candidate('2.000')->candidate_company_id, '1.000');
        $atCommit();
    });

    // A case recognized with no snapshot.
    Refusal::assert(Refusal::CHECK, 'unattributed_loss_candidates_c36_snapshot_sums', function (): void {
        UnattributedLossCase::factory()->create();
        DB::statement('set constraints unattributed_loss_cases_c36_snapshot_sums immediate');
    });

    UnattributedLossCaseFactory::expose($case->id, $candidate('2.000')->candidate_company_id);
    $atCommit();

    expect($case->candidates()->count())->toBe(1);
});

it('assigns a company no more of a case than its exposure, overlapping cases no more than their group bound, never lowered (C-37; §5.8)', function () {
    $line = UnattributedLossResolutionLine::factory()->create();
    $exposure = fn () => DB::table('unattributed_loss_exposures')->where('unattributed_loss_case_id', $line->unattributed_loss_case_id)->where('company_id', $line->assigned_company_id);
    $group = ['exposure_group_case_id' => $line->unattributed_loss_case_id, 'company_id' => $line->assigned_company_id];
    DB::table('unattributed_loss_group_exposures')->insert([...$group, 'bound_qty_base' => 2]);
    $bound = fn () => DB::table('unattributed_loss_group_exposures')->where($group);

    Refusal::assert(Refusal::CHECK, 'unattributed_loss_exposures_c37_bound', fn () => $exposure()->update(['assigned_net_qty_base' => 3]));
    Refusal::assert(Refusal::CHECK, 'unattributed_loss_group_exposures_c37_bound', fn () => $bound()->update(['assigned_net_qty_base' => 3]));
    Refusal::assert(Refusal::CHECK, 'unattributed_loss_group_exposures_c37_bound_never_lowered', fn () => $bound()->update(['bound_qty_base' => 1]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table unattributed_loss_exposures', fn () => $exposure()->update(['exposure_qty_base' => 5]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'unattributed_loss_resolution_lines_c37_exposure_foreign', fn () => UnattributedLossResolutionLine::factory()->create(['unattributed_loss_resolution_id' => $line->unattributed_loss_resolution_id, 'assigned_company_id' => Company::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'unattributed_loss_resolution_lines_c32_bearer_project_foreign', fn () => UnattributedLossResolutionLine::factory()->create(['unattributed_loss_resolution_id' => $line->unattributed_loss_resolution_id, 'bearer_project_id' => Project::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'unattributed_loss_resolutions_c37_supersedes_foreign', fn () => UnattributedLossResolution::factory()->create(['supersedes_resolution_id' => $line->unattributed_loss_resolution_id]));

    $exposure()->update(['assigned_net_qty_base' => 1]);
    $bound()->update(['bound_qty_base' => 3, 'assigned_net_qty_base' => 1]);

    expect($bound()->value('bound_qty_base'))->toBe('3.000');
});
