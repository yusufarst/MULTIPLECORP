<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-08 (TASK-045; INT-201; INT-203; INT-204; INT-015; IG-05; CI-05): each command through its route — success, a rejection named, one `command_id` replayed with one effect, the same identifier with another body CONFLICT, the fungible delivery's confirmation.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, DeliveryWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $lot = StockWorld::lot($this->actor->id, $this->company, $this->demand['product'], $this->demand['unit'], $this->rack, '10', '1000');
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->demand['line'], $this->demand['unit'], '4', [DispatchWorld::pick($lot, $this->rack, '4')]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($this->demand['line']);
    $this->url = '/gudang/pengiriman/'.DB::table('stock_movements')->where('id', $shipment->movement)->value('public_id');
    $this->handle = RowHandle::of('dispatch_lines', $shipment->dispatch);
    $this->proof = (string) DB::table('evidence_documents')->where('id', DeliveryWorld::proof($this->company))->value('public_id');
});

afterEach(fn () => @unlink($this->hotFile));

function deliveryAnswer(object $test, string $url, array $body): array
{
    return $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
}

function deliveryRecord(object $test, string $qty, array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'penerima' => 'Bu Sari', 'bukti' => $test->proof, 'baris' => [['baris' => $test->handle, 'jumlah' => $qty]], ...$more];
}

it('answers INT-201 with one command_id replayed and one effect, a changed body CONFLICT, too much REJECTED with the figure (CI-05; C-30; MSG-02)', function () {
    $body = deliveryRecord($this, '2');
    $first = deliveryAnswer($this, "{$this->url}/catat", $body);
    Auth::forgetGuards();
    $again = deliveryAnswer($this, "{$this->url}/catat", $body);
    Auth::forgetGuards();

    expect($first['kind'])->toBe('COMMITTED')->and($again['kind'])->toBe('COMMITTED')
        ->and(DB::table('deliveries')->count())->toBe(1)
        ->and(deliveryAnswer($this, "{$this->url}/catat", [...$body, 'penerima' => 'Pak Lain'])['kind'])->toBe('CONFLICT');
    Auth::forgetGuards();

    $over = deliveryAnswer($this, "{$this->url}/catat", deliveryRecord($this, '3'));
    expect($over['kind'])->toBe('REJECTED')->and($over['code'])->toBe('C-30')->and($over['text'])->toContain('2');
});

it('answers INT-015 for a fungible delivery: the matches first, then the confirmation with its reason commits under the acknowledged action (L-49; BD-13; UXS-15)', function () {
    expect(deliveryAnswer($this, "{$this->url}/catat", deliveryRecord($this, '1'))['kind'])->toBe('COMMITTED');
    Auth::forgetGuards();
    $warned = deliveryAnswer($this, "{$this->url}/catat", deliveryRecord($this, '1'));
    Auth::forgetGuards();
    $matches = array_column($warned['state']['matches'] ?? [], 'id');

    expect($warned['code'])->toBe('L-49')->and($matches)->toHaveCount(1)
        ->and(deliveryAnswer($this, "{$this->url}/catat", deliveryRecord($this, '1', ['acknowledged' => $matches, 'reason' => 'Dua kiriman terpisah']))['kind'])->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'FUL.DeliveryDuplicateConfirmed')->count())->toBe(1);
});

it('answers INT-203 and INT-204 in place, one command_id replayed closing once (NX-15; CM-08; CI-05)', function () {
    $close = ['command_id' => (string) Str::uuid(), 'baris' => $this->handle, 'jenis' => 'RETURNED_UNDELIVERED', 'jumlah' => '1', 'alasan' => 'Alamat tidak ditemukan', 'tanggal' => BusinessDate::today()];

    expect(deliveryAnswer($this, "{$this->url}/tutup", $close)['kind'])->toBe('COMMITTED');
    Auth::forgetGuards();
    expect(deliveryAnswer($this, "{$this->url}/tutup", $close)['kind'])->toBe('COMMITTED')
        ->and(DB::table('delivery_closures')->count())->toBe(1);
    Auth::forgetGuards();

    expect(deliveryAnswer($this, "{$this->url}/catat", deliveryRecord($this, '2'))['kind'])->toBe('COMMITTED');
    Auth::forgetGuards();
    $delivery = DeliveryWorld::lastDelivery();
    $correct = ['command_id' => (string) Str::uuid(), 'pengiriman' => $delivery->public_id, 'baris' => RowHandle::of('delivery_lines', $delivery->line), 'alasan' => 'Tidak jadi diterima', 'tanggal' => BusinessDate::today()];

    expect(deliveryAnswer($this, "{$this->url}/koreksi", $correct)['kind'])->toBe('COMMITTED')
        ->and(DeliveryWorld::delivered($this->demand['line']))->toBe('0.000');
});
