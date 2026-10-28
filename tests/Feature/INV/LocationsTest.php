<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-028; ROUTE-057, ROUTE-148; criteria 2–5): a rack created with its code, a duplicate answered at the field;
// its name and active state edited at the version read, a stale edit CONFLICT; a product's minimum set in base units at
// its scale, creating no purchase, reservation or movement; racks of no company, seen by every `stock.view` holder.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->manager = Authorization::admin([$this->company->id], ['stock.view', 'warehouse.manage']);
});

afterEach(fn () => @unlink($this->hotFile));

function locationAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function saveLocation(object $test, array $body): TestResponse
{
    return $test->actingAs($test->manager)->post('/data-master/lokasi-rak', ['command_id' => (string) Str::uuid(), ...$body]);
}

it('creates a rack with its code, a duplicate answered at the field (criterion 2; BD-16)', function () {
    saveLocation($this, ['kind' => 'create', 'code' => 'a-01', 'name' => 'Rak A baris 1'])->assertRedirect('/data-master/lokasi-rak');

    expect(locationAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('locations')->where('code', 'A-01')->value('name'))->toBe('Rak A baris 1')
        ->and(DB::table('audit_events')->where('action', 'INV.LocationCreated')->value('company_id'))->toBeNull();

    saveLocation($this, ['kind' => 'create', 'code' => 'A-01', 'name' => 'Lain'])->assertSessionHasErrors(['code' => 'Kode ini sudah dipakai rak lain.']);
    saveLocation($this, ['kind' => 'create', 'code' => '', 'name' => ''])->assertSessionHasErrors(['code', 'name']);
    expect(DB::table('locations')->count())->toBe(1);
});

it('edits a rack\'s name and active state at the version read, a stale edit CONFLICT (criterion 4; ST-01)', function () {
    saveLocation($this, ['kind' => 'create', 'code' => 'B-01', 'name' => 'Rak B']);

    saveLocation($this, ['kind' => 'edit', 'code' => 'B-01', 'name' => 'Rak B atas', 'active' => false, 'lock_version' => 0])->assertRedirect();
    expect(locationAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('locations')->where('code', 'B-01')->first(['name', 'is_active']))->toEqual((object) ['name' => 'Rak B atas', 'is_active' => false]);

    saveLocation($this, ['kind' => 'edit', 'code' => 'B-01', 'name' => 'Rak B lama', 'active' => true, 'lock_version' => 0])->assertRedirect();
    expect(locationAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'cause' => 'stale']);
    saveLocation($this, ['kind' => 'edit', 'code' => 'TIDAK-ADA', 'name' => 'X', 'active' => true, 'lock_version' => 0])->assertNotFound();
});

it('sets a minimum in base units at the product\'s scale, creating no purchase, reservation or movement (criterion 3; BR-INV-10)', function () {
    $product = Product::factory()->create(['quantity_scale' => 0])->refresh();
    $counts = fn (): array => [DB::table('purchases')->count(), DB::table('reservations')->count(), DB::table('stock_movements')->count()];
    $before = $counts();

    saveLocation($this, ['kind' => 'minimum', 'product' => $product->public_id, 'minimum' => '1.5', 'lock_version' => $product->lock_version])->assertSessionHasErrors('minimum');
    saveLocation($this, ['kind' => 'minimum', 'product' => $product->public_id, 'minimum' => '12', 'lock_version' => $product->lock_version])->assertRedirect();

    expect(locationAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($product->refresh()->min_stock_qty_base)->toBe('12.000')->and($counts())->toBe($before)
        ->and(DB::table('audit_events')->where('action', 'CAT.MinimumStockSet')->count())->toBe(1);

    saveLocation($this, ['kind' => 'minimum', 'product' => $product->public_id, 'minimum' => '20'])->assertRedirect();
    expect(locationAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'cause' => 'stale'])->and($product->refresh()->min_stock_qty_base)->toBe('12.000');

    saveLocation($this, ['kind' => 'minimum', 'product' => $product->public_id, 'minimum' => '', 'lock_version' => $product->lock_version])->assertRedirect();
    expect($product->refresh()->min_stock_qty_base)->toBeNull();
});

it('shows the racks and minimums to a stock.view holder of any company — pool records of none (criterion 5; CS-05)', function () {
    saveLocation($this, ['kind' => 'create', 'code' => 'C-01', 'name' => 'Rak C']);
    $product = Product::factory()->create(['min_stock_qty_base' => '4']);
    $other = Authorization::admin([Company::factory()->create()->id], ['stock.view']);

    $this->actingAs($other)->get('/data-master/lokasi-rak')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('INV/Locations', false)
            ->where('locations.rows.0', ['code' => 'C-01', 'name' => 'Rak C', 'active' => true, 'lock_version' => 0])
            ->where('minimums.rows.0.id', $product->public_id)->where('minimums.rows.0.minimum', '4.000')->where('can.manage', false));
});
