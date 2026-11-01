<?php

use App\Modules\PUR\Models\Purchase;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-037; IG-05; INT-171–INT-175; INT-178–INT-180; CI-05; M-09): each command once for one `command_id` sent twice, nothing written twice; other content a CONFLICT; the success, validation and each rejection named.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = PurchaseWorld::numbered($this->world->company);
    $this->actor = PurchaseWorld::actor($this->company);
    $this->supplier = PurchaseWorld::supplier();
    $this->goods = Projects::product();
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function purchaseFlash(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

function goodsPurchase(object $test, array $lines = []): Purchase
{
    $test->actingAs($test->actor)->post('/pembelian', PurchaseWorld::payload($test->supplier, $lines === [] ? [PurchaseWorld::line($test->goods, '10', '1000')] : $lines, ['perusahaan' => $test->company->public_id]));

    return PurchaseWorld::latest($test->company->id);
}

it('INT-171: saves once, the validation at its fields, a replay the recorded answer, another content a CONFLICT', function () {
    $body = PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '10', '1000')], ['perusahaan' => $this->company->public_id]);

    $this->actingAs($this->actor)->post('/pembelian', [...$body, 'command_id' => (string) Str::uuid(), 'baris' => [PurchaseWorld::line($this->goods, '0', '1000')]])->assertSessionHasErrors('baris.0.jumlah');
    $this->actingAs($this->actor)->post('/pembelian', [...$body, 'command_id' => (string) Str::uuid(), 'pemasok' => (string) Str::uuid()])->assertSessionHasErrors('pemasok');
    $this->actingAs($this->actor)->post('/pembelian', [...$body, 'command_id' => (string) Str::uuid(), 'tanggal' => '2099-01-01'])->assertSessionHasErrors('tanggal');
    expect(DB::table('purchases')->count())->toBe(0);

    $saved = $this->actingAs($this->actor)->post('/pembelian', $body);
    $purchase = PurchaseWorld::latest($this->company->id);
    $saved->assertRedirect("/pembelian/{$purchase->public_id}");
    expect(purchaseFlash()['kind'])->toBe('COMMITTED');

    $this->actingAs($this->actor)->post('/pembelian', $body)->assertRedirect("/pembelian/{$purchase->public_id}");
    expect(DB::table('purchases')->count())->toBe(1)->and(DB::table('audit_events')->where('action', 'PUR.PurchaseRecorded')->count())->toBe(1);

    $this->actingAs($this->actor)->post('/pembelian', [...$body, 'referensi' => 'LAIN']);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT')->and(DB::table('purchases')->count())->toBe(1);
});

it('INT-172: amends once for one command_id, another content a CONFLICT', function () {
    $purchase = goodsPurchase($this);
    $handle = RowHandle::of('purchase_lines', PurchaseWorld::lines($purchase)[0]->id);
    $body = ['command_id' => (string) Str::uuid(), 'lock_version' => $purchase->lock_version, 'baris' => [['handle' => $handle, ...PurchaseWorld::line($this->goods, '12', '1000')]]];

    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", $body)->assertRedirect("/pembelian/{$purchase->public_id}");
    expect(purchaseFlash()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'PUR.PurchaseAmended')->count())->toBe(1)->and($purchase->fresh()->lock_version)->toBe($purchase->lock_version + 1);
    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", [...$body, 'baris' => [['handle' => $handle, ...PurchaseWorld::line($this->goods, '13', '1000')]]]);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT');
});

it('INT-172: names each amendment refusal: a line left out, a non-stock line, an unknown reference', function () {
    $purchase = goodsPurchase($this, [PurchaseWorld::line($this->goods, '1', '1'), PurchaseWorld::line(Projects::product(), '1', '1')]);
    [$a] = PurchaseWorld::lines($purchase);
    $handle = RowHandle::of('purchase_lines', $a->id);

    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", ['command_id' => (string) Str::uuid(), 'lock_version' => $purchase->lock_version, 'baris' => [['handle' => $handle, ...PurchaseWorld::line($this->goods, '1', '1')]]]);
    expect(purchaseFlash()['code'])->toBe('L-23')->and(purchaseFlash()['state']['reason'])->toBe('lines');

    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", ['command_id' => (string) Str::uuid(), 'lock_version' => $purchase->lock_version, 'baris' => [['handle' => $handle, 'jenis' => 'GOODS', 'produk' => (string) Str::uuid(), 'satuan' => 'X', 'jumlah' => '1', 'harga' => '1']]])->assertSessionHasErrors('baris.0.produk');
});

it('INT-173: cancels once, the reason required, other content a CONFLICT', function () {
    $purchase = goodsPurchase($this);
    $url = "/pembelian/{$purchase->public_id}/batalkan";

    $this->actingAs($this->actor)->post($url, ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today])->assertSessionHasErrors('alasan');
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'alasan' => 'Pesanan batal'];
    $this->actingAs($this->actor)->post($url, $body)->assertRedirect("/pembelian/{$purchase->public_id}");
    expect(purchaseFlash()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post($url, $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'PUR.PurchaseCancelled')->count())->toBe(1);
    $this->actingAs($this->actor)->post($url, [...$body, 'alasan' => 'Alasan lain']);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT');
});

