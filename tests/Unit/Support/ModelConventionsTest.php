<?php

use App\Modules\ADM\Models\AdminRequirementWaiver;
use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductPriceHistory;
use App\Modules\CST\Models\CostAttribution;
use App\Modules\CST\Models\Expense;
use App\Modules\CST\Models\LotCostBalance;
use App\Modules\CST\Models\LotCostEntry;
use App\Modules\CST\Models\PurchaseChargeBalance;
use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceBalance;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\DispatchLine;
use App\Modules\INV\Models\InventoryLot;
use App\Modules\INV\Models\ReceivingLine;
use App\Modules\INV\Models\ReservationEvent;
use App\Modules\INV\Models\StockConsumption;
use App\Modules\INV\Models\StockCount;
use App\Modules\INV\Models\StockCountLine;
use App\Modules\INV\Models\StockMovement;
use App\Modules\INV\Models\StockMovementEntry;
use App\Modules\INV\Models\StockProductBalance;
use App\Modules\INV\Models\StockRestoration;
use App\Modules\INV\Models\UnattributedLossCandidate;
use App\Modules\INV\Models\UnattributedLossCase;
use App\Modules\INV\Models\UnattributedLossResolution;
use App\Modules\INV\Models\UnattributedLossResolutionLine;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\OPS\Models\SecurityEvent;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\RetiredNumber;
use App\Modules\ORG\Models\TaxRateVersion;
use App\Modules\PRJ\Models\CommercialConfirmation;
use App\Modules\PRJ\Models\ConfirmationLinePin;
use App\Modules\PRJ\Models\LineScopeCancellation;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectCommercialBalance;
use App\Modules\PRJ\Models\ProjectLineBalance;
use App\Modules\PRJ\Models\ProjectSiplahDetail;
use App\Modules\PRJ\Models\ProjectStateTransition;
use App\Modules\PRJ\Models\QuotationRevision;
use App\Modules\PRJ\Models\QuotationRevisionLine;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseChargeLineShare;
use App\Modules\PUR\Models\PurchaseLine;
use App\Modules\PUR\Models\PurchaseLineAdjustment;
use App\Modules\PUR\Models\PurchaseLineBalance;
use App\Modules\PUR\Models\PurchaseLineClosure;
use App\Modules\PUR\Models\PurchaseLineTaxComponent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// TL-03 (TASK-005–TASK-008): the casts and value conventions of DATABASE §16–§17 on the IAM, OPS, ORG,
// PTY, CAT, DOC, PRJ, PUR, CST, ADM and INV models — UUIDv7 public identifiers beside the internal key, money,
// quantities and rates as exact decimal strings never a float, a one-way password hash, append-only
// models that refuse a change (C-53), and the guards keyed by the record they guard.

it('generates a UUIDv7 public identifier beside the internal key', function (string $model) {
    $instance = new $model;

    expect($instance->uniqueIds())->toBe(['public_id'])
        ->and(Str::isUuid($instance->newUniqueId(), 7))->toBeTrue()
        ->and($instance->getKeyName())->toBe('id')
        ->and($instance->getIncrementing())->toBeTrue();
})->with([
    User::class, CorrectionCase::class, ImportBatch::class, Company::class, ClientOrganization::class, Supplier::class, Product::class,
    Document::class, DocumentVersion::class, EvidenceDocument::class, FileObject::class, Project::class, QuotationRevision::class, Purchase::class,
    StockMovement::class, StockCount::class, UnattributedLossCase::class,
]);

it('carries money as an exact decimal string with two places', function (string $given, string $carried) {
    $case = new CorrectionCase;
    $case->returned_cost = $given;

    expect($case->returned_cost)->toBe($carried)->toBeString();
})->with([
    'whole rupiah' => ['1500000', '1500000.00'],
    'sen' => ['0.5', '0.50'],
    'beyond a float\'s exact range' => ['9999999999999999.99', '9999999999999999.99'],
]);

