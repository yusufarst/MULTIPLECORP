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

// TL-08 (TASK-048; INT-210; INT-211; INT-015; IG-05; CI-05): each command through its route — success, a rejection named, one
// `command_id` replayed with one effect, the same identifier with another body CONFLICT, a likely duplicate return confirmed.

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
    $demand = DispatchWorld::demand($this->world);
    $lot = StockWorld::lot($this->actor->id, $this->company, $demand['product'], $demand['unit'], $this->rack, '10', '1000');
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $demand['line'], $demand['unit'], '4', [DispatchWorld::pick($lot, $this->rack, '4')]))->toBe('COMMITTED');
    $this->shipment = DispatchWorld::last($demand['line']);
    expect(DeliveryWorld::record($this->actor->id, $demand['line'], [[$this->shipment->dispatch, '4']], DeliveryWorld::proof($this->company)))->toBe('COMMITTED');
    $this->url = '/proyek/'.$demand['project']->public_id.'/retur';
    $this->proof = (string) DB::table('evidence_documents')->where('id', ClientReturnWorld::proof($this->company))->value('public_id');
});

afterEach(fn () => @unlink($this->hotFile));

function clientReturnInteraction(object $test, string $url, array $body): array
{
    $answer = $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

function clientReturnRequest(object $test, string $qty, string $condition = 'USABLE'): array
{
    return ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Klien mengembalikan barang', 'bukti' => $test->proof, 'baris' => RowHandle::of('dispatch_lines', $test->shipment->dispatch),
        'unit' => [['konsumsi' => RowHandle::of('stock_consumptions', $test->shipment->consumption), 'jumlah' => $qty, 'satuan_dasar' => true, 'kondisi' => $condition, 'rak' => RowHandle::of('locations', $test->rack)]]];
}

it('answers INT-210 with one command_id replayed and one effect, a changed body CONFLICT, too much REJECTED with the figure, a likely duplicate confirmed with its reason (CI-05; C-30; INT-015; L-49)', function () {
    $body = clientReturnRequest($this, '1');

    expect(clientReturnInteraction($this, $this->url, $body)['kind'])->toBe('COMMITTED')
        ->and(clientReturnInteraction($this, $this->url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('stock_restorations')->where('kind', 'SALES_RETURN')->count())->toBe(1)
        ->and(clientReturnInteraction($this, $this->url, [...$body, 'alasan' => 'Alasan lain sama sekali'])['kind'])->toBe('CONFLICT');

    $over = clientReturnInteraction($this, $this->url, clientReturnRequest($this, '9'));
    expect($over['kind'])->toBe('REJECTED')->and($over['code'])->toBe('C-30')->and($over['text'])->toContain('3');

    $again = clientReturnRequest($this, '1');
    $warned = clientReturnInteraction($this, $this->url, $again);
    $matches = array_column($warned['state']['matches'] ?? [], 'id');

    expect($warned['code'])->toBe('L-49')->and($matches)->toHaveCount(1)
        ->and(clientReturnInteraction($this, $this->url, [...$again, 'command_id' => (string) Str::uuid(), 'acknowledged' => $matches, 'reason' => 'Dua kardus terpisah'])['kind'])->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'INV.ClientReturnDuplicateConfirmed')->count())->toBe(1);
});

it('answers INT-211 in place with one command_id replayed reversing once, a changed body CONFLICT, above what still stands REJECTED (CI-05; C-55)', function () {
    expect(clientReturnInteraction($this, $this->url, clientReturnRequest($this, '2'))['kind'])->toBe('COMMITTED');
    $return = ClientReturnWorld::last();
    $url = '/retur/'.DB::table('stock_movements')->where('id', $return->movement)->value('public_id').'/balikkan';
    $body = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Tercatat keliru', 'asal' => $this->url,
        'unit' => [['pemulihan' => RowHandle::of('stock_restorations', $return->restoration), 'jumlah' => '1']]];

    expect(clientReturnInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(clientReturnInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('stock_movements')->where('movement_type', 'MOVEMENT_REVERSAL')->count())->toBe(1)
        ->and(clientReturnInteraction($this, $url, [...$body, 'alasan' => 'Alasan lain sama sekali'])['kind'])->toBe('CONFLICT');

    $over = clientReturnInteraction($this, $url, [...$body, 'command_id' => (string) Str::uuid(), 'unit' => [['pemulihan' => RowHandle::of('stock_restorations', $return->restoration), 'jumlah' => '2']]]);

    expect($over['kind'])->toBe('REJECTED')->and($over['code'])->toBe('C-55')->and($over['text'])->toBe(__('returns_client.refusal.reversal', ['sisa' => '1']));
});
