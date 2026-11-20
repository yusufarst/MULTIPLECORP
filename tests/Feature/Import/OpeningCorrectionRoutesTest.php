<?php

use App\Modules\DOC\Models\EvidenceDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\ImportWorld;
use Tests\Support\RefreshDatabase;

// TL-04 and TL-08 (TASK-069; INT-213; INT-214; ROUTE-213; ROUTE-214; CM-33; IG-01; IG-05; IP-14): "Koreksi Data Awal" on
// PG-058 through its routes — offered on a committed opening fact only to the account holding `stock.reverse` for stock
// and `finance.contra` for a receivable or a credit; the reason asked, the correction answered in place on the batch;
// a missing reason answered at its field with nothing written; a second reversal of the same movement refused in the
// business's words. TASK-057's OpeningImportFlowTest proves the actions' own rules (CM-33).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    $this->world = ImportWorld::make();
    $code = $this->world->company->code;
    $this->world->import('PRODUCT', [['id' => 'P1', 'jenis' => 'barang', 'sku' => 'KURSI-1', 'nama' => 'Kursi', 'satuan' => 'UNIT']]);
    $this->world->import('LOCATION', [['id' => 'L1', 'kode' => 'RAK-B1', 'nama' => 'Rak B1']]);
    $this->world->import('CLIENT', [['id' => 'K1', 'nama' => 'SMP 2 Contoh', 'unit' => 'TU', 'pic_nama' => 'Pak Budi']]);
    $this->stock = $this->world->import('OPENING_STOCK', [['id' => 'S1', 'perusahaan' => $code, 'produk' => 'KURSI-1', 'lokasi' => 'RAK-B1', 'jumlah' => '6', 'biaya' => '600000']]);
    $this->receivable = $this->world->import('OPENING_RECEIVABLE', [['id' => 'F1', 'perusahaan' => $code, 'klien' => 'K1', 'nomor_faktur' => 'LAMA-9', 'tanggal_tagih' => "{$this->world->year}-09-01", 'nilai' => '1000000']]);
    $this->corrector = Authorization::admin([$this->world->company->id], ['import.prepare', 'projects.view', 'stock.view', 'stock.reverse', 'finance.view', 'finance.contra']);
    $this->answer = function (string $url, array $body): array {
        $answer = $this->actingAs($this->corrector)->followingRedirects()->post($url, ['command_id' => (string) Str::uuid(), ...$body])->viewData('page')['flash']['answer'];
        Auth::forgetGuards();

        return $answer;
    };
});

afterEach(fn () => @unlink($this->hotFile));

/** The facts PG-058 offers the account on a batch, each with the address its correction posts to. */
function correctableFacts(object $test, object $account, string $batch): array
{
    $facts = $test->actingAs($account)->get("/data-master/impor?batch={$batch}")->assertOk()->viewData('page')['props']['batch']['facts'] ?? [];
    Auth::forgetGuards();

    return $facts;
}

it('offers Koreksi Data Awal on an opening stock fact to stock.reverse alone and reverses it in place with its reason (INT-213; ROUTE-213; IG-01)', function () {
    $without = Authorization::admin([$this->world->company->id], ['import.prepare', 'projects.view', 'stock.view', 'finance.view', 'finance.contra']);
    expect(correctableFacts($this, $without, $this->stock->public_id))->toBe([]);

    [$fact] = correctableFacts($this, $this->corrector, $this->stock->public_id);
    $url = "/gudang/riwayat-stok/{$fact['target']}/koreksi-data-awal";

    $this->actingAs($this->corrector)->from("/data-master/impor?batch={$this->stock->public_id}")->post($url, ['command_id' => (string) Str::uuid(), 'alasan' => ''])
        ->assertRedirect("/data-master/impor?batch={$this->stock->public_id}")->assertSessionHasErrors('alasan');
    Auth::forgetGuards();
    expect(DB::table('stock_product_balances')->sum('on_hand_qty'))->toEqual(6);

    expect(($this->answer)($url, ['alasan' => 'Jumlah salah', 'batch' => $this->stock->public_id])['kind'])->toBe('COMMITTED')
        ->and(DB::table('stock_product_balances')->sum('on_hand_qty'))->toEqual(0)
        ->and(($this->answer)($url, ['alasan' => 'Jumlah salah lagi', 'batch' => $this->stock->public_id])['kind'])->not->toBe('COMMITTED')
        ->and(DB::table('stock_product_balances')->sum('on_hand_qty'))->toEqual(0);
});

it('offers Koreksi Data Awal on an opening receivable to finance.contra alone and contras it in place with its reason (INT-214; ROUTE-214; IG-01)', function () {
    $without = Authorization::admin([$this->world->company->id], ['import.prepare', 'projects.view', 'stock.view', 'stock.reverse', 'finance.view']);
    expect(correctableFacts($this, $without, $this->receivable->public_id))->toBe([]);

    [$fact] = correctableFacts($this, $this->corrector, $this->receivable->public_id);
    $url = "/keuangan/data-awal/{$fact['target']}/koreksi";
    $invoice = (int) DB::table('invoices')->where('origin', 'OPENING')->value('id');

    $this->actingAs($this->corrector)->from("/data-master/impor?batch={$this->receivable->public_id}")->post($url, ['command_id' => (string) Str::uuid(), 'baris' => $fact['row'], 'alasan' => 'Sisa salah', 'nilai' => 'empat ratus'])
        ->assertSessionHasErrors('nilai');
    Auth::forgetGuards();
    expect(DB::table('invoice_balances')->where('invoice_id', $invoice)->value('gross_value'))->toEqual(1000000);

    expect(($this->answer)($url, ['baris' => $fact['row'], 'alasan' => 'Sisa salah', 'nilai' => '400000', 'ppn' => '0'])['kind'])->toBe('COMMITTED')
        ->and(DB::table('invoice_balances')->where('invoice_id', $invoice)->value('gross_value'))->toEqual(400000);
});

it('answers a correction of a fact outside the batch as not found (ROUTE-214; RC-05)', function () {
    $this->actingAs($this->corrector)->post("/keuangan/data-awal/{$this->receivable->public_id}/koreksi", ['command_id' => (string) Str::uuid(), 'baris' => 999, 'alasan' => 'Tidak ada'])->assertNotFound();
    $this->actingAs($this->corrector)->post('/gudang/riwayat-stok/'.Str::uuid().'/koreksi-data-awal', ['command_id' => (string) Str::uuid(), 'alasan' => 'Tidak ada'])->assertNotFound();
});

it('contras an opening credit through the same route, offered to finance.contra (INT-214; ROUTE-214)', function () {
    $evidence = EvidenceDocument::factory()->create(['company_id' => $this->world->company->id, 'evidence_scope' => 'COMPANY']);
    $credit = $this->world->import('OPENING_CREDIT', [['id' => 'C1', 'perusahaan' => $this->world->company->code, 'klien' => 'K1', 'nilai' => '250000', 'bukti' => $evidence->public_id]]);
    [$fact] = correctableFacts($this, $this->corrector, $credit->public_id);

    expect($fact['kind'])->toBe('OPENING_CREDIT')
        ->and(($this->answer)("/keuangan/data-awal/{$fact['target']}/koreksi", ['baris' => $fact['row'], 'alasan' => 'Kredit tidak ada'])['kind'])->toBe('COMMITTED')
        ->and(DB::table('application_source_balances')->whereNotNull('opening_customer_credit_id')->value('net_amount'))->toEqual(0);
});
