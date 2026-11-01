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

// TL-15 (TASK-040; QB-05; PFM-01–PFM-03; PFM-20; PF-13; PF-41): PG-018, PG-019 and PG-020 within four statements, the lot and serial group within three, the same for one row and many; ROUTE-212 bounded.

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

it('reads PG-018 within QB-05, the same for one row and many (PFM-01; PFM-02)', function () {
    $views = app(StockViews::class);
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    $one = stockStatements(fn () => $views->products(null, null));

    foreach (range(1, 3) as $ignored) {
        $goods = Projects::product();
        StockWorld::lot($this->actor->id, $this->company, $goods['product']->id, $goods['base']->id, $this->rack, '1');
    }

    expect(stockStatements(fn () => $views->products(null, null)))->toBe($one)->and($one)->toBeLessThanOrEqual(4)
        ->and(stockStatements(fn () => $views->places(null)))->toBeLessThanOrEqual(4);
});

it('reads PG-019 within QB-05, its group within three, the same for one lot and many (PFM-02)', function () {
    $views = app(StockViews::class);
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    $head = stockStatements(fn () => $views->product($this->actor->id, $this->product->public_id));
    $group = stockStatements(fn () => $views->group($this->actor->id, $this->product->id));

    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');

    expect($head)->toBeLessThanOrEqual(4)->and($group)->toBeLessThanOrEqual(3)
        ->and(stockStatements(fn () => $views->group($this->actor->id, $this->product->id)))->toBe($group);
});

it('reads PG-020 within QB-05, one branch per company in scope and one for pool movements (PFM-03; PF-13; PF-41)', function () {
    $history = app(MovementHistory::class);
    StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    $one = stockStatements(fn () => $history->page($this->actor->id, ['produk' => $this->product->public_id]));

    foreach (range(1, 3) as $ignored) {
        StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack, '1');
    }

    expect(stockStatements(fn () => $history->page($this->actor->id, ['produk' => $this->product->public_id])))->toBe($one)->and($one)->toBeLessThanOrEqual(4);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $history->page($this->actor->id, []);
    $sql = collect(DB::getQueryLog())->pluck('query')->first();
    DB::disableQueryLog();
    expect($sql)->toContain('"m"."company_id" is null')->toContain('"m"."company_id" = ?')->toContain('union all');
});

it('records ROUTE-212 in bounded statements (PFM-20; QB-13)', function () {
    $lot = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $this->unit, $this->rack);
    $change = StockWorld::conditionChange($this->actor->id, $this->company, $this->product->id, $lot, $this->rack, '2');
    $url = '/gudang/riwayat-stok/'.DB::table('stock_movements')->where('id', $change)->value('public_id').'/balikkan';
    $count = stockStatements(fn () => $this->actingAs($this->actor)->post($url, ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah kondisi', 'tanggal' => date('Y-m-d')]));

    expect(DB::table('stock_movements')->where('reverses_movement_id', $change)->count())->toBe(1)->and($count)->toBeLessThan(90);
});
