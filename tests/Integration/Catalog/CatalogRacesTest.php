<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CatalogRaces;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;

// TL-05 (TASK-027; BD-16; C-01; L-35; LK-13; ST-09; criteria 4, 8): under real PostgreSQL sessions — two creations of
// one SKU and two registrations of one code give one COMMITTED and one CONFLICT; the serial switch waits on a receipt
// holding the product's guard and, reading ON HAND under it, is REJECTED with the registration-count path; with no
// stock it commits, and switching it off keeps the serial history.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $company = Company::factory()->create();
    // Two admins: each session's command holds its own actor's row at LK-03 (RV-01).
    $this->first = Authorization::admin([$company->id], ['products.edit']);
    $this->second = Authorization::admin([$company->id], ['products.edit']);
});

it('commits one of two concurrent creations of one SKU and answers the other CONFLICT (criterion 8; BD-16)', function () {
    [$first, $second] = [$this->first->id, $this->second->id];

    $results = Sessions::run([
        'first' => static fn (): string => CatalogRaces::holding('the first SKU is written', static fn (): string => CatalogRaces::create($first, 'SKU-RACE')),
        'second' => static function () use ($second): string {
            Barrier::await('the first SKU is written');

            return CatalogRaces::create($second, 'sku-race');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toStartWith('CONFLICT')
        ->and(DB::table('products')->whereRaw('lower(sku) = ?', ['sku-race'])->count())->toBe(1);
});

it('commits one of two concurrent registrations of one code and answers the other CONFLICT (criterion 8; C-01)', function () {
    [$first, $second] = [$this->first->id, $this->second->id];
    CatalogRaces::create($first, 'SKU-A');
    CatalogRaces::create($first, 'SKU-B');
    [$a, $b] = [Product::query()->where('sku', 'SKU-A')->value('id'), Product::query()->where('sku', 'SKU-B')->value('id')];

    $results = Sessions::run([
        'first' => static fn (): string => CatalogRaces::holding('the first code is written', static fn (): string => CatalogRaces::register($first, $a, '899 1000 1')),
        'second' => static function () use ($second, $b): string {
            Barrier::await('the first code is written');

            return CatalogRaces::register($second, $b, '89910001');
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'CONFLICT C-01'])
        ->and(DB::table('product_barcodes')->where('code_key', '89910001')->where('is_active', true)->count())->toBe(1);
});

it('refuses the serial switch once a receipt holding the guard has put stock on hand (criterion 4; L-35; ST-09)', function () {
    [$first, $second] = [$this->first->id, $this->second->id];
    CatalogRaces::create($first, 'SKU-SERIAL');
    $product = (int) Product::query()->where('sku', 'SKU-SERIAL')->value('id');

    $results = Sessions::run([
        'receipt' => static fn (): string => CatalogRaces::receiveHolding($product, '5'),
        'switch' => static function () use ($second, $product): string {
            Barrier::await('the receipt holds the guard');

            return CatalogRaces::serialize($second, $product);
        },
    ]);

    expect($results)->toBe(['receipt' => 'RECEIVED', 'switch' => 'REJECTED L-35'])
        ->and(Product::query()->findOrFail($product)->is_serialized)->toBeFalse();
});

it('switches a product without stock to serialized, and off again keeping its serial history (criterion 4; L-35)', function () {
    CatalogRaces::create($this->first->id, 'SKU-OFF');
    $product = Product::query()->where('sku', 'SKU-OFF')->firstOrFail();

    expect(CatalogRaces::serialize($this->first->id, $product->id))->toBe('COMMITTED');
    $product->refresh();
    // A serial already dispatched — history, holding no lot or place (C-02).
    DB::table('serial_units')->insert(['product_id' => $product->id, 'serial_number' => 'SN-0001', 'serial_key' => 'SN-0001', 'state' => 'DISPATCHED']);

    $off = app(App\Modules\CAT\Actions\UpdateProduct::class)->policy((string) Illuminate\Support\Str::uuid(), $this->first->id, $product, $product->lock_version, false, 0);

    expect($off->kind)->toBe('COMMITTED')->and($product->refresh()->is_serialized)->toBeFalse()
        ->and(DB::table('serial_units')->where('product_id', $product->id)->count())->toBe(1);
});
