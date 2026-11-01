<?php

use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
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
    $body = fn (string $qty): array => ['command_id' => (string) Str::uuid(), 'baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => $qty, 'lot' => [['lot' => RowHandle::of('inventory_lots', $this->lot), 'rak' => RowHandle::of('locations', $this->rack), 'jumlah' => $qty]], 'kebijakan_seri' => $line['serialized'], 'kebijakan_skala' => $line['scale'], 'tanggal' => date('Y-m-d')];

    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$project}/keluarkan", $body('2'))->assertRedirect();
    $answer = $this->actingAs($this->actor)->followingRedirects()->post("/gudang/barang-keluar/{$project}/keluarkan", $body('2'))->viewData('page')['flash']['answer'];

    expect($answer['kind'])->toBe('REJECTED')->and($answer['code'])->toBe('L-49')->and($answer['state']['matches'])->toHaveCount(1)->and($answer['state']['matches'][0]['id'])->toBeString();
    $over = $this->actingAs($this->actor)->followingRedirects()->post("/gudang/barang-keluar/{$project}/keluarkan", $body('25'))->viewData('page')['flash']['answer'];
    expect($over['code'])->toBe('PX-03')->and($over['state']['remainder'])->toBe('18')->and($over['text'])->toContain('18')->not->toContain('18.000');
});

it('INT-186: another company\'s lot without its reason answers MSG-14 like one without the authority (AZ-08; UXS-22; criterion 8)', function () {
    $foreign = InvoiceWorld::make();
    $lot = StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->demand['product'], $this->demand['unit'], $this->rack, '4');
    $allowed = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'stock.allocate_intercompany']);
    $project = DB::table('projects')->where('id', $this->demand['project']->id)->value('public_id');
    $line = $this->actingAs($allowed)->get("/gudang/barang-keluar/{$project}")->viewData('page')['props']['lines'][0];
    $answer = $this->actingAs($allowed)->followingRedirects()->post("/gudang/barang-keluar/{$project}/keluarkan", ['command_id' => (string) Str::uuid(), 'baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => '1', 'lot' => [['lot' => RowHandle::of('inventory_lots', $lot), 'rak' => RowHandle::of('locations', $this->rack), 'jumlah' => '1']], 'kebijakan_seri' => $line['serialized'], 'kebijakan_skala' => $line['scale'], 'tanggal' => date('Y-m-d')])->viewData('page')['flash']['answer'];

    expect($answer['code'])->toBe('AZ-08')->and($answer['text'])->toBe(__('messages.msg_14'));
});

