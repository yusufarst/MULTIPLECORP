<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-04, TL-06, TL-07, TL-14 (TASK-045; ROUTE-030; ROUTE-031; ROUTE-201–ROUTE-204; NAV-030; NAV-031; AZ-01; SM-01; SM-04): PG-030 and PG-031 by their capabilities, a recorded delivery answering on PG-030, each command's control present only with its capability, DOC-08's and DOC-10's drafts only from recorded facts; another company's shipment unknown.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-08');
    DocumentTypeFactory::ensure('DOC-10');
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->foreign = InvoiceWorld::make();
    $this->recorder = ReceivingWorld::actor($this->company, DeliveryWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $lot = StockWorld::lot($this->recorder->id, $this->company, $this->demand['product'], $this->demand['unit'], $this->rack, '10', '1000');
    expect(DispatchWorld::dispatch($this->recorder->id, $this->company->id, $this->line, $this->demand['unit'], '3', [DispatchWorld::pick($lot, $this->rack, '3')]))->toBe('COMMITTED');
    $this->shipment = DispatchWorld::last($this->line);
    $this->movement = (string) DB::table('stock_movements')->where('id', $this->shipment->movement)->value('public_id');
    $this->handle = RowHandle::of('dispatch_lines', $this->shipment->dispatch);
    $this->proof = (string) DB::table('evidence_documents')->where('id', DeliveryWorld::proof($this->company))->value('public_id');
});

afterEach(fn () => @unlink($this->hotFile));

function deliveryBody(object $test, string $qty = '2', array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'penerima' => 'Bu Sari', 'bukti' => $test->proof, 'baris' => [['baris' => $test->handle, 'jumlah' => $qty]], ...$more];
}

function shipmentPage(object $test, object $viewer): array
{
    return $test->actingAs($viewer)->get("/gudang/pengiriman/{$test->movement}")->assertOk()->viewData('page')['props'];
}

it('lists the shipment awaiting delivery on PG-030, records through ROUTE-201 back to PG-030, then lists the delivery recorded (NAV-030; NAV-031; QS-06; criteria 1, 2)', function () {
    $awaiting = $this->actingAs($this->recorder)->get('/gudang/pengiriman')->assertOk()->viewData('page')['props']['list'];
    expect(collect($awaiting['rows'])->pluck('id')->all())->toBe([$this->movement]);

    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/catat", deliveryBody($this, '2', ['bukti' => null]))->assertSessionHasErrors('bukti');
    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/catat", deliveryBody($this, '0'))->assertSessionHasErrors('baris');
    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/catat", deliveryBody($this, '3'))->assertRedirect('/gudang/pengiriman');
    expect(DeliveryWorld::delivered($this->line))->toBe('3.000');

    $page = shipmentPage($this, $this->recorder)['shipment'];
    expect($page['lines'][0])->toMatchArray(['shipped' => '3', 'delivered' => '3', 'remainder' => '0'])
        ->and($page['deliveries'][0]['recipient'])->toBe('Bu Sari')
        ->and($page['deliveries'][0]['proof'])->not->toBeNull()
        ->and($this->actingAs($this->recorder)->get('/gudang/pengiriman')->viewData('page')['props']['list']['rows'])->toBe([])
        ->and(collect($this->actingAs($this->recorder)->get('/gudang/pengiriman?tab=tercatat')->viewData('page')['props']['list']['rows'])->pluck('shipment')->all())->toBe([$this->movement]);
});

it('continues each PG-030 list after the row a page ended with (D-PF-02; IP-18)', function () {
    expect(DeliveryWorld::record($this->recorder->id, $this->line, [[$this->shipment->dispatch, '1']], (int) DB::table('evidence_documents')->where('public_id', $this->proof)->value('id')))->toBe('COMMITTED')
        ->and(DeliveryWorld::record($this->recorder->id, $this->line, [[$this->shipment->dispatch, '2']], (int) DB::table('evidence_documents')->where('public_id', $this->proof)->value('id')))->toBe('COMMITTED');
    $ids = DB::table('deliveries')->orderBy('id')->pluck('public_id')->all();
    $rows = fn (string $query): array => collect($this->actingAs($this->recorder)->get('/gudang/pengiriman'.$query)->assertOk()->viewData('page')['props']['list']['rows'])->pluck('id')->all();

    expect($rows('?tab=tercatat'))->toBe(array_reverse($ids))
        ->and($rows('?tab=tercatat&setelah='.$ids[1]))->toBe([$ids[0]])
        ->and($rows('?tab=tercatat&setelah='.Str::uuid()))->toBe(array_reverse($ids));
});

it('answers ROUTE-201 above the shipped quantity in place with the figure (C-30; MSG-02; criterion 2)', function () {
    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/catat", deliveryBody($this, '4'))->assertRedirect("/gudang/pengiriman/{$this->movement}");

    expect(DB::table('deliveries')->count())->toBe(0)
        ->and(DeliveryWorld::delivered($this->line))->toBe('0.000');
});

