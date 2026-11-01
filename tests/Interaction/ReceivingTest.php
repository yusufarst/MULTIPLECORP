<?php

use App\Modules\IAM\Models\User;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-039; IG-05; INT-181–INT-183; INT-006; INT-282; CI-05; M-10; AC-16; REF-058; IP-04; IP-19; criterion 15): each command once for one `command_id` sent twice, also after re-authentication, nothing written twice; other content a CONFLICT; each line its own command with its own answer; the validation at its fields; a scan a lookup with no effect.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-12');
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company);
    $this->goods = Projects::product();
    $this->unit = $this->goods['base']->code;
    $this->rack = ReceivingWorld::rack();
    $this->purchase = ReceivingWorld::purchase($this->company, $this->goods['product']->id, $this->goods['base']->id);
    $this->line = ReceivingWorld::line($this->purchase);
    $this->url = "/gudang/barang-masuk/{$this->purchase->public_id}";
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function receiveFlash(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('INT-181: receives once, a replay the recorded answer, another content a CONFLICT, the validation at its fields', function () {
    $body = ReceivingWorld::request($this->line, $this->unit, $this->rack, '4', more: ['referensi' => 'SJ-1']);

    $this->actingAs($this->actor)->post("{$this->url}/terima", [...$body, 'layak' => 'dua'])->assertSessionHasErrors('layak');
    $this->actingAs($this->actor)->post("{$this->url}/terima", [...$body, 'rak' => 'bukan-rak'])->assertSessionHasErrors('rak');
    expect(DB::table('receiving_lines')->count())->toBe(0);

    $this->actingAs($this->actor)->post("{$this->url}/terima", $body)->assertRedirect($this->url);
    expect(receiveFlash()['kind'])->toBe('COMMITTED', json_encode(receiveFlash()));

    $this->actingAs($this->actor)->post("{$this->url}/terima", $body)->assertRedirect($this->url);
    expect(receiveFlash()['kind'])->toBe('COMMITTED')
        ->and(DB::table('receiving_lines')->count())->toBe(1)->and(DB::table('inventory_lots')->count())->toBe(1)
        ->and(DB::table('stock_movement_entries')->count())->toBe(1)->and(ReceivingWorld::balance($this->line->id)->normal_received_net_qty_base)->toBe('4.000');

    $this->actingAs($this->actor)->post("{$this->url}/terima", [...$body, 'layak' => '5']);
    expect(receiveFlash()['kind'])->toBe('CONFLICT')->and(DB::table('receiving_lines')->count())->toBe(1);
});

it('INT-181: a lost answer replayed after re-authentication returns the recorded receipt, one effect (M-10; CI-05; AC-16; criterion 15)', function () {
    $body = ReceivingWorld::request($this->line, $this->unit, $this->rack, '4');
    $this->actingAs($this->actor)->post("{$this->url}/terima", $body);
    expect(receiveFlash()['kind'])->toBe('COMMITTED', json_encode(receiveFlash()));

    auth()->logout();
    $this->post("{$this->url}/terima", $body)->assertRedirect();
    expect(DB::table('receiving_lines')->count())->toBe(1);

    $this->actingAs(User::query()->findOrFail($this->actor->id))->post("{$this->url}/terima", $body)->assertRedirect($this->url);
    expect(receiveFlash()['kind'])->toBe('COMMITTED')->and(DB::table('receiving_lines')->count())->toBe(1)->and(DB::table('inventory_lots')->count())->toBe(1)
        ->and(DB::table('stock_product_balances')->where('product_id', $this->line->product_id)->value('on_hand_qty'))->toBe('4.000');
});

it('INT-181: gives each line its own command and its own answer, a refused one leaving the other recorded (IP-19; PX-05; criterion 15)', function () {
    $this->actingAs($this->actor)->post("{$this->url}/terima", ReceivingWorld::request($this->line, $this->unit, $this->rack, '4'));
    expect(receiveFlash()['kind'])->toBe('COMMITTED', json_encode(receiveFlash()));

    $this->actingAs($this->actor)->post("{$this->url}/terima", ReceivingWorld::request($this->line, $this->unit, $this->rack, '500'));
    expect(receiveFlash()['code'])->toBe('C-29')->and(receiveFlash()['text'])->toContain('96')
        ->and(DB::table('receiving_lines')->count())->toBe(1)->and(ReceivingWorld::balance($this->line->id)->normal_received_net_qty_base)->toBe('4.000');
});

it('INT-182: opens one draft of the receiving event for one command_id, its draft reading the committed receipt, no stock changed (ROUTE-221; BR-XD-04; criterion 14)', function () {
    $this->actingAs($this->actor)->post("{$this->url}/terima", ReceivingWorld::request($this->line, $this->unit, $this->rack, '4', more: ['referensi' => 'SJ-9']));
    $movement = DB::table('stock_movements')->where('movement_type', 'RECEIPT')->value('public_id');
    $body = ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-12', 'subjek' => ['receiving_movement_id' => $movement]];

    $this->actingAs($this->actor)->post('/dokumen', $body)->assertRedirect();
    $this->actingAs($this->actor)->post('/dokumen', $body)->assertRedirect();
    expect(DB::table('documents')->where('document_type_code', 'DOC-12')->count())->toBe(1)
        ->and(DB::table('stock_movement_entries')->count())->toBe(1)
        ->and(DB::table('stock_product_balances')->where('product_id', $this->line->product_id)->value('on_hand_qty'))->toBe('4.000');
});

it('INT-182: never opens a note without a committed receipt, a fictitious subject answered like none (WF-DOC-01; BR-DOC-04)', function () {
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-12', 'subjek' => ['receiving_movement_id' => (string) Str::uuid()]])->assertNotFound();
    expect(DB::table('documents')->count())->toBe(0);
});

it('INT-183: reverses once, a replay the recorded answer, another content a CONFLICT, the reason required (IP-09; MSG-40)', function () {
    $this->actingAs($this->actor)->post("{$this->url}/terima", ReceivingWorld::request($this->line, $this->unit, $this->rack, '10'));
    $handle = RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->value('id'));
    $body = ['command_id' => (string) Str::uuid(), 'tempat' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '4']], 'alasan' => 'Produk salah', 'tanggal' => $this->today];

    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", [...$body, 'alasan' => ''])->assertSessionHasErrors('alasan');
    expect(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(0);

    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", $body);
    expect(receiveFlash()['kind'])->toBe('COMMITTED', json_encode(receiveFlash()));
    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", $body);
    expect(receiveFlash()['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(1)
        ->and(ReceivingWorld::balance($this->line->id)->normal_received_net_qty_base)->toBe('6.000');

    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", [...$body, 'alasan' => 'Alasan lain']);
    expect(receiveFlash()['kind'])->toBe('CONFLICT')->and(DB::table('stock_movements')->where('movement_type', 'RECEIPT_REVERSAL')->count())->toBe(1);
});

it('INT-183: records one business audit event in its transaction, reasoned and reaching the Owner review (BR-XC-01; LG-01; AZ-10)', function () {
    $this->actingAs($this->actor)->post("{$this->url}/terima", ReceivingWorld::request($this->line, $this->unit, $this->rack, '10'));
    $handle = RowHandle::of('receiving_lines', (int) DB::table('receiving_lines')->value('id'));
    $this->actingAs($this->actor)->post("/gudang/penerimaan/{$handle}/balikkan", ['command_id' => (string) Str::uuid(), 'tempat' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '4']], 'alasan' => 'Produk salah', 'tanggal' => $this->today]);

    expect(DB::table('audit_events')->where('action', 'INV.GoodsReceived')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'INV.ReceiptReversed')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'INV.ReceiptReversed')->value('authority_class'))->toBe('ADM_PLUS')
        ->and(DB::table('audit_events')->where('action', 'INV.ReceiptReversed')->value('reason'))->toBe('Produk salah');
});

