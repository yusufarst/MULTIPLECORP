<?php

use App\Modules\CST\Models\CostAttribution;
use App\Modules\CST\Models\Expense;
use App\Modules\CST\Models\LotCostEntry;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use App\Modules\PUR\Models\PurchaseLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-007), criteria 1, 2 and 4: the CST rows of DATABASE §18 on costing and expenses (§4.8, §6)
// — a lot's cost posted once per basis, tax and share with the component its kind fixes (C-57), its
// remaining cost conserved (C-26), HPP attributed once (C-25), a cross-company bearer only with its reason
// (C-28), a charge and its shares never over-allocated (C-21), a fee expensed only from its settlement and
// at most its evidence (C-23), a bill cited once (C-20), and every reference within its company (C-32,
// C-58). The lots, consumptions and drop-ship lines are TASK-008's: here they are synthetic keys.

uses(RefreshDatabase::class);

it('fixes a lot cost\'s component by its kind and names a charge row\'s share or re-spread line (C-57; §6)', function (array $attributes, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => LotCostEntry::factory()->create($attributes));
})->with([
    'goods carried as tax' => [['entry_kind' => 'GOODS', 'cost_component' => 'NONCREDITABLE_TAX'], 'lot_cost_entries_c57_component_by_kind'],
    'an opening basis carried as goods' => [['entry_kind' => 'OPENING_COST', 'cost_component' => 'GOODS'], 'lot_cost_entries_c57_component_by_kind'],
    'a basis reversal carried as goods' => [['entry_kind' => 'BASIS_REVERSAL', 'cost_component' => 'GOODS'], 'lot_cost_entries_c57_component_by_kind'],
    'a charge without its charge' => [['entry_kind' => 'COST_CORRECTION', 'cost_component' => 'CHARGE', 'allocation_version' => 1], 'lot_cost_entries_c57_charge_references'],
    'a goods row with a version' => [['entry_kind' => 'COST_CORRECTION', 'cost_component' => 'GOODS', 'allocation_version' => 1], 'lot_cost_entries_c57_charge_references'],
    'an unknown kind' => [['entry_kind' => 'FREIGHT'], 'lot_cost_entries_entry_kind_code'],
]);