it('INT-186, INT-188 and INT-189: a product whose serial or scale policy changed since the page read it answers CONFLICT, ST-09 (L-35)', function () {
    $project = DB::table('projects')->where('id', $this->demand['project']->id)->value('public_id');
    $line = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$project}")->viewData('page')['props']['lines'][0];
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '2', [DispatchWorld::pick($this->lot, $this->rack, '2')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->demand['line']);
    $movement = DB::table('stock_movements')->where('id', $row->movement)->value('public_id');
    $seen = ['kebijakan_seri' => true, 'kebijakan_skala' => 0, 'tanggal' => date('Y-m-d')];
    $consumption = RowHandle::of('stock_consumptions', $row->consumption);
    $rack = RowHandle::of('locations', $this->rack);
    $lot = RowHandle::of('inventory_lots', $this->lot);
    $evidence = (string) DB::table('evidence_documents')->where('id', DispatchWorld::evidence($this->company))->value('public_id');
    $sent = fn (string $url, array $body): array => $this->actingAs($this->actor)->followingRedirects()->post($url, ['command_id' => (string) Str::uuid(), ...$seen, ...$body])->viewData('page')['flash']['answer'];

    foreach ([
        $sent("/gudang/barang-keluar/{$project}/keluarkan", ['baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => '1', 'lot' => [['lot' => $lot, 'rak' => $rack, 'jumlah' => '1']]]),
        $sent("/gudang/barang-keluar/{$movement}/balikkan", ['jenis' => 'DISPATCH_REVERSAL', 'unit' => [['konsumsi' => $consumption, 'jumlah' => '1', 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => $rack]], 'alasan' => 'Tidak jadi berangkat']),
        $sent("/gudang/barang-keluar/{$movement}/ganti-identitas", ['konsumsi' => $consumption, 'jumlah' => '1', 'lot' => $lot, 'rak' => $rack, 'bukti' => $evidence, 'alasan' => 'Nomor keliru']),
    ] as $answer) {
        expect($answer['kind'])->toBe('CONFLICT')->and($answer['code'])->toBe('ST-09');
    }

    expect(DB::table('dispatch_lines')->count())->toBe(1)->and(DB::table('stock_restorations')->count())->toBe(0);
});

it('INT-186, INT-188 and INT-189: a lost answer replayed after re-authentication returns the recorded outcome with one effect, a refusal its current figure (CI-05; M-10; criterion 19)', function () {
    $project = DB::table('projects')->where('id', $this->demand['project']->id)->value('public_id');
    $line = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$project}")->viewData('page')['props']['lines'][0];
    $rack = RowHandle::of('locations', $this->rack);
    $lot = RowHandle::of('inventory_lots', $this->lot);
    $seen = ['kebijakan_seri' => $line['serialized'], 'kebijakan_skala' => $line['scale'], 'tanggal' => date('Y-m-d')];
    $twice = function (string $url, array $body): array {
        $body = ['command_id' => (string) Str::uuid(), ...$body];
        $first = $this->actingAs($this->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
        Auth::forgetGuards();

        return [$first, $this->actingAs($this->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer']];
    };

    [$first, $replay] = $twice("/gudang/barang-keluar/{$project}/keluarkan", [...$seen, 'baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => '3', 'lot' => [['lot' => $lot, 'rak' => $rack, 'jumlah' => '3']]]);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('dispatch_lines')->count())->toBe(1);
    $row = DispatchWorld::last($this->demand['line']);
    $movement = DB::table('stock_movements')->where('id', $row->movement)->value('public_id');
    $consumption = RowHandle::of('stock_consumptions', $row->consumption);

    [$first, $replay] = $twice("/gudang/barang-keluar/{$movement}/balikkan", [...$seen, 'jenis' => 'DISPATCH_REVERSAL', 'unit' => [['konsumsi' => $consumption, 'jumlah' => '1', 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => $rack]], 'alasan' => 'Tidak jadi berangkat']);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_restorations')->count())->toBe(1);

    // A refused restoration replayed names what the consumption holds now.
    [$first, $replay] = $twice("/gudang/barang-keluar/{$movement}/balikkan", [...$seen, 'jenis' => 'DISPATCH_REVERSAL', 'unit' => [['konsumsi' => $consumption, 'jumlah' => '5', 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => $rack]], 'alasan' => 'Tidak jadi berangkat']);
    expect($first['code'])->toBe('C-27')->and($first['state']['open'])->toBe('2')->and($replay['code'])->toBe('C-27')->and($replay['state']['open'])->toBe('2')->and($replay['text'])->toContain('2');

    $evidence = (string) DB::table('evidence_documents')->where('id', DispatchWorld::evidence($this->company))->value('public_id');
    [$first, $replay] = $twice("/gudang/barang-keluar/{$movement}/ganti-identitas", [...$seen, 'konsumsi' => $consumption, 'jumlah' => '1', 'lot' => $lot, 'rak' => $rack, 'bukti' => $evidence, 'alasan' => 'Lot keliru dicatat']);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_restorations')->where('kind', 'SUBSTITUTION')->count())->toBe(1);
});

it('INT-188 and INT-189: a restoration and a substitution each answer once under one command_id (CI-05)', function () {
    $pick = [DispatchWorld::pick($this->lot, $this->rack, '3')];
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '3', $pick))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->demand['line']);
    $movement = DB::table('stock_movements')->where('id', $row->movement)->value('public_id');
    $command = (string) Str::uuid();
    $unit = ['konsumsi' => RowHandle::of('stock_consumptions', $row->consumption), 'jumlah' => '1', 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => RowHandle::of('locations', $this->rack)];
    $body = ['command_id' => $command, 'jenis' => 'DISPATCH_REVERSAL', 'unit' => [$unit], 'alasan' => 'Tidak jadi berangkat', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => date('Y-m-d')];

    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$movement}/balikkan", $body)->assertRedirect();
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$movement}/balikkan", $body)->assertRedirect();
    expect(DB::table('stock_restorations')->count())->toBe(1);
    $conflict = $this->actingAs($this->actor)->followingRedirects()->post("/gudang/barang-keluar/{$movement}/balikkan", [...$body, 'alasan' => 'Alasan lain'])->viewData('page')['flash']['answer'];
    expect($conflict['kind'])->toBe('CONFLICT');
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$movement}/balikkan", [...$body, 'command_id' => (string) Str::uuid(), 'alasan' => ''])->assertSessionHasErrors('alasan');
});
