<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\DispatchQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-15 (TASK-041; PFM-01; PFM-02; PFM-20; QB-05; QB-13): PG-023 and PG-024 within QB-05, the same for one row and many; a dispatch bounded.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->lot = StockWorld::lot($this->actor->id, $this->company, $this->demand['product'], $this->demand['unit'], $this->rack);
    Numbering::goLive(date('Y').'-01-01');
    app(EffectiveCapabilities::class)->for($this->actor->id);
});

afterEach(fn () => @unlink($this->hotFile));

function dispatchStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('reads PG-023 in one statement a group and PG-024 in QB-05\'s bound, the same for one dispatch and several (PFM-01; PFM-02)', function () {
    $queries = app(DispatchQueries::class);
    expect(dispatchStatements(fn () => $queries->worklist($this->actor->id, $this->company->id, null)))->toBe(1);
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $one = dispatchStatements(fn () => $queries->screen($this->actor->id, $this->demand['project']->id, $this->company->id));

    foreach (['2', '3'] as $qty) {
        expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], $qty, [DispatchWorld::pick($this->lot, $this->rack, $qty)]))->toBe('COMMITTED');
    }

    expect(dispatchStatements(fn () => $queries->screen($this->actor->id, $this->demand['project']->id, $this->company->id)))->toBe($one)->and($one)->toBeLessThanOrEqual(6);
});

it('records a dispatch in bounded statements, the same for one lot and three (PFM-20; QB-13)', function () {
    $project = DB::table('projects')->where('id', $this->demand['project']->id)->value('public_id');
    $handle = fn (int $lot, string $qty): array => ['lot' => App\Support\RowHandle::of('inventory_lots', $lot), 'rak' => App\Support\RowHandle::of('locations', $this->rack), 'jumlah' => $qty];
    $line = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$project}")->viewData('page')['props']['lines'][0];
    $body = fn (string $qty, array $lots): array => ['command_id' => (string) Str::uuid(), 'baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => $qty, 'lot' => $lots, 'kebijakan_seri' => $line['serialized'], 'kebijakan_skala' => $line['scale'], 'tanggal' => date('Y-m-d')];
    $count = dispatchStatements(fn () => $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$project}/keluarkan", $body('1', [$handle($this->lot, '1')])));

    expect(DB::table('dispatch_lines')->count())->toBe(1)->and($count)->toBeLessThan(90);
});