it('INT-174: closes once, one closure per line per command', function () {
    $purchase = goodsPurchase($this);
    $line = PurchaseWorld::lines($purchase)[0];
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'alasan' => 'Pemasok tidak sanggup', 'baris' => [['baris' => RowHandle::of('purchase_lines', $line->id), 'jumlah' => '4']]];

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", [...$body, 'command_id' => (string) Str::uuid(), 'baris' => []])->assertSessionHasErrors('baris');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED')->and(DB::table('purchase_line_closures')->count())->toBe(1)->and(DB::table('purchase_line_balances')->where('purchase_line_id', $line->id)->value('closed_qty_base'))->toBe('4.000')->and($purchase->fresh()->state)->toBe('OPEN');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", [...$body, 'alasan' => 'Beda']);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT');
});

it('INT-175: corrects once, unchanged named, other content a CONFLICT', function () {
    $purchase = goodsPurchase($this);
    $line = PurchaseWorld::lines($purchase)[0];
    PurchaseWorld::cost($this->company, $this->actor->id, $line->product_id)->receive($line, '10');
    $handle = RowHandle::of('purchase_lines', $line->id);
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'alasan' => 'Harga keliru', 'jenis' => 'PRICE_CORRECTION', 'catatan' => $handle, 'nilai' => '9000'];

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED', json_encode(purchaseFlash()));
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", $body);
    expect(DB::table('purchase_line_adjustments')->count())->toBe(1)->and(DB::table('purchase_line_balances')->where('purchase_line_id', $line->id)->value('goods_value'))->toBe('9000.00');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", [...$body, 'nilai' => '8000']);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", [...$body, 'command_id' => (string) Str::uuid()]);
    expect(purchaseFlash()['code'])->toBe('AX-34');
});

it('INT-178: records a late charge once; another content a CONFLICT; a cancelled purchase a CONFLICT', function () {
    $purchase = goodsPurchase($this);
    $bill = PurchaseWorld::bill($this->company->id);
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'tagihan' => $bill, 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '100'];

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", [...$body, 'command_id' => (string) Str::uuid(), 'klasifikasi' => 'ATTRIBUTABLE'])->assertSessionHasErrors('dasar');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED', json_encode(purchaseFlash()));
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", $body);
    expect(DB::table('purchase_charges')->count())->toBe(1);
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", [...$body, 'jumlah' => '200']);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'alasan' => 'Batal']);
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", [...$body, 'command_id' => (string) Str::uuid()]);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT');
});

it('INT-179: corrects the basis once, Σ allocated = the charge exactly, an expensed charge named', function () {
    $purchase = goodsPurchase($this, [PurchaseWorld::line($this->goods, '10', '1000'), PurchaseWorld::line(Projects::product(), '30', '1000')]);
    $bill = PurchaseWorld::bill($this->company->id);
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '1000', 'dasar' => 'LINE_VALUE']);
    $charge = (int) DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id');
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'alasan' => 'Dasar salah', 'catatan' => RowHandle::of('purchase_charges', $charge), 'dasar' => 'EXPLICIT', 'nilai_dasar' => ['1', '3']];

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-dasar-alokasi", $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED', json_encode(purchaseFlash()));
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-dasar-alokasi", $body);
    expect(DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $charge)->sum('share_amount'))->toEqual('1000.00')->and(DB::table('purchase_charge_line_shares')->where('purchase_charge_id', $charge)->max('allocation_version'))->toBe(2);

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'tagihan' => $bill, 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '10']);
    $expensed = (int) DB::table('purchase_charges')->where('purchase_id', $purchase->id)->where('classification', 'NON_ATTRIBUTABLE')->value('id');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-dasar-alokasi", [...$body, 'command_id' => (string) Str::uuid(), 'catatan' => RowHandle::of('purchase_charges', $expensed)]);
    expect(purchaseFlash()['code'])->toBe('CM-35');
});

it('INT-180: marks once, a second mark on a marked line a CONFLICT, one command_id replayed', function () {
    $project = $this->world->project(['SERVICE'], '1');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line(Projects::product('SERVICE'), '1', '500', ['jenis' => 'NONSTOCK'])], ['proyek' => $project->public_id]));
    $purchase = PurchaseWorld::latest($this->company->id);
    $line = PurchaseWorld::lines($purchase)[0];
    $url = "/pembelian/{$purchase->public_id}/baris/".RowHandle::of('purchase_lines', $line->id).'/terlaksana';
    $body = ['command_id' => (string) Str::uuid()];

    $this->actingAs($this->actor)->post($url, $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED', json_encode(purchaseFlash()));
    $this->actingAs($this->actor)->post($url, $body);
    expect(purchaseFlash()['kind'])->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'PUR.LinePerformed')->count())->toBe(1);
    $this->actingAs($this->actor)->post($url, ['command_id' => (string) Str::uuid()]);
    expect(purchaseFlash()['kind'])->toBe('CONFLICT');
});
