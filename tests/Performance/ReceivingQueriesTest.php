<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\ReceivingQueries;
use App\Modules\INV\Queries\ScanLookup;
use App\Modules\PUR\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-039; QB-05; QB-06; PFM-01; PFM-02; PFM-20; PF-13): the scan lookup in a handful of statements, each index-served; the receiving worklist the same for one row and a full page, one company at a time; PG-022 the same for one line and many; one line's receipt bounded.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company);
    $this->goods = Projects::product();
    $this->rack = ReceivingWorld::rack();
});

afterEach(fn () => @unlink($this->hotFile));

function receivingStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function receivingPurchase(object $test, string $qty = '10'): Purchase
{
    return ReceivingWorld::purchase($test->company, $test->goods['product']->id, $test->goods['base']->id, $qty);
}

it('reads the scan lookup in few statements, none scanning a table (QB-06; PFM-01; WS-12)', function () {
    $authority = app(EffectiveCapabilities::class)->for($this->actor->id);
    $lookup = app(ScanLookup::class);

    $found = receivingStatements(fn () => $lookup->find($authority, $this->goods['product']->sku));
    $none = receivingStatements(fn () => $lookup->find($authority, 'TIDAK-ADA'));

    expect($found)->toBeLessThanOrEqual(5)->and($none)->toBeLessThanOrEqual(5);
});

it('reads the worklist in the same statements for one row and a full page, one company at a time (QB-05; PFM-01; PF-13; QS-05)', function () {
    $queries = app(ReceivingQueries::class);
    receivingPurchase($this);
    $one = receivingStatements(fn () => $queries->worklist($this->actor->id, $this->company->id, null));

    foreach (range(1, 5) as $ignored) {
        receivingPurchase($this);
    }

    $many = receivingStatements(fn () => $queries->worklist($this->actor->id, $this->company->id, null));
    expect($many)->toBe($one)->and($one)->toBeLessThanOrEqual(6);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $queries->worklist($this->actor->id, $this->company->id, null);
    $reads = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, '"purchases"'))->values();
    DB::disableQueryLog();
    expect($reads->first())->toContain('company_id');
});

it('reads PG-022 in the same statements after more receipts (PFM-02; QB-02)', function () {
    $queries = app(ReceivingQueries::class);
    $purchase = receivingPurchase($this, '100');
    $line = ReceivingWorld::line($purchase);
    $url = "/gudang/barang-masuk/{$purchase->public_id}/terima";
    $unit = $this->goods['base']->code;
    $this->actingAs($this->actor)->post($url, ReceivingWorld::request($line, $unit, $this->rack, '1'));
    $one = receivingStatements(fn () => $queries->screen($this->actor->id, $purchase->id, $this->company->id));

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->actor)->post($url, ReceivingWorld::request($line, $unit, $this->rack, '1'));
    }

    $many = receivingStatements(fn () => $queries->screen($this->actor->id, $purchase->id, $this->company->id));
    expect($many)->toBe($one)->and($one)->toBeLessThanOrEqual(20);
});

it('records one line\'s receipt in bounded statements, the same for the next (PFM-20; AX-04)', function () {
    $purchase = receivingPurchase($this, '100');
    $line = ReceivingWorld::line($purchase);
    $url = "/gudang/barang-masuk/{$purchase->public_id}/terima";
    $unit = $this->goods['base']->code;
    $first = receivingStatements(fn () => $this->actingAs($this->actor)->post($url, ReceivingWorld::request($line, $unit, $this->rack, '1')));
    $second = receivingStatements(fn () => $this->actingAs($this->actor)->post($url, ReceivingWorld::request($line, $unit, $this->rack, '1')));

    expect($first)->toBeLessThan(120)->and($second)->toBeLessThan(120);
});

it('adds a bounded number of statements per serial in one receipt (PFM-20; FU-04)', function () {
    DB::table('products')->where('id', $this->goods['product']->id)->update(['is_serialized' => true]);
    $purchase = receivingPurchase($this, '100');
    $line = ReceivingWorld::line($purchase);
    $url = "/gudang/barang-masuk/{$purchase->public_id}/terima";
    $unit = $this->goods['base']->code;
    $serials = fn (string $tag, int $count): array => array_map(fn (int $n): array => ['nomor' => "{$tag}-{$n}"], range(1, $count));
    $one = receivingStatements(fn () => $this->actingAs($this->actor)->post($url, ReceivingWorld::request($line, $unit, $this->rack, '1', more: ['seri' => $serials('A', 1)])));
    $ten = receivingStatements(fn () => $this->actingAs($this->actor)->post($url, ReceivingWorld::request($line, $unit, $this->rack, '10', more: ['seri' => $serials('B', 10)])));

    expect(DB::table('serial_units')->where('state', 'IN_STOCK')->count())->toBe(11)->and(($ten - $one) / 9)->toBeLessThan(12);
});
