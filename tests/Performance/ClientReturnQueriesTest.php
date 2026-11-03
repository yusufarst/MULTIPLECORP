<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\ClientReturnWorld;
use Tests\Support\DeliveryWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-15 (TASK-048; QB-02; QB-13; PFM-01; PFM-02; PFM-20): PG-034 reads a project in the same number of statements for one
// return and several; "Retur dari Klien" and "Balikkan (Tercatat Keliru)" write in the same number of statements whatever the
// line's earlier returns and reversals, never one per row read.

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
    $this->actor = ReceivingWorld::actor($this->company, ClientReturnWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->proof = ClientReturnWorld::proof($this->company);
});

afterEach(fn () => @unlink($this->hotFile));

function clientReturnStatements(callable $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    Auth::forgetGuards();

    return $count;
}

/** A project whose client received `qty` of one shipment: its page, the shipment and the shipment's consumption. */
function clientShipment(object $test, string $qty): array
{
    $demand = DispatchWorld::demand($test->world, $qty);
    $lot = StockWorld::lot($test->actor->id, $test->company, $demand['product'], $demand['unit'], $test->rack, $qty, '1000');
    expect(DispatchWorld::dispatch($test->actor->id, $test->company->id, $demand['line'], $demand['unit'], $qty, [DispatchWorld::pick($lot, $test->rack, $qty)]))->toBe('COMMITTED');
    $shipment = DispatchWorld::last($demand['line']);
    expect(DeliveryWorld::record($test->actor->id, $demand['line'], [[$shipment->dispatch, $qty]], DeliveryWorld::proof($test->company)))->toBe('COMMITTED');

    return ['page' => '/proyek/'.$demand['project']->public_id.'/retur', 'shipment' => $shipment];
}

it('reads PG-034 in the same number of statements for one return and several (QB-02; PFM-01; PFM-02)', function () {
    $few = clientShipment($this, '5');
    $many = clientShipment($this, '20');
    expect(ClientReturnWorld::restore($this->actor->id, $this->company->id, $few['shipment']->dispatch, [DispatchWorld::item($few['shipment']->consumption, $this->rack, '1')], $this->proof))->toBe('COMMITTED');

    foreach (['1', '2', '3', '4'] as $qty) {
        expect(ClientReturnWorld::restore($this->actor->id, $this->company->id, $many['shipment']->dispatch, [DispatchWorld::item($many['shipment']->consumption, $this->rack, $qty, $qty === '2' ? 'UNUSABLE' : 'USABLE')], $this->proof))->toBe('COMMITTED');
    }

    $one = clientReturnStatements(fn () => $this->actingAs($this->actor)->get($few['page'])->assertOk());
    $several = clientReturnStatements(fn () => $this->actingAs($this->actor)->get($many['page'])->assertOk());

    expect($several)->toBe($one);
});

it('records a return and reverses one in the same number of statements whatever the line\'s earlier ones (QB-13; PFM-20)', function () {
    $w = clientShipment($this, '30');
    $proof = (string) DB::table('evidence_documents')->where('id', $this->proof)->value('public_id');
    $record = fn (string $qty): int => clientReturnStatements(fn () => $this->actingAs($this->actor)->post($w['page'], [
        'command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Klien mengembalikan barang', 'bukti' => $proof, 'baris' => RowHandle::of('dispatch_lines', $w['shipment']->dispatch),
        'unit' => [['konsumsi' => RowHandle::of('stock_consumptions', $w['shipment']->consumption), 'jumlah' => $qty, 'satuan_dasar' => true, 'kondisi' => 'USABLE', 'rak' => RowHandle::of('locations', $this->rack)]],
    ])->assertRedirect($w['page']));

    $record('1');
    $second = $record('2');
    $record('3');
    $record('4');

    expect($record('5'))->toBe($second)->and(DB::table('stock_restorations')->where('kind', 'SALES_RETURN')->count())->toBe(5);

    $return = ClientReturnWorld::last();
    $url = '/retur/'.DB::table('stock_movements')->where('id', $return->movement)->value('public_id').'/balikkan';
    $reverse = fn (): int => clientReturnStatements(fn () => $this->actingAs($this->actor)->post($url, ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Tercatat keliru', 'asal' => $w['page'],
        'unit' => [['pemulihan' => RowHandle::of('stock_restorations', $return->restoration), 'jumlah' => '1']]])->assertRedirect($w['page']));

    $reverse();
    $again = $reverse();
    $reverse();

    expect($reverse())->toBe($again)->and(DB::table('stock_movements')->where('movement_type', 'MOVEMENT_REVERSAL')->count())->toBe(4);
});
