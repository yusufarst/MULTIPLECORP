<?php

use App\Modules\CAT\Models\Product;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use App\Modules\PUR\Models\PurchaseLine;
use App\Modules\PUR\Models\PurchaseLineAdjustment;
use App\Modules\PUR\Models\PurchaseLineClosure;
use App\Modules\PUR\Models\PurchaseLineTaxComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-007), criteria 1, 2 and 4: the PUR rows of DATABASE §18 on purchases (§4.6) — a purchase
// number once in its company (C-10), every reference within the company (C-32), exact conversions at the
// product's scale (C-09), a source bill typed SUPPLIER_BILL (C-58), the two receipt caps and the posted
// values (C-29, C-57), a closure once per line per command and its linear supersession (C-48, C-59), one
// purchase per import row (C-47), and a line edited only before its first effect (L-23).

uses(RefreshDatabase::class);

/** A purchase line's guard row, written as PurchasePosting would (§3.1). @param  array<string, mixed>  $values */
function purchaseGuard(PurchaseLine $line, array $values = []): void
{
    DB::table('purchase_line_balances')->insert(['purchase_line_id' => $line->id, 'ordered_qty_base' => $line->qty_base, 'goods_value' => $line->line_value, ...$values]);
}

it('numbers a purchase once in its company, for a project of the same company or none (C-10; C-32; L-05)', function () {
    $purchase = Purchase::factory()->create();
    $row = ImportRow::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'purchases_c10_number', fn () => Purchase::factory()->create(['company_id' => $purchase->company_id, 'purchase_number' => $purchase->purchase_number]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table purchases', fn () => DB::table('purchases')->where('id', $purchase->id)->update(['purchase_number' => 'CTH/PO/2026/9999']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'purchases_c32_project_foreign', fn () => Purchase::factory()->create(['company_id' => $purchase->company_id, 'project_id' => Project::factory()->create()->id]));

    Purchase::factory()->create(['origin' => 'OPENING', 'import_row_id' => $row->id]);

    Refusal::assert(Refusal::UNIQUE, 'purchases_import_row_id_unique', fn () => Purchase::factory()->create(['origin' => 'OPENING', 'import_row_id' => $row->id]));

    expect(Purchase::factory()->create(['company_id' => $purchase->company_id, 'project_id' => Project::factory()->create(['company_id' => $purchase->company_id])->id])->project?->company_id)->toBe($purchase->company_id);
});

it('cancels a purchase with its actor, time and reason, never deleting it (BR-PUR-01–04; §19)', function () {
    $purchase = Purchase::factory()->create();
    $row = fn () => DB::table('purchases')->where('id', $purchase->id);

    Refusal::assert(Refusal::CHECK, 'purchases_cancellation_columns', fn () => $row()->update(['state' => 'CANCELLED']));
    Refusal::assert(Refusal::CHECK, 'purchases_supplier_snapshot_object', fn () => Purchase::factory()->create(['supplier_snapshot' => ['Pemasok']]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table purchases', fn () => $row()->delete());

    $row()->update(['state' => 'CANCELLED', 'cancelled_by' => User::factory()->create()->id, 'cancelled_at' => now(), 'cancelled_reason' => 'Pemasok tidak menyanggupi']);

    expect($row()->value('state'))->toBe('CANCELLED');
});

it('converts a purchased quantity exactly at its product\'s scale, within the purchase\'s company (C-09; C-32)', function (Closure $write, string $constraint, string $state = Refusal::CHECK) {
    Refusal::assert($state, $constraint, $write);
})->with([
    'a line not its ratio\'s multiple' => [fn () => PurchaseLine::factory()->create(['entered_qty' => '1.000', 'ratio_to_base' => 12, 'qty_base' => '10.000']), 'purchase_lines_c09_conversion'],
    'a fraction of a counted product' => [fn () => PurchaseLine::factory()->create(['entered_qty' => '0.500', 'qty_base' => '0.500']), 'purchase_lines_c09_scale'],
    'nothing ordered' => [fn () => PurchaseLine::factory()->create(['entered_qty' => '0.000', 'qty_base' => '0.000']), 'purchase_lines_c09_scale'],
    'a non-stock line shipped direct' => [fn () => PurchaseLine::factory()->create(['kind' => 'NONSTOCK', 'delivery_mode' => 'DROPSHIP']), 'purchase_lines_nonstock_to_warehouse'],
    'a negative value' => [fn () => PurchaseLine::factory()->create(['line_value' => '-1.00']), 'purchase_lines_values'],
    'a scale not the product\'s' => [fn () => PurchaseLine::factory()->create(['quantity_scale' => 2]), 'purchase_lines_c09_product_scale_foreign', Refusal::FOREIGN_KEY],
    'another company\'s project line' => [fn () => PurchaseLine::factory()->create(['project_line_id' => ProjectLine::factory()->create()->id]), 'purchase_lines_c32_project_line_foreign', Refusal::FOREIGN_KEY],
    'another company\'s purchase' => [fn () => PurchaseLine::factory()->create(['purchase_id' => Purchase::factory()->create()->id, 'company_id' => Company::factory()->create()->id]), 'purchase_lines_c32_purchase_foreign', Refusal::FOREIGN_KEY],
]);

it('edits a line and its tax only before the line\'s first effect, then never (L-23; §19)', function () {
    $line = PurchaseLine::factory()->create();
    $tax = PurchaseLineTaxComponent::factory()->create(['purchase_line_id' => $line->id]);
    $spare = PurchaseLineTaxComponent::factory()->create(['purchase_line_id' => $line->id]);
    purchaseGuard($line);

    DB::table('purchase_lines')->where('id', $line->id)->update(['unit_price' => '95000.00', 'line_value' => '950000.00']);
    DB::table('purchase_line_tax_components')->where('id', $tax->id)->update(['base_amount' => '950000.00', 'amount' => '104500.00']);
    DB::table('purchase_line_tax_components')->where('id', $spare->id)->delete();

    // The first effect: a closure of part of the remainder.
    PurchaseLineClosure::factory()->create(['purchase_line_id' => $line->id]);

    Refusal::assert(Refusal::CHECK, 'purchase_lines_draft_until_first_effect', fn () => DB::table('purchase_lines')->where('id', $line->id)->update(['unit_price' => '1.00']));
    Refusal::assert(Refusal::CHECK, 'purchase_line_tax_components_draft_until_first_effect', fn () => DB::table('purchase_line_tax_components')->where('id', $tax->id)->update(['amount' => '1.00']));
    Refusal::assert(Refusal::CHECK, 'purchase_line_tax_components_draft_until_first_effect', fn () => DB::table('purchase_line_tax_components')->where('id', $tax->id)->delete());
    Refusal::assert(Refusal::CHECK, 'purchase_line_tax_components_draft_until_first_effect', fn () => PurchaseLineTaxComponent::factory()->create(['purchase_line_id' => $line->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table purchase_lines', fn () => DB::table('purchase_lines')->where('id', $line->id)->delete());

    expect(DB::table('purchase_lines')->where('id', $line->id)->value('line_value'))->toBe('950000.00');
});

it('treats a received quantity on the line\'s guard as its first effect (L-23; AX-04)', function () {
    $line = PurchaseLine::factory()->create();
    purchaseGuard($line, ['normal_received_net_qty_base' => 1]);

    Refusal::assert(Refusal::CHECK, 'purchase_lines_draft_until_first_effect', fn () => DB::table('purchase_lines')->where('id', $line->id)->update(['description' => 'Diubah']));
});

it('holds normal receipts, closures and drop-ship confirmations within the ordered quantity and replacements within their expectation, separately (C-29; PX-03; AX-12)', function () {
    $line = PurchaseLine::factory()->create();
    purchaseGuard($line, ['normal_received_net_qty_base' => 6, 'closed_qty_base' => 2, 'dropship_confirmed_net_qty_base' => 2, 'replacement_expected_qty_base' => 3]);
    $guard = fn () => DB::table('purchase_line_balances')->where('purchase_line_id', $line->id);

    Refusal::assert(Refusal::CHECK, 'purchase_line_balances_c29_normal_cap', fn () => $guard()->update(['normal_received_net_qty_base' => 7]));
    // Replacement headroom never admits a normal receipt.
    Refusal::assert(Refusal::CHECK, 'purchase_line_balances_c29_replacement_cap', fn () => $guard()->update(['replacement_received_net_qty_base' => 4]));
    Refusal::assert(Refusal::CHECK, 'purchase_line_balances_not_negative', fn () => $guard()->update(['closed_qty_base' => -1]));

    $guard()->update(['replacement_received_net_qty_base' => 3]);

    expect($guard()->value('replacement_received_net_qty_base'))->toBe('3.000');
});

it('posts no more of a line\'s goods and non-creditable tax than its current values (C-57)', function () {
    $line = PurchaseLine::factory()->create();
    purchaseGuard($line, ['noncreditable_tax_value' => '110000.00']);
    $guard = fn () => DB::table('purchase_line_balances')->where('purchase_line_id', $line->id);

    Refusal::assert(Refusal::CHECK, 'purchase_line_balances_c57_posted_values', fn () => $guard()->update(['posted_goods_value' => '1000000.01']));
    Refusal::assert(Refusal::CHECK, 'purchase_line_balances_c57_posted_values', fn () => $guard()->update(['posted_noncreditable_tax_value' => '110000.01']));

    $guard()->update(['posted_goods_value' => '1000000.00']);

    // A price correction below what is posted fails until the posting moves with it (CM-17; AX-34).
    Refusal::assert(Refusal::CHECK, 'purchase_line_balances_c57_posted_values', fn () => $guard()->update(['goods_value' => '900000.00']));
});

it('names each charge\'s source bill, a supplier\'s bill of the purchase\'s company (L-43; C-32; C-58)', function (Closure $write, string $constraint, string $state = Refusal::CHECK) {
    Refusal::assert($state, $constraint, $write);
})->with([
    'a bill of another type' => [fn () => PurchaseCharge::factory()->create(['source_bill_evidence_type' => 'CLIENT_ORDER']), 'purchase_charges_c58_source_bill'],
    'another evidence typed as a bill' => [function () {
        $purchase = Purchase::factory()->create();

        return PurchaseCharge::factory()->create(['purchase_id' => $purchase->id, 'source_bill_evidence_id' => EvidenceDocument::factory()->create(['company_id' => $purchase->company_id])->id]);
    }, 'purchase_charges_c58_source_bill_type_foreign', Refusal::FOREIGN_KEY],
    'another company\'s bill' => [fn () => PurchaseCharge::factory()->create(['source_bill_evidence_id' => EvidenceDocument::factory()->supplierBill()->create()->id]), 'purchase_charges_c32_source_bill_foreign', Refusal::FOREIGN_KEY],
    'a charge of nothing' => [fn () => PurchaseCharge::factory()->create(['amount' => '0.00']), 'purchase_charges_amount_positive'],
    'an attributable charge without its basis' => [fn () => PurchaseCharge::factory()->create(['allocation_basis' => null]), 'purchase_charges_basis_when_attributable'],
    'a non-attributable charge spread' => [fn () => PurchaseCharge::factory()->create(['classification' => 'NON_ATTRIBUTABLE']), 'purchase_charges_basis_when_attributable'],
    'an unknown basis' => [fn () => PurchaseCharge::factory()->create(['allocation_basis' => 'VOLUME']), 'purchase_charges_allocation_basis_code'],
]);

it('versions a charge\'s share on a line, each version once, within the company (BR-FIN-13; CM-35; C-32)', function () {
    $share = PurchaseChargeLineShare::factory()->create();
    $same = ['purchase_charge_id' => $share->purchase_charge_id, 'purchase_line_id' => $share->purchase_line_id];

    Refusal::assert(Refusal::UNIQUE, 'purchase_charge_line_shares_version_key', fn () => PurchaseChargeLineShare::factory()->create($same));
    Refusal::assert(Refusal::CHECK, 'purchase_charge_line_shares_values', fn () => PurchaseChargeLineShare::factory()->create([...$same, 'allocation_version' => 0]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'purchase_charge_line_shares_c32_line_foreign', fn () => PurchaseChargeLineShare::factory()->create(['purchase_line_id' => PurchaseLine::factory()->create()->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table purchase_charge_line_shares', fn () => DB::table('purchase_charge_line_shares')->where('id', $share->id)->update(['share_amount' => '1.00']));

    expect(PurchaseChargeLineShare::factory()->create([...$same, 'allocation_version' => 2, 'share_amount' => '48000.00'])->exists)->toBeTrue();
});

it('closes a line\'s remainder once per command, superseded once within its line (BR-PUR-04; C-48; C-59)', function () {
    $line = PurchaseLine::factory()->create();
    $command = ['command_id' => (string) Str::uuid7()];
    $closure = PurchaseLineClosure::factory()->create(['purchase_line_id' => $line->id, ...$command, 'command_line_no' => 1]);
    PurchaseLineClosure::factory()->create([...$command, 'command_line_no' => 2]);

    Refusal::assert(Refusal::UNIQUE, 'purchase_line_closures_one_per_line_per_command', fn () => PurchaseLineClosure::factory()->create(['purchase_line_id' => $line->id, ...$command, 'command_line_no' => 3]));
    Refusal::assert(Refusal::UNIQUE, 'purchase_line_closures_c48_command_identity', fn () => PurchaseLineClosure::factory()->create([...$command, 'command_line_no' => 2]));
    Refusal::assert(Refusal::CHECK, 'purchase_line_closures_c09_quantity', fn () => PurchaseLineClosure::factory()->create(['closed_qty_base' => '0.000']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'purchase_line_closures_c09_line_foreign', fn () => PurchaseLineClosure::factory()->create(['purchase_line_id' => $line->id, 'product_id' => Product::factory()->create()->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'purchase_line_closures_c59_supersedes_foreign', fn () => PurchaseLineClosure::factory()->create(['supersedes_id' => $closure->id]));

    PurchaseLineClosure::factory()->create(['purchase_line_id' => $line->id, 'supersedes_id' => $closure->id]);

    Refusal::assert(Refusal::UNIQUE, 'purchase_line_closures_c59_linear_chain', fn () => PurchaseLineClosure::factory()->create(['purchase_line_id' => $line->id, 'supersedes_id' => $closure->id]));
});

it('adjusts exactly one line or charge, a charge only by its correction, a case of the company\'s (CM-17; AX-34; C-32)', function (Closure $write, string $constraint, string $state = Refusal::CHECK) {
    Refusal::assert($state, $constraint, $write);
})->with([
    'a line and a charge' => [fn () => PurchaseLineAdjustment::factory()->create(['purchase_charge_id' => PurchaseCharge::factory()->create()->id]), 'purchase_line_adjustments_one_target'],
    'a charge correction on a line' => [fn () => PurchaseLineAdjustment::factory()->create(['kind' => 'CHARGE_CORRECTION']), 'purchase_line_adjustments_target_by_kind'],
    'an unknown kind' => [fn () => PurchaseLineAdjustment::factory()->create(['kind' => 'DISCOUNT']), 'purchase_line_adjustments_kind_code'],
    'another company\'s case' => [fn () => PurchaseLineAdjustment::factory()->create(['correction_case_id' => CorrectionCase::factory()->create()->id]), 'purchase_line_adjustments_c32_correction_case_foreign', Refusal::FOREIGN_KEY],
]);

it('corrects a charge by its own adjustment, within its company (CM-17)', function () {
    $charge = PurchaseCharge::factory()->create();

    Refusal::assert(Refusal::FOREIGN_KEY, 'purchase_line_adjustments_c32_charge_foreign', fn () => PurchaseLineAdjustment::factory()->create(['purchase_line_id' => null, 'purchase_charge_id' => $charge->id, 'kind' => 'CHARGE_CORRECTION', 'company_id' => Company::factory()->create()->id]));

    expect(PurchaseLineAdjustment::factory()->create(['purchase_line_id' => null, 'purchase_charge_id' => $charge->id, 'kind' => 'CHARGE_CORRECTION', 'company_id' => $charge->company_id, 'amount_delta' => '5000.00'])->charge?->is($charge))->toBeTrue();
});