it('shows each command\'s control only with its capability, and answers another company\'s shipment as unknown (AZ-01; SM-01; SM-04; criterion 10)', function () {
    $plain = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'delivery.record', 'evidence.view']);
    $stocker = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $outsider = ReceivingWorld::actor($this->foreign->company, DeliveryWorld::CAPABILITIES);

    expect(shipmentPage($this, $plain)['can'])->toMatchArray(['record' => true, 'close' => false, 'lost' => false, 'correct' => false])
        ->and(shipmentPage($this, $this->recorder)['can'])->toMatchArray(['record' => true, 'close' => true, 'lost' => true, 'correct' => true]);
    $this->actingAs($stocker)->get("/gudang/pengiriman/{$this->movement}")->assertForbidden();

    // The closure alone, or the correction alone, opens PG-031 for its own control (ROUTE-031; RG-06).
    foreach (['delivery.close', 'fulfillment.correct'] as $capability) {
        $one = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, $capability]);
        expect(shipmentPage($this, $one)['can'])->toMatchArray(['record' => false, 'close' => $capability === 'delivery.close', 'correct' => $capability === 'fulfillment.correct']);
    }
    $this->actingAs($outsider)->get("/gudang/pengiriman/{$this->movement}")->assertNotFound();
    $this->actingAs($plain)->post("/gudang/pengiriman/{$this->movement}/tutup", ['command_id' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($plain)->post("/gudang/pengiriman/{$this->movement}/koreksi", ['command_id' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($outsider)->post("/gudang/pengiriman/{$this->movement}/catat", deliveryBody($this))->assertNotFound();
});

it('closes and corrects through ROUTE-203 and ROUTE-204 in place, each with its reason (NX-15; CM-08; IP-09; criteria 4, 6)', function () {
    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/catat", deliveryBody($this, '2'))->assertRedirect('/gudang/pengiriman');
    $delivery = DeliveryWorld::lastDelivery();
    $close = ['command_id' => (string) Str::uuid(), 'baris' => $this->handle, 'jenis' => 'REFUSED', 'jumlah' => '1', 'tanggal' => BusinessDate::today()];

    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/tutup", $close)->assertSessionHasErrors('alasan');
    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/tutup", [...$close, 'alasan' => 'Klien menolak satu unit'])->assertRedirect("/gudang/pengiriman/{$this->movement}");
    $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$this->movement}/koreksi", ['command_id' => (string) Str::uuid(), 'pengiriman' => $delivery->public_id, 'baris' => RowHandle::of('delivery_lines', $delivery->line), 'jumlah' => '1', 'alasan' => 'Salah ketik', 'tanggal' => BusinessDate::today()])
        ->assertRedirect("/gudang/pengiriman/{$this->movement}");

    expect(DB::table('delivery_closures')->where('closure_kind', 'REFUSED')->value('closed_qty_base'))->toBe('1.000')
        ->and(DeliveryWorld::delivered($this->line))->toBe('1.000')
        ->and(shipmentPage($this, $this->recorder)['shipment']['lines'][0])->toMatchArray(['delivered' => '1', 'held' => '1', 'remainder' => '1']);
});

it('drafts DOC-08 from an accepted delivery and DOC-10 only once its inspection is recorded, never from a status (WF-DOC-01; BR-DOC-04; DIR-081 OB-02; criterion 8)', function () {
    expect(DeliveryWorld::record($this->recorder->id, $this->line, [[$this->shipment->dispatch, '1']], (int) DB::table('evidence_documents')->where('public_id', $this->proof)->value('id')))->toBe('COMMITTED');
    $plain = DeliveryWorld::lastDelivery();
    expect(DeliveryWorld::record($this->recorder->id, $this->line, [[$this->shipment->dispatch, '2']], (int) DB::table('evidence_documents')->where('public_id', $this->proof)->value('id'), inspection: ['inspector' => 'Pak Adi', 'result' => 'Sesuai pesanan'], recipient: 'Pak Budi'))->toBe('COMMITTED');
    $inspected = DeliveryWorld::lastDelivery();
    $draft = fn (string $type, string $delivery) => $this->actingAs($this->recorder)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => $type, 'subjek' => ['delivery_id' => $delivery]]);

    $draft('DOC-10', $plain->public_id);
    expect(DB::table('documents')->where('document_type_code', 'DOC-10')->count())->toBe(0);
    $draft('DOC-10', $inspected->public_id)->assertRedirect();
    $draft('DOC-08', $plain->public_id)->assertRedirect();

    expect(DB::table('documents')->where('document_type_code', 'DOC-10')->where('delivery_id', $inspected->delivery)->count())->toBe(1)
        ->and(DB::table('documents')->where('document_type_code', 'DOC-08')->where('delivery_id', $plain->delivery)->count())->toBe(1);

    // ROUTE-202 issues the BAST's draft once; a second issue of it is BD-06's CONFLICT, never a second number.
    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::goLive("{$year}-01-01");
    Numbering::scheme($this->company, SequenceType::Doc08, "{$year}-01-01", 'YEAR', 'BAST/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc08, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $document = DB::table('documents')->where('document_type_code', 'DOC-08')->where('delivery_id', $plain->delivery)->sole();
    $version = DB::table('document_versions')->where('document_id', $document->id)->sole();
    $issue = fn (): mixed => $this->actingAs($this->recorder)->post("/gudang/pengiriman/{$document->public_id}/dokumen", ['command_id' => (string) Str::uuid(), 'draf' => $version->public_id, 'lock_version' => $version->lock_version, 'tanggal' => BusinessDate::today()]);
    $issue()->assertRedirect();
    $issue()->assertRedirect();

    expect(DB::table('document_versions')->where('document_id', $document->id)->where('state', 'ISSUED')->count())->toBe(1)
        ->and((string) DB::table('document_versions')->where('document_id', $document->id)->where('state', 'ISSUED')->value('formatted_number'))->toStartWith('BAST/');
});