it('keys a charge row to its charge, share or re-spread line within the lot\'s source company (C-32; C-57)', function () {
    $share = PurchaseChargeLineShare::factory()->create();
    $charge = ['source_company_id' => $share->company_id, 'cost_component' => 'CHARGE', 'purchase_charge_id' => $share->purchase_charge_id, 'allocation_version' => 1];
    $shareRow = ['entry_kind' => 'CHARGE_SHARE', ...$charge, 'purchase_charge_line_share_id' => $share->id];

    Refusal::assert(Refusal::CHECK, 'lot_cost_entries_c57_charge_references', fn () => LotCostEntry::factory()->create(['entry_kind' => 'CHARGE_SHARE', ...$charge, 'respread_from_purchase_line_id' => $share->purchase_line_id]));
    Refusal::assert(Refusal::CHECK, 'lot_cost_entries_c57_charge_references', fn () => LotCostEntry::factory()->create([...$shareRow, 'respread_from_purchase_line_id' => $share->purchase_line_id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_entries_c32_charge_foreign', fn () => LotCostEntry::factory()->create([...$shareRow, 'source_company_id' => Company::factory()->create()->id]));

    $lot = LotCostEntry::factory()->create($shareRow)->lot_id;
    $respread = LotCostEntry::factory()->create(['lot_id' => $lot, 'entry_kind' => 'CHARGE_RESPREAD', ...$charge, 'respread_from_purchase_line_id' => $share->purchase_line_id]);

    Refusal::assert(Refusal::UNIQUE, 'lot_cost_entries_one_charge_share', fn () => LotCostEntry::factory()->create([...$shareRow, 'lot_id' => $lot]));

    expect($respread->exists)->toBeTrue();
});

it('posts one basis and one non-creditable tax row per lot, never twice (BR-INV-04; BR-FIN-17)', function () {
    $line = PurchaseLine::factory()->create();
    $goods = ['source_company_id' => $line->company_id, 'purchase_line_id' => $line->id];
    $lot = LotCostEntry::factory()->create(['entry_kind' => 'GOODS', 'cost_component' => 'GOODS', ...$goods])->lot_id;
    LotCostEntry::factory()->create(['lot_id' => $lot, 'entry_kind' => 'NONCREDITABLE_TAX', 'cost_component' => 'NONCREDITABLE_TAX', ...$goods]);

    Refusal::assert(Refusal::UNIQUE, 'lot_cost_entries_one_basis', fn () => LotCostEntry::factory()->create(['lot_id' => $lot, 'entry_kind' => 'OPENING_COST', 'cost_component' => 'BASIS']));
    Refusal::assert(Refusal::UNIQUE, 'lot_cost_entries_one_noncreditable_tax', fn () => LotCostEntry::factory()->create(['lot_id' => $lot, 'entry_kind' => 'NONCREDITABLE_TAX', 'cost_component' => 'NONCREDITABLE_TAX', ...$goods]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_entries_c32_purchase_line_foreign', fn () => LotCostEntry::factory()->create(['entry_kind' => 'GOODS', 'cost_component' => 'GOODS', 'purchase_line_id' => $line->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_entries_c32_correction_case_foreign', fn () => LotCostEntry::factory()->create(['lot_id' => $lot, 'entry_kind' => 'COST_CORRECTION', 'cost_component' => 'GOODS', ...$goods, 'correction_case_id' => CorrectionCase::factory()->create()->id]));

    // Corrections and reversals add rows; the basis stays one (§6).
    LotCostEntry::factory()->create(['lot_id' => $lot, 'entry_kind' => 'COST_CORRECTION', 'cost_component' => 'GOODS', 'amount' => '-25000.00', ...$goods]);

    expect(LotCostEntry::query()->where('lot_id', $lot)->count())->toBe(3);
});

it('conserves a lot\'s cost: never negative, none left once no quantity is (C-26; BR-FIN-13)', function () {
    DB::table('lot_cost_balances')->insert(['lot_id' => 7001, 'remaining_qty_base' => 10, 'remaining_cost' => '500000.00']);
    $guard = fn () => DB::table('lot_cost_balances')->where('lot_id', 7001);

    Refusal::assert(Refusal::CHECK, 'lot_cost_balances_c26_conservation', fn () => $guard()->update(['remaining_qty_base' => 0]));
    Refusal::assert(Refusal::CHECK, 'lot_cost_balances_c26_conservation', fn () => $guard()->update(['remaining_cost' => '-0.01']));

    // The zeroing draw takes exactly the remaining cost.
    $guard()->update(['remaining_qty_base' => 0, 'remaining_cost' => '0.00']);

    expect($guard()->value('remaining_cost'))->toBe('0.00');
});

it('attributes from exactly one source, a lot draw with its lot and no component, a direct one with its component (§4.8; D-DB-10)', function (array $attributes, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => CostAttribution::factory()->create($attributes));
})->with([
    'nothing' => [['amount' => '0.00'], 'cost_attributions_amount_not_zero'],
    'two sources' => [['dropship_confirmation_line_id' => 9001], 'cost_attributions_one_source'],
    'a direct row without its component' => [['cost_component' => null], 'cost_attributions_lot_draw'],
    'a lot draw with a component' => [['purchase_line_id' => null, 'stock_consumption_id' => 9001, 'lot_id' => 7001, 'source_company_id' => null, 'bearer_company_id' => Company::factory()], 'cost_attributions_lot_draw'],
    'a direct row naming a lot' => [['lot_id' => 7001], 'cost_attributions_lot_draw'],
    'a charge without its charge' => [['cost_component' => 'CHARGE', 'allocation_version' => 1], 'cost_attributions_c57_charge_references'],
    'an unknown cause' => [['cause' => 'GIFT'], 'cost_attributions_cause_code'],
]);

it('attributes HPP once per consumption, and per component and charge of a direct line (C-25; L-18)', function () {
    $company = Company::factory()->create()->id;
    $draw = ['purchase_line_id' => null, 'cost_component' => null, 'stock_consumption_id' => 9001, 'lot_id' => 7001, 'source_company_id' => $company, 'bearer_company_id' => $company];
    CostAttribution::factory()->create($draw);
    $direct = CostAttribution::factory()->create();
    $line = ['purchase_line_id' => $direct->purchase_line_id, 'bearer_company_id' => $direct->bearer_company_id];

    Refusal::assert(Refusal::UNIQUE, 'cost_attributions_c25_one_initial_per_consumption', fn () => CostAttribution::factory()->create($draw));
    // A NULL charge counts as one value, so a goods row cannot repeat.
    Refusal::assert(Refusal::UNIQUE, 'cost_attributions_c25_one_initial_per_purchase_line', fn () => CostAttribution::factory()->create($line));
    Refusal::assert(Refusal::UNIQUE, 'cost_attributions_c25_one_initial_per_dropship_line', function () use ($company): void {
        $once = ['purchase_line_id' => null, 'dropship_confirmation_line_id' => 9002, 'cost_component' => 'GOODS', 'bearer_company_id' => $company];
        CostAttribution::factory()->create($once);
        CostAttribution::factory()->create($once);
    });

    // Other components, and contras of another cause, add rows.
    CostAttribution::factory()->create([...$line, 'cost_component' => 'NONCREDITABLE_TAX']);
    CostAttribution::factory()->create([...$draw, 'cause' => 'RESTORATION_CONTRA', 'amount' => '-100000.00']);

    expect(CostAttribution::query()->where('purchase_line_id', $direct->purchase_line_id)->count())->toBe(2);
});

it('bears a cost in another company than the lot\'s only with its reason, its project and case within their companies (C-28; L-44; §15)', function () {
    [$source, $bearer] = [Company::factory()->create()->id, Company::factory()->create()->id];
    $draw = ['purchase_line_id' => null, 'cost_component' => null, 'stock_consumption_id' => 9101, 'lot_id' => 7101, 'source_company_id' => $source, 'bearer_company_id' => $bearer];

    Refusal::assert(Refusal::CHECK, 'cost_attributions_c28_allocation_reason', fn () => CostAttribution::factory()->create($draw));
    Refusal::assert(Refusal::FOREIGN_KEY, 'cost_attributions_c32_bearer_project_foreign', fn () => CostAttribution::factory()->create([...$draw, 'allocation_reason' => 'Barang PT A dipakai proyek PT B', 'bearer_project_id' => Project::factory()->create(['company_id' => $source])->id]));
    Refusal::assert(Refusal::CHECK, 'cost_attributions_case_side', fn () => CostAttribution::factory()->create([...$draw, 'allocation_reason' => 'Barang PT A dipakai proyek PT B', 'correction_case_id' => CorrectionCase::factory()->create()->id, 'case_company_id' => Company::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'cost_attributions_case_pair', fn () => CostAttribution::factory()->create([...$draw, 'allocation_reason' => 'Barang PT A dipakai proyek PT B', 'case_company_id' => $source]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'cost_attributions_c32_correction_case_foreign', fn () => CostAttribution::factory()->create([...$draw, 'allocation_reason' => 'Barang PT A dipakai proyek PT B', 'correction_case_id' => CorrectionCase::factory()->create(['company_id' => $bearer])->id, 'case_company_id' => $source]));

    $allocated = CostAttribution::factory()->create([...$draw, 'allocation_reason' => 'Barang PT A dipakai proyek PT B', 'bearer_project_id' => Project::factory()->create(['company_id' => $bearer])->id,
        'correction_case_id' => CorrectionCase::factory()->create(['company_id' => $source])->id, 'case_company_id' => $source]);

    expect($allocated->bearerProject?->company_id)->toBe($bearer);
});

it('allocates or expenses a charge, never both and never more than it, and a share likewise (C-21; L-47; C-57)', function () {
    $share = PurchaseChargeLineShare::factory()->create();
    DB::table('purchase_charge_balances')->insert(['purchase_charge_id' => $share->purchase_charge_id, 'amount' => '50000.00', 'allocated_amount' => '30000.00']);
    DB::table('purchase_charge_share_balances')->insert(['purchase_charge_id' => $share->purchase_charge_id, 'purchase_line_id' => $share->purchase_line_id, 'allocation_version' => 1, 'share_amount' => '50000.00', 'posted_amount' => '30000.00']);
    $charge = fn () => DB::table('purchase_charge_balances')->where('purchase_charge_id', $share->purchase_charge_id);
    $line = fn () => DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $share->purchase_charge_id);

    Refusal::assert(Refusal::CHECK, 'purchase_charge_balances_c21_conservation', fn () => $charge()->update(['expensed_amount' => '20000.01']));
    Refusal::assert(Refusal::CHECK, 'purchase_charge_balances_c21_conservation', fn () => $charge()->update(['amount' => '29999.99']));
    Refusal::assert(Refusal::CHECK, 'purchase_charge_share_balances_c21_share_cap', fn () => $line()->update(['respread_out_amount' => '15000.00', 'expensed_out_amount' => '5000.01']));
    // The share balance names a version the share rows hold.
    Refusal::assert(Refusal::FOREIGN_KEY, 'purchase_charge_share_balances_current_share_foreign', fn () => $line()->update(['allocation_version' => 2]));

    $charge()->update(['expensed_amount' => '20000.00']);

    expect($charge()->value('expensed_amount'))->toBe('20000.00');
});

it('expenses a fee only from its settlement with its fee advice, and a tax with its proof, of the expense\'s company (C-23; BR-FIN-16; C-58)', function (Closure $write, string $constraint, string $state = Refusal::CHECK) {
    Refusal::assert($state, $constraint, $write);
})->with([
    'a fee without its evidence' => [fn () => Expense::factory()->create(['kind' => 'FEE_DEDUCTION', 'deduction_settlement_id' => 9201]), 'expenses_c58_evidence_by_kind'],
    'a fee without its settlement' => [function () {
        $advice = EvidenceDocument::factory()->create(['evidence_type' => 'FEE_ADVICE']);

        return Expense::factory()->create(['company_id' => $advice->company_id, 'kind' => 'FEE_DEDUCTION', 'evidence_document_id' => $advice->id, 'evidence_type' => 'FEE_ADVICE']);
    }, 'expenses_c23_fee_from_settlement'],
    'a fee on a client order' => [function () {
        $order = EvidenceDocument::factory()->create();

        return Expense::factory()->create(['company_id' => $order->company_id, 'kind' => 'FEE_DEDUCTION', 'deduction_settlement_id' => 9201, 'evidence_document_id' => $order->id, 'evidence_type' => 'CLIENT_ORDER']);
    }, 'expenses_c58_evidence_by_kind'],
    'a tax on a fee advice' => [function () {
        $advice = EvidenceDocument::factory()->create(['evidence_type' => 'FEE_ADVICE']);

        return Expense::factory()->create(['company_id' => $advice->company_id, 'kind' => 'TAX_TREATMENT', 'evidence_document_id' => $advice->id, 'evidence_type' => 'FEE_ADVICE']);
    }, 'expenses_c58_evidence_by_kind'],
    'an evidence typed otherwise than it is' => [function () {
        $order = EvidenceDocument::factory()->create();

        return Expense::factory()->create(['company_id' => $order->company_id, 'kind' => 'FEE_DEDUCTION', 'deduction_settlement_id' => 9201, 'evidence_document_id' => $order->id, 'evidence_type' => 'FEE_ADVICE']);
    }, 'expenses_c58_evidence_type_foreign', Refusal::FOREIGN_KEY],
    'another company\'s fee advice' => [fn () => Expense::factory()->create(['kind' => 'FEE_DEDUCTION', 'deduction_settlement_id' => 9201, 'evidence_document_id' => EvidenceDocument::factory()->create(['evidence_type' => 'FEE_ADVICE'])->id, 'evidence_type' => 'FEE_ADVICE']), 'expenses_c32_evidence_foreign', Refusal::FOREIGN_KEY],
    'an evidence without its type' => [function () {
        $advice = EvidenceDocument::factory()->create(['evidence_type' => 'FEE_ADVICE']);

        return Expense::factory()->create(['company_id' => $advice->company_id, 'kind' => 'FEE_DEDUCTION', 'deduction_settlement_id' => 9201, 'evidence_document_id' => $advice->id]);
    }, 'expenses_typed_pairs'],
    'a manual expense citing a bill as its evidence' => [function () {
        $bill = EvidenceDocument::factory()->supplierBill()->create();

        return Expense::factory()->create(['company_id' => $bill->company_id, 'evidence_document_id' => $bill->id, 'evidence_type' => 'SUPPLIER_BILL']);
    }, 'expenses_evidence_fee_or_tax'],
    'a purchase charge expense without its charge' => [fn () => Expense::factory()->create(['kind' => 'PURCHASE_CHARGE']), 'expenses_charge_when_purchase_charge'],
    'a fallback line on a manual expense' => [fn () => Expense::factory()->create(['purchase_line_id' => 9301]), 'expenses_fallback_line'],
    'an expense of nothing' => [fn () => Expense::factory()->create(['amount' => '0.00']), 'expenses_amount_positive'],
    'another company\'s project' => [fn () => Expense::factory()->create(['project_id' => Project::factory()->create()->id]), 'expenses_c32_project_foreign', Refusal::FOREIGN_KEY],
]);

it('cites a supplier\'s bill once, by its charge or by the expense, within the company (L-43; C-20; C-58)', function () {
    $charge = PurchaseCharge::factory()->create(['classification' => 'NON_ATTRIBUTABLE', 'allocation_basis' => null]);
    $bill = EvidenceDocument::factory()->supplierBill()->create(['company_id' => $charge->company_id]);
    $own = ['company_id' => $charge->company_id];

    Refusal::assert(Refusal::CHECK, 'expenses_c20_bill_cited_once', fn () => Expense::factory()->create([...$own, 'kind' => 'PURCHASE_CHARGE', 'purchase_charge_id' => $charge->id, 'source_bill_evidence_id' => $bill->id, 'source_bill_evidence_type' => 'SUPPLIER_BILL']));
    Refusal::assert(Refusal::CHECK, 'expenses_c58_source_bill', fn () => Expense::factory()->create([...$own, 'source_bill_evidence_id' => $bill->id, 'source_bill_evidence_type' => 'CLIENT_ORDER']));
    Refusal::assert(Refusal::FOREIGN_KEY, 'expenses_c32_charge_foreign', fn () => Expense::factory()->create(['kind' => 'PURCHASE_CHARGE', 'purchase_charge_id' => $charge->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'expenses_c32_source_bill_foreign', fn () => Expense::factory()->create(['source_bill_evidence_id' => $bill->id, 'source_bill_evidence_type' => 'SUPPLIER_BILL']));

    $expensed = Expense::factory()->create([...$own, 'kind' => 'PURCHASE_CHARGE', 'purchase_charge_id' => $charge->id, 'amount' => '50000.00']);
    $direct = Expense::factory()->create([...$own, 'source_bill_evidence_id' => $bill->id, 'source_bill_evidence_type' => 'SUPPLIER_BILL']);

    expect($expensed->charge?->is($charge))->toBeTrue()
        ->and($direct->sourceBill?->is($bill))->toBeTrue();
});

it('posts a share only under its own charge and version (DIR-090 (3); APPR-019)', function () {
    $share = PurchaseChargeLineShare::factory()->create();
    $other = PurchaseCharge::factory()->create(['purchase_id' => $share->charge->purchase_id]);
    $row = ['entry_kind' => 'CHARGE_SHARE', 'source_company_id' => $share->company_id, 'cost_component' => 'CHARGE', 'purchase_charge_line_share_id' => $share->id];

    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_entries_share_of_charge_version_foreign', fn () => LotCostEntry::factory()->create([...$row, 'purchase_charge_id' => $other->id, 'allocation_version' => 1]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'lot_cost_entries_share_of_charge_version_foreign', fn () => LotCostEntry::factory()->create([...$row, 'purchase_charge_id' => $share->purchase_charge_id, 'allocation_version' => 2]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'cost_attributions_share_of_charge_version_foreign', fn () => CostAttribution::factory()->create([
        'cost_component' => 'CHARGE', 'purchase_line_id' => $share->purchase_line_id, 'bearer_company_id' => $share->company_id,
        'purchase_charge_id' => $other->id, 'purchase_charge_line_share_id' => $share->id, 'allocation_version' => 1,
    ]));

    expect(LotCostEntry::factory()->create([...$row, 'purchase_charge_id' => $share->purchase_charge_id, 'allocation_version' => 1])->exists)->toBeTrue();
});

it('bears a direct cost in the purchase\'s company, a return settlement aside (DIR-090 (2); APPR-019; §15)', function () {
    $line = PurchaseLine::factory()->create(['kind' => 'NONSTOCK']);
    $other = Company::factory()->create()->id;

    Refusal::assert(Refusal::CHECK, 'cost_attributions_direct_within_bearer_company', fn () => CostAttribution::factory()->create(['purchase_line_id' => $line->id, 'bearer_company_id' => $other]));

    // A RETURN_SETTLEMENT loss is borne by its case's causal project, of another company (§4.8; §15).
    expect(CostAttribution::factory()->create(['purchase_line_id' => $line->id, 'bearer_company_id' => $other, 'cost_class' => 'LOSS', 'cause' => 'RETURN_SETTLEMENT'])->exists)->toBeTrue()
        ->and(CostAttribution::factory()->create(['purchase_line_id' => $line->id])->bearer_company_id)->toBe($line->company_id);
});

it('expenses a fallback share only for a line of its charge\'s purchase (DIR-090 (3); APPR-019)', function () {
    $charge = PurchaseCharge::factory()->create(['classification' => 'NON_ATTRIBUTABLE', 'allocation_basis' => null]);
    $fallback = ['company_id' => $charge->company_id, 'kind' => 'PURCHASE_CHARGE', 'purchase_charge_id' => $charge->id];
    $sibling = PurchaseLine::factory()->create(['purchase_id' => Purchase::factory()->create(['company_id' => $charge->company_id])->id]);

    Refusal::assert(Refusal::CHECK, 'expenses_fallback_line_of_charge_purchase', fn () => Expense::factory()->create([...$fallback, 'purchase_line_id' => $sibling->id]));

    expect(Expense::factory()->create([...$fallback, 'purchase_line_id' => PurchaseLine::factory()->create(['purchase_id' => $charge->purchase_id])->id])->exists)->toBeTrue();
});
