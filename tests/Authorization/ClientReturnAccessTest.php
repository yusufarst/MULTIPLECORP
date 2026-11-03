<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\ClientReturnWorld;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-06, TL-14 (TASK-048; AZ-01; RG-06; SM-01; SM-04; CS-03; criterion 9): ROUTE-034 and ROUTE-210 with `sales_return.record`,
// ROUTE-211 with `stock.reverse`, each a separately granted ADM_PLUS capability and each control absent without it; another
// company's project and return unknown, each command writing nothing.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->owner = ReceivingWorld::actor($this->company, ClientReturnWorld::CAPABILITIES);
    $rack = ReceivingWorld::rack();
    $demand = DispatchWorld::demand($this->world);
    $lot = StockWorld::lot($this->owner->id, $this->company, $demand['product'], $demand['unit'], $rack, '10', '1000');
    expect(DispatchWorld::dispatch($this->owner->id, $this->company->id, $demand['line'], $demand['unit'], '3', [DispatchWorld::pick($lot, $rack, '3')]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($demand['line']);
    expect(DeliveryWorld::record($this->owner->id, $demand['line'], [[$shipment->dispatch, '3']], DeliveryWorld::proof($this->company)))->toBe('COMMITTED')
        ->and(ClientReturnWorld::restore($this->owner->id, $this->company->id, $shipment->dispatch, [DispatchWorld::item($shipment->consumption, $rack, '1')], ClientReturnWorld::proof($this->company)))->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    $this->page = '/proyek/'.$demand['project']->public_id.'/retur';
    $this->reverse = '/retur/'.DB::table('stock_movements')->where('id', $return->movement)->value('public_id').'/balikkan';
    $this->record = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Klien mengembalikan barang', 'bukti' => (string) DB::table('evidence_documents')->where('id', ClientReturnWorld::proof($this->company))->value('public_id'),
        'baris' => RowHandle::of('dispatch_lines', $shipment->dispatch), 'unit' => [['konsumsi' => RowHandle::of('stock_consumptions', $shipment->consumption), 'jumlah' => '1', 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => RowHandle::of('locations', $rack)]]];
    $this->undo = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Tercatat keliru', 'asal' => $this->page,
        'unit' => [['pemulihan' => RowHandle::of('stock_restorations', $return->restoration), 'jumlah' => '1']]];
});

afterEach(fn () => @unlink($this->hotFile));

it('refuses PG-034, the return and its reversal to an account holding neither capability (AZ-01; SM-04; criterion 9)', function () {
    $viewer = Authorization::admin([$this->company->id], ['projects.view', 'stock.view', 'delivery.record']);

    $this->actingAs($viewer)->get($this->page)->assertForbidden();
    $this->actingAs($viewer)->post($this->page, $this->record)->assertForbidden();
    $this->actingAs($viewer)->post($this->reverse, $this->undo)->assertForbidden();

    expect(DB::table('stock_restorations')->count())->toBe(1)->and(DB::table('stock_movements')->where('movement_type', 'MOVEMENT_REVERSAL')->count())->toBe(0);
});

it('grants the return with `sales_return.record` alone and the reversal with `stock.reverse` alone, each control shown by its own (RG-06; criterion 9)', function () {
    $returner = Authorization::admin([$this->company->id], ['projects.view', 'stock.view', 'sales_return.record', 'evidence.view']);
    $reverser = Authorization::admin([$this->company->id], ['projects.view', 'stock.view', 'stock.reverse']);

    $this->actingAs($returner)->get($this->page)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('page.can.record', true)->where('page.can.reverse', false)->where('page.can.reserve', false));
    $this->actingAs($returner)->post($this->reverse, $this->undo)->assertForbidden();
    $this->actingAs($reverser)->get($this->page)->assertForbidden();
    $this->actingAs($reverser)->post($this->page, $this->record)->assertForbidden();

    expect(DB::table('stock_movements')->where('movement_type', 'MOVEMENT_REVERSAL')->count())->toBe(0);

    $this->actingAs($reverser)->post($this->reverse, $this->undo)->assertRedirect($this->page);

    expect(DB::table('stock_movements')->where('movement_type', 'MOVEMENT_REVERSAL')->count())->toBe(1);
});

it('answers another company\'s project and return as unknown, each command writing nothing (SM-01; CS-03)', function () {
    $stranger = ReceivingWorld::actor(InvoiceWorld::make()->company, ClientReturnWorld::CAPABILITIES);

    $this->actingAs($stranger)->get($this->page)->assertNotFound();
    $this->actingAs($stranger)->post($this->page, $this->record)->assertNotFound();
    $this->actingAs($stranger)->post($this->reverse, $this->undo)->assertNotFound();

    expect(DB::table('stock_restorations')->count())->toBe(1)->and(DB::table('stock_movements')->where('movement_type', 'MOVEMENT_REVERSAL')->count())->toBe(0);
});
