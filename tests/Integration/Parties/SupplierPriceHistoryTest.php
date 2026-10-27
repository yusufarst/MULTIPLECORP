<?php

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PTY\Queries\SupplierDirectory;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-026; PJ-09; BR-ACC-02; REF-011; SM-25; criterion 5): a supplier's purchase-price history derived from the
// purchases of two companies — each company's latest actual price and date per product, from its own purchases only,
// never pooled —, shown with `cost.view` for the companies in scope and to no one else; a cancelled purchase is no
// price.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->first = Company::factory()->create(['code' => 'AAA']);
    $this->second = Company::factory()->create(['code' => 'BBB']);
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create(['name' => 'Kertas HVS A4']);
});

/** A purchase line of the supplier for the company, at the price and date. */
function boughtAt(Company $company, Supplier $supplier, Product $product, string $price, string $date, array $purchase = []): void
{
    PurchaseLine::factory()->for(Purchase::factory()->for($company)->for($supplier)->create(['business_date' => $date, ...$purchase]))
        ->for($product)->create(['unit_price' => $price]);
}

function priceHistory(User $viewer, Supplier $supplier): ?array
{
    return app(SupplierDirectory::class)->detail(app(EffectiveCapabilities::class)->fresh($viewer->id), $supplier)['prices'] ?? null;
}

it('shows each company its own latest price per product, never pooled, only with cost.view in scope (criterion 5; PJ-09)', function () {
    boughtAt($this->first, $this->supplier, $this->product, '50000.00', '2026-09-01');
    boughtAt($this->first, $this->supplier, $this->product, '52000.00', '2026-10-01');
    boughtAt($this->second, $this->supplier, $this->product, '61000.00', '2026-09-15');
    boughtAt($this->second, $this->supplier, $this->product, '10.00', '2026-10-20', ['state' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => Authorization::owner()->id, 'cancelled_reason' => 'Batal']);

    $both = priceHistory(Authorization::admin([$this->first->id, $this->second->id], ['cost.view']), $this->supplier);
    $first = priceHistory(Authorization::admin([$this->first->id], ['cost.view']), $this->supplier);

    expect($both)->toHaveCount(2)
        ->and($both[0])->toMatchArray(['company' => 'AAA', 'product' => 'Kertas HVS A4', 'unit_price' => '52000.00', 'date' => '2026-10-01'])
        ->and($both[1])->toMatchArray(['company' => 'BBB', 'unit_price' => '61000.00', 'date' => '2026-09-15'])
        ->and($first)->toHaveCount(1)->and($first[0]['company'])->toBe('AAA')
        ->and(priceHistory(Authorization::admin([$this->second->id], ['projects.view']), $this->supplier))->toBeNull()
        ->and(priceHistory(Authorization::admin([], ['cost.view']), $this->supplier))->toBeNull();
});

it('keeps no supplier price of its own — the history is read from purchases each time (DATABASE §4.3; BR-ACC-02)', function () {
    $viewer = Authorization::admin([$this->first->id], ['cost.view']);
    expect(priceHistory($viewer, $this->supplier))->toBe([]);

    boughtAt($this->first, $this->supplier, $this->product, '75000.00', '2026-10-05');

    expect(priceHistory($viewer, $this->supplier)[0]['unit_price'])->toBe('75000.00')
        ->and(Schema::hasColumn('suppliers', 'unit_price'))->toBeFalse()
        ->and(Schema::hasTable('supplier_prices'))->toBeFalse();
});
