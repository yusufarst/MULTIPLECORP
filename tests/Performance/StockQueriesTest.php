<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\MovementHistory;
use App\Modules\INV\Queries\StockViews;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-15 (TASK-040; QB-05; PFM-01–PFM-03; PFM-20; PF-13; PF-41): PG-018, PG-019 and PG-020 within four statements, the lot and serial group within three, the same for one row and many, read at the pages' own partial reloads; ROUTE-212 bounded.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, StockWorld::CAPABILITIES);
    $goods = Projects::product();
    $this->product = $goods['product'];
    $this->unit = $goods['base']->id;
    $this->rack = ReceivingWorld::rack();
    app(EffectiveCapabilities::class)->for($this->actor->id);
});

afterEach(fn () => @unlink($this->hotFile));

function stockStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** What a partial reload of `props` reads beyond the request's own statements, measured against a prop that reads nothing. */
function stockPartial(object $test, object $user, string $url, string $component, string $props): int
{
    $version = (string) $test->actingAs($user)->get($url)->assertOk()->viewData('page')['version'];
    $partial = fn (string $data): int => stockStatements(fn () => $test->actingAs($user)->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => $component, 'X-Inertia-Partial-Data' => $data])->assertOk());
    $partial($props);

    return $partial($props) - $partial('can');
}

it('reads PG-018 within QB-05, the same for one row and many, each partial reload its own group alone (PFM-01; PFM-02)', function () {
    $views = app(StockViews::class);
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    $one = stockStatements(fn () => $views->products($this->actor->id, null, false, null));

    foreach (range(1, 3) as $ignored) {
        $goods = Projects::product();
        StockWorld::lot($this->actor->id, $this->company, $goods['product']->id, $goods['base']->id, $this->rack, '1');
    }

    expect(stockStatements(fn () => $views->products($this->actor->id, null, false, null)))->toBe($one)->and($one)->toBeLessThanOrEqual(4)
        ->and(stockStatements(fn () => $views->places($this->actor->id, null, null, null)))->toBeLessThanOrEqual(4);

    // The stock value's group: three statements whatever the viewer's companies; never the list's.
    $wide = ReceivingWorld::actor($this->company, StockWorld::CAPABILITIES);
    $few = stockPartial($this, $wide, '/gudang/stok', 'INV/Stock', 'values');

    foreach (range(1, 4) as $ignored) {
        Authorization::grantCompany($wide, InvoiceWorld::make()->company->id);
    }

    app(EffectiveCapabilities::class)->forget();
    expect($few)->toBeLessThanOrEqual(3)->and(stockPartial($this, $wide, '/gudang/stok', 'INV/Stock', 'values'))->toBe($few);
});

it('reads PG-019 within QB-05, its group within three, the same for one lot and many (PFM-02)', function () {
    $views = app(StockViews::class);
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    $head = stockStatements(fn () => $views->product($this->product->public_id));
    $group = stockStatements(fn () => $views->group($this->actor->id, $this->product->id));

    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');

    expect($head)->toBe(1)->and($group)->toBeLessThanOrEqual(2)
        ->and(stockStatements(fn () => $views->group($this->actor->id, $this->product->id)))->toBe($group);

    // At the page: the head resolved once, then each group alone, within QB-05's three.
    $url = "/gudang/stok/{$this->product->public_id}";
    expect(stockPartial($this, $this->actor, $url, 'INV/ProductStock', 'group'))->toBeLessThanOrEqual(2)
        ->and(stockPartial($this, $this->actor, $url, 'INV/ProductStock', 'movements'))->toBeLessThanOrEqual(2)
        ->and(stockPartial($this, $this->actor, $url, 'INV/ProductStock', 'costs'))->toBeLessThanOrEqual(2);
});

it('reads PG-020 within QB-05, one branch per company in scope and one for pool movements (PFM-03; PF-13; PF-41)', function () {
    $history = app(MovementHistory::class);
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    $filters = MovementHistory::filters(['produk' => $this->product->public_id]);
    $one = stockStatements(fn () => $history->page($this->actor->id, $filters));

    foreach (range(1, 3) as $ignored) {
        StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    }

    expect(stockStatements(fn () => $history->page($this->actor->id, $filters)))->toBe($one)->and($one)->toBeLessThanOrEqual(4)
        ->and(stockPartial($this, $this->actor, '/gudang/riwayat-stok', 'INV/StockHistory', 'list'))->toBeLessThanOrEqual(4);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $history->page($this->actor->id, MovementHistory::filters([]));
    $sql = collect(DB::getQueryLog())->pluck('query')->first();
    DB::disableQueryLog();
    expect($sql)->toContain('"m"."company_id" is null')->toContain('"m"."company_id" = ?')->toContain('union all');

    // The Owner gets one branch per company too (PF-41), never one scan across them.
    $owner = Authorization::owner();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $history->page($owner->id, MovementHistory::filters([]));
    $statements = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    expect($statements->first(fn (string $query): bool => str_contains($query, 'stock_movement_entries')))->toContain('"m"."company_id" is null')->toContain('"m"."company_id" = ?');
});

it('records ROUTE-212 in bounded statements (PFM-20; QB-13)', function () {
    $lot = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack);
    $change = StockWorld::conditionChange($this->actor->id, $this->company, $this->product->id, $lot, $this->rack, '2');
    $url = '/gudang/riwayat-stok/'.DB::table('stock_movements')->where('id', $change)->value('public_id').'/balikkan';
    $count = stockStatements(fn () => $this->actingAs($this->actor)->post($url, ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah kondisi', 'tanggal' => date('Y-m-d')]));

    expect(DB::table('stock_movements')->where('reverses_movement_id', $change)->count())->toBe(1)->and($count)->toBeLessThan(90);
});
