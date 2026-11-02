<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReturnWorld;
use Tests\Support\StockWorld;

// TL-08 (TASK-044; INT-176; INT-177; IG-05; CI-05): each command through its route — success, validation, a rejection named, one `command_id` replayed with one effect, the same identifier with another body CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, ReturnWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->lot = StockWorld::lot($this->actor->id, $this->company, $this->demand['product'], $this->demand['unit'], $this->rack, '10', '12000');
    $this->purchase = (string) DB::table('purchases')->where('id', ReturnWorld::purchaseOf($this->lot))->value('public_id');
});

afterEach(fn () => @unlink($this->hotFile));

function returnAnswer(object $test, string $url, array $body): array
{
    return $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
}

function returnTwice(object $test, string $url, array $body): array
{
    $first = returnAnswer($test, $url, $body);
    Auth::forgetGuards();

    return [$first, returnAnswer($test, $url, $body)];
}

it('answers INT-176 with one command_id replayed and one effect, a changed body CONFLICT, too much REJECTED with the figure, a missing reason at its field (CI-05; C-03; MSG-12)', function () {
    $url = "/pembelian/{$this->purchase}/retur";
    $body = ['command_id' => (string) Str::uuid(), 'lot' => RowHandle::of('inventory_lots', $this->lot), 'unit' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '2']], 'alasan' => 'Rusak dari pemasok', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];

    [$first, $replay] = returnTwice($this, $url, $body);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')
        ->and(DB::table('correction_cases')->where('case_kind', 'PURCHASE_RETURN')->count())->toBe(1)
        ->and(returnAnswer($this, $url, [...$body, 'alasan' => 'Alasan lain'])['kind'])->toBe('CONFLICT');

    $tooMuch = returnAnswer($this, $url, [...$body, 'command_id' => (string) Str::uuid(), 'unit' => [['rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '9']]]);
    expect($tooMuch['kind'])->toBe('REJECTED')->and($tooMuch['code'])->toBe('C-03');

    $this->actingAs($this->actor)->post($url, [...$body, 'command_id' => (string) Str::uuid(), 'alasan' => ''])->assertSessionHasErrors('alasan');
});

it('answers INT-177 in place with one command_id replayed and one effect, a replacement above the units returned REJECTED (CI-05; C-29)', function () {
    expect(ReturnWorld::returnUnits($this->actor->id, $this->lot, [['lot' => $this->lot, 'location' => $this->rack, 'condition' => 'USABLE', 'qty' => '2', 'serial' => null]]))->toBe('COMMITTED');
    $case = (string) DB::table('correction_cases')->where('id', ReturnWorld::lastCase())->value('public_id');
    $url = "/pembelian/{$this->purchase}/retur/{$case}/hasil";
    $body = ['command_id' => (string) Str::uuid(), 'hasil' => 'REPLACEMENT', 'jumlah' => '1', 'alasan' => 'Pemasok mengganti', 'tanggal' => BusinessDate::today()];

    [$first, $replay] = returnTwice($this, $url, $body);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')
        ->and((string) DB::table('purchase_line_balances')->where('purchase_line_id', DB::table('purchase_lines')->where('purchase_id', ReturnWorld::purchaseOf($this->lot))->value('id'))->value('replacement_expected_qty_base'))->toBe('1.000');

    $above = returnAnswer($this, $url, [...$body, 'command_id' => (string) Str::uuid(), 'jumlah' => '2']);
    expect($above['kind'])->toBe('REJECTED')->and($above['code'])->toBe('C-29');
});
