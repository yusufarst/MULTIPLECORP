<?php

use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Queries\PurchaseDetail;
use App\Modules\PUR\Queries\PurchaseList;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-037; PFM-01; PFM-02; PFM-20; PF-13; QB-14): PG-015 and PG-016 read in bounded statements, the same for one row and a full page; PG-015 one company at a time; ROUTE-171's ten lines, ROUTE-174 and ROUTE-175 bounded by a constant per line.

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
});

afterEach(fn () => @unlink($this->hotFile));

function purchaseStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function purchaseSaved(object $test, int $lines): Purchase
{
    $test->actingAs($test->actor)->post('/pembelian', PurchaseWorld::payload($test->supplier, array_fill(0, $lines, PurchaseWorld::line($test->goods, '2', '100')), ['perusahaan' => $test->company->public_id]));

    return PurchaseWorld::latest($test->company->id);
}

it('reads PG-015 in the same statements for one row and a full page, one company at a time (PFM-01; PF-13)', function () {
    purchaseSaved($this, 1);
    $list = app(PurchaseList::class);
    $one = purchaseStatements(fn () => $list->screen($this->actor->id, null, null, null));

    foreach (range(1, 5) as $ignored) {
        purchaseSaved($this, 1);
    }

    $many = purchaseStatements(fn () => $list->screen($this->actor->id, null, null, null));
    expect($many)->toBe($one)->and($one)->toBeLessThanOrEqual(8);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $list->screen($this->actor->id, null, null, null);
    $reads = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, '"purchases"'))->values();
    DB::disableQueryLog();
    expect($reads->first())->toContain('"x"."company_id" = ?');
});

it('reads PG-016 in the same statements for one line and thirty (PFM-02; QB-02)', function () {
    $detail = app(PurchaseDetail::class);
    $small = purchaseSaved($this, 1);
    $one = purchaseStatements(fn () => $detail->screen($this->actor->id, $small));
    $large = purchaseSaved($this, 30);
    $many = purchaseStatements(fn () => $detail->screen($this->actor->id, $large));

    expect($many)->toBe($one)->and($one)->toBeLessThanOrEqual(40);
});

it('saves a ten-line purchase with its statements bounded per line (PFM-20)', function () {
    $count = purchaseStatements(fn () => purchaseSaved($this, 10));

    expect($count)->toBeLessThan(150);
});

it('bounds ROUTE-174 and ROUTE-175 (QB-14)', function () {
    $purchase = purchaseSaved($this, 10);
    $lines = PurchaseWorld::lines($purchase);
    $closures = array_map(fn ($line): array => ['baris' => RowHandle::of('purchase_lines', $line->id), 'jumlah' => '1'], $lines);
    $closing = purchaseStatements(fn () => $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", ['command_id' => (string) Str::uuid(), 'tanggal' => date('Y-m-d'), 'alasan' => 'Sisa ditutup', 'baris' => $closures]));
    expect($closing)->toBeLessThan(220);

    PurchaseWorld::cost($this->company, $this->actor->id, $lines[0]->product_id)->receive($lines[0], '1');
    $correcting = purchaseStatements(fn () => $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", ['command_id' => (string) Str::uuid(), 'tanggal' => date('Y-m-d'), 'alasan' => 'Harga keliru', 'jenis' => 'PRICE_CORRECTION', 'catatan' => RowHandle::of('purchase_lines', $lines[0]->id), 'nilai' => '300']));
    expect($correcting)->toBeLessThan(160);
});
