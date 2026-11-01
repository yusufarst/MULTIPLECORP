<?php

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

// TL-08 (TASK-041; INT-186; INT-188; INT-189; IG-05; CI-05; M-10; REF-058; criterion 19): each command answered, one `command_id` replayed with one effect, a changed payload CONFLICT.

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
});

afterEach(fn () => @unlink($this->hotFile));

it('INT-186: replays one command_id with one effect, a changed payload CONFLICT (CI-05; M-10)', function () {
    $command = (string) Str::uuid();
    $pick = [DispatchWorld::pick($this->lot, $this->rack, '2')];

    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '2', $pick, commandId: $command))->toBe('COMMITTED')
        ->and(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '2', $pick, commandId: $command))->toBe('COMMITTED')
        ->and(DB::table('dispatch_lines')->count())->toBe(1)
        ->and(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '3', [DispatchWorld::pick($this->lot, $this->rack, '3')], commandId: $command))->toStartWith('CONFLICT')
        ->and(DB::table('stock_product_balances as b')->where('product_id', $this->demand['product'])->value('on_hand_qty'))->toBe('8.000');
});

it('INT-186: a route answer carries its refusal with the line\'s remainder, and a likely duplicate its matches (IP-05; INT-015)', function () {
    $project = DB::table('projects')->where('id', $this->demand['project']->id)->value('public_id');
    $page = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$project}")->viewData('page')['props'];
    $line = $page['lines'][0];
    $body = fn (string $qty): array => ['command_id' => (string) Str::uuid(), 'baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => $qty, 'lot' => [['lot' => App\Support\RowHandle::of('inventory_lots', $this->lot), 'rak' => App\Support\RowHandle::of('locations', $this->rack), 'jumlah' => $qty]], 'kebijakan_seri' => $line['serialized'], 'kebijakan_skala' => $line['scale'], 'tanggal' => date('Y-m-d')];

    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$project}/keluarkan", $body('2'))->assertRedirect();
    $answer = $this->actingAs($this->actor)->followingRedirects()->post("/gudang/barang-keluar/{$project}/keluarkan", $body('2'))->viewData('page')['flash']['answer'];

    expect($answer['kind'])->toBe('REJECTED')->and($answer['code'])->toBe('L-49')->and($answer['state']['matches'])->toHaveCount(1)->and($answer['state']['matches'][0]['id'])->toBeString();
    $over = $this->actingAs($this->actor)->followingRedirects()->post("/gudang/barang-keluar/{$project}/keluarkan", $body('25'))->viewData('page')['flash']['answer'];
    expect($over['code'])->toBe('PX-03')->and($over['state']['remainder'])->toBe('18.000');
});

it('INT-188 and INT-189: a restoration and a substitution each answer once under one command_id (CI-05)', function () {
    $pick = [DispatchWorld::pick($this->lot, $this->rack, '3')];
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '3', $pick))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->demand['line']);
    $movement = DB::table('stock_movements')->where('id', $row->movement)->value('public_id');
    $command = (string) Str::uuid();
    $unit = ['konsumsi' => App\Support\RowHandle::of('stock_consumptions', $row->consumption), 'jumlah' => '1', 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => App\Support\RowHandle::of('locations', $this->rack)];
    $body = ['command_id' => $command, 'jenis' => 'DISPATCH_REVERSAL', 'unit' => [$unit], 'alasan' => 'Tidak jadi berangkat', 'tanggal' => date('Y-m-d')];

    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$movement}/balikkan", $body)->assertRedirect();
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$movement}/balikkan", $body)->assertRedirect();
    expect(DB::table('stock_restorations')->count())->toBe(1);
    $conflict = $this->actingAs($this->actor)->followingRedirects()->post("/gudang/barang-keluar/{$movement}/balikkan", [...$body, 'alasan' => 'Alasan lain'])->viewData('page')['flash']['answer'];
    expect($conflict['kind'])->toBe('CONFLICT');
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$movement}/balikkan", [...$body, 'command_id' => (string) Str::uuid(), 'alasan' => ''])->assertSessionHasErrors('alasan');
});