it('INT-282: looks a scan up with no effect and no command_id, under the scanner\'s own limiter (SF-SCAN; QB-06; WS-12; IP-15)', function () {
    $before = [DB::table('command_log')->count(), DB::table('audit_events')->count()];
    $found = $this->actingAs($this->actor)->post('/gudang/pindai', ['kode' => $this->goods['product']->sku])->assertOk()->json();
    $none = $this->actingAs($this->actor)->post('/gudang/pindai', ['kode' => 'TIDAK-ADA'])->assertOk()->json();

    expect($found['kind'])->toBe('match')->and($found['matches'][0]['kind'])->toBe('product')->and($none['kind'])->toBe('unknown')
        ->and([DB::table('command_log')->count(), DB::table('audit_events')->count()])->toBe($before)
        ->and(DB::table('product_barcodes')->count())->toBe(0);

    $this->actingAs($this->actor)->post('/gudang/pindai', ['kode' => ''])->assertUnprocessable();
    expect(RateLimiter::limiter('scanner'))->not->toBeNull();

    $hits = 0;

    foreach (range(1, 305) as $ignored) {
        $hits += $this->actingAs($this->actor)->post('/gudang/pindai', ['kode' => 'X'])->getStatusCode() === 429 ? 1 : 0;
    }

    expect($hits)->toBeGreaterThan(0);
});