it('carries each money, quantity and rate attribute as an exact decimal string at its scale', function (string $model, string $attribute, string $given, string $carried) {
    $instance = new $model;
    $instance->{$attribute} = $given;

    expect($instance->{$attribute})->toBe($carried)->toBeString();
})->with([
    'a default price' => [ProductPriceHistory::class, 'amount', '1500000', '1500000.00'],
    'a Kuitansi amount' => [DocumentVersion::class, 'kuitansi_amount', '0.5', '0.50'],
    'a proven amount beyond a float\'s exact range' => [EvidenceDocument::class, 'proven_amount', '9999999999999999.99', '9999999999999999.99'],
    'a bank line' => [BankStatementLine::class, 'amount', '1500000.5', '1500000.50'],
    'a cited amount' => [EvidenceBalance::class, 'cited_amount', '1', '1.00'],
    'a minimum stock quantity' => [Product::class, 'min_stock_qty_base', '1.5', '1.500'],
    'a tax rate' => [TaxRateVersion::class, 'rate_percent', '11', '11.000000'],
    'a pinned base quantity' => [ConfirmationLinePin::class, 'qty_base', '2.25', '2.250'],
    'a quoted sales value' => [QuotationRevisionLine::class, 'sales_value', '1500000', '1500000.00'],
    'a line\'s demand' => [ProjectLineBalance::class, 'demand_qty_base', '10', '10.000'],
    'a purchase line\'s value' => [PurchaseLine::class, 'line_value', '9999999999999999.99', '9999999999999999.99'],
    'a purchase line\'s tax rate' => [PurchaseLineTaxComponent::class, 'rate_percent', '11', '11.000000'],
    'a charge share\'s basis' => [PurchaseChargeLineShare::class, 'basis_value', '12.5', '12.500'],
    'a posted goods value' => [PurchaseLineBalance::class, 'posted_goods_value', '0.5', '0.50'],
    'a signed lot cost' => [LotCostEntry::class, 'amount', '-1500.5', '-1500.50'],
    'a lot\'s remaining quantity' => [LotCostBalance::class, 'remaining_qty_base', '3', '3.000'],
    'an expense' => [Expense::class, 'amount', '250000', '250000.00'],
    'a ledger delta' => [StockMovementEntry::class, 'qty_delta_base', '-1.25', '-1.250'],
    'a product\'s on-hand quantity' => [StockProductBalance::class, 'on_hand_qty', '7', '7.000'],
    'a candidate\'s unit cost' => [UnattributedLossCandidate::class, 'net_unit_cost_at_recognition', '12000', '12000.00'],
]);

it('keys each guard by the record it guards, never an identity of its own (DATABASE §4.5–§4.8)', function (string $model, string $key) {
    $instance = new $model;

    expect($instance->getKeyName())->toBe($key)
        ->and($instance->getIncrementing())->toBeFalse();
})->with([
    [ProjectLineBalance::class, 'project_line_id'], [ProjectCommercialBalance::class, 'project_id'], [PurchaseLineBalance::class, 'purchase_line_id'],
    [LotCostBalance::class, 'lot_id'], [PurchaseChargeBalance::class, 'purchase_charge_id'], [ProjectSiplahDetail::class, 'project_id'],
    [StockProductBalance::class, 'product_id'],
]);

it('stores the password only as a one-way hash and never serializes a credential', function () {
    $user = new User;
    $user->password = 'kata-sandi-contoh';
    $user->two_factor_secret = 'eyJpdiI6ImNvbnRvaCJ9';

    expect($user->password)->not->toBe('kata-sandi-contoh')
        ->and(Hash::check('kata-sandi-contoh', $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes'])
        ->and($user->getRememberTokenName())->toBe('');
});

it('refuses to update or delete a saved append-only row at the model (C-53)', function (string $model) {
    $row = (new $model)->forceFill(['id' => 1]);
    $row->exists = true;
    $row->syncOriginal();

    expect(fn () => $row->forceFill(['recorded_at' => now()->subYear()])->save())->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $row->delete())->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $row->increment('id'))->toThrow(LogicException::class, 'append-only');
})->with([
    AuditEvent::class, SecurityEvent::class, LegacyArchiveItem::class, RetiredNumber::class, ProductPriceHistory::class, FileObject::class,
    EvidenceDocumentVersion::class, CommercialConfirmation::class, ConfirmationLinePin::class, LineScopeCancellation::class,
    ProjectStateTransition::class, PurchaseCharge::class, PurchaseChargeLineShare::class, PurchaseLineClosure::class, PurchaseLineAdjustment::class,
    LotCostEntry::class, CostAttribution::class, Expense::class, AdminRequirementWaiver::class, InventoryLot::class, StockMovement::class,
    StockMovementEntry::class, ReceivingLine::class, DispatchLine::class, StockConsumption::class, StockRestoration::class, ReservationEvent::class,
    StockCountLine::class, UnattributedLossCandidate::class, UnattributedLossResolution::class, UnattributedLossResolutionLine::class,
]);
