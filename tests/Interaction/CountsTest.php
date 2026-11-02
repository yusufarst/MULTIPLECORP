<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\CountWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-08 (TASK-043; INT-191–INT-193; INT-197–INT-200; INT-215; IG-05; CI-05; criteria 11, 13): each command answered, one `command_id` replayed with one effect after re-authentication, a changed payload CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->foreign = InvoiceWorld::make();
    $this->actor = ReceivingWorld::actor($this->company, CountWorld::CAPABILITIES);
    $this->stocker = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->product = $this->demand['product'];
    $this->productId = (string) DB::table('products')->where('id', $this->product)->value('public_id');
    $this->rackHandle = RowHandle::of('locations', $this->rack);
    $this->lot = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '10', '10000');
    $this->evidence = (string) DB::table('evidence_documents')->where('id', AdjustmentWorld::evidence())->value('public_id');
    $this->owner = Authorization::owner();
});

afterEach(fn () => @unlink($this->hotFile));

function countAnswer(object $test, object $actor, string $url, array $body): array
{
    return $test->actingAs($actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
}

function countTwice(object $test, object $actor, string $url, array $body): array
{
    $first = countAnswer($test, $actor, $url, $body);
    Auth::forgetGuards();

    return [$first, countAnswer($test, $actor, $url, $body)];
}

it('answers INT-191, INT-192 and INT-193 with one command_id replayed and one effect; a stale count MSG-30, a changed payload CONFLICT (CI-05; ST-03)', function () {
    $open = ['command_id' => (string) Str::uuid(), 'produk' => $this->productId, 'rak' => $this->rackHandle, 'kondisi' => 'USABLE'];
    [$first, $replay] = countTwice($this, $this->actor, '/gudang/stok-opname', $open);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_counts')->count())->toBe(1)
        ->and(countAnswer($this, $this->actor, '/gudang/stok-opname', [...$open, 'kondisi' => 'UNUSABLE'])['kind'])->toBe('CONFLICT');
    $public = (string) DB::table('stock_counts')->value('public_id');

    $line = ['command_id' => (string) Str::uuid(), 'rak' => $this->rackHandle, 'kondisi' => 'USABLE', 'jumlah' => '8'];
    [$first, $replay] = countTwice($this, $this->actor, "/gudang/stok-opname/{$public}/baris", $line);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_count_lines')->count())->toBe(1);

    $apply = ['command_id' => (string) Str::uuid(), 'unit' => [['lot' => RowHandle::of('inventory_lots', $this->lot), 'jumlah' => '2']], 'bukti' => [$this->evidence], 'alasan' => 'Selisih hitung', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
    [$first, $replay] = countTwice($this, $this->actor, "/gudang/stok-opname/{$public}/terapkan", $apply);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(1)
        ->and(DB::table('stock_counts')->value('state'))->toBe('APPLIED');

    // A stale count says MSG-30.
    $stale = CountWorld::open($this->actor->id, $this->product, $this->rack);
    $stalePublic = (string) DB::table('stock_counts')->where('id', $stale)->value('public_id');
    expect(AdjustmentWorld::move(ReceivingWorld::actor($this->company, ['stock.view', 'stock.move'])->id, $this->product, $this->lot, $this->rack, ReceivingWorld::rack(), '1'))->toBe('COMMITTED');
    $answer = countAnswer($this, $this->actor, "/gudang/stok-opname/{$stalePublic}/terapkan", [...$apply, 'command_id' => (string) Str::uuid()]);
    expect($answer['kind'])->toBe('CONFLICT')->and($answer['code'])->toBe('ST-03')->and($answer['text'])->toContain('kedaluwarsa');
});

it('answers INT-215: an open count cancelled once, replayed with one effect, a later one refused (`DIR-100`)', function () {
    $count = CountWorld::open($this->actor->id, $this->product, $this->rack);
    $public = (string) DB::table('stock_counts')->where('id', $count)->value('public_id');
    [$first, $replay] = countTwice($this, $this->actor, "/gudang/stok-opname/{$public}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah rak']);

    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'INV.CountCancelled')->count())->toBe(1)
        ->and(countAnswer($this, $this->actor, "/gudang/stok-opname/{$public}/batalkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah rak'])['kind'])->toBe('REJECTED');
});

it('answers INT-197, INT-198, INT-199 and INT-200, each replayed with one effect (AX-07; AX-37; OD-10; CI-05)', function () {
    $foreignStocker = ReceivingWorld::actor($this->foreign->company, DispatchWorld::CAPABILITIES);
    $other = StockWorld::lot($foreignStocker->id, $this->foreign->company, $this->product, $this->demand['unit'], $this->rack, '5', '12000');
    $count = CountWorld::open($this->actor->id, $this->product, $this->rack);
    expect(CountWorld::line($this->actor->id, $count, $this->product, $this->rack, '13'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor->id, CountWorld::entry($count, pending: '2', evidence: [AdjustmentWorld::evidence()])))->toBe('COMMITTED');
    $case = DB::table('unattributed_loss_cases')->sole();
    $today = BusinessDate::today();

    $resolve = ['command_id' => (string) Str::uuid(), 'baris' => [['perusahaan' => $this->foreign->company->public_id, 'tutup' => [['lot' => RowHandle::of('inventory_lots', $other), 'jumlah' => '2']]]], 'alasan' => 'Keputusan Owner', 'tanggal' => $today];
    [$first, $replay] = countTwice($this, $this->owner, "/gudang/selisih-temuan/{$case->public_id}/tetapkan", $resolve);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('unattributed_loss_resolutions')->count())->toBe(1);

    $resolution = RowHandle::of('unattributed_loss_resolutions', (int) DB::table('unattributed_loss_resolutions')->value('id'));
    [$first, $replay] = countTwice($this, $this->owner, "/gudang/selisih-temuan/{$resolution}/balikkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Keliru', 'tanggal' => $today]);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('movement_type', 'UNATTRIBUTED_RESOLUTION_REVERSAL')->count())->toBe(1);

    // A refused evidence resolution names its condition; the later proof commits it.
    $holder = Authorization::admin([$this->company->id], [...CountWorld::CAPABILITIES, 'stock.resolve_unattributed_evidence']);
    $closes = [['lot' => RowHandle::of('inventory_lots', $this->lot), 'jumlah' => '2']];
    expect(countAnswer($this, $holder, "/gudang/selisih-temuan/{$case->public_id}/tetapkan-bukti", ['command_id' => (string) Str::uuid(), 'tutup' => $closes, 'alasan' => 'Bukti lot', 'tanggal' => $today])['code'])->toBe('OD-10');
    $later = (string) DB::table('evidence_documents')->where('id', AdjustmentWorld::evidence(['recorded_at' => now()->addMinute()]))->value('public_id');
    $evidence = ['command_id' => (string) Str::uuid(), 'tutup' => $closes, 'bukti' => [$later], 'alasan' => 'Bukti lot', 'tanggal' => $today];
    [$first, $replay] = countTwice($this, $holder, "/gudang/selisih-temuan/{$case->public_id}/tetapkan-bukti", $evidence);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('unattributed_loss_resolutions')->where('resolution_kind', 'EVIDENCE_IDENTIFIED')->count())->toBe(1);

    // INT-197 on a finding of another rack.
    $rack = ReceivingWorld::rack();
    $surplus = CountWorld::open($this->actor->id, $this->product, $rack);
    expect(CountWorld::line($this->actor->id, $surplus, $this->product, $rack, '3'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor->id, CountWorld::entry($surplus)))->toBe('COMMITTED');
    $finding = RowHandle::of('stock_count_findings', (int) DB::table('stock_count_findings')->where('state', 'OPEN')->value('id'));
    [$first, $replay] = countTwice($this, $this->owner, "/gudang/selisih-temuan/{$finding}/surplus", ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'dasar_biaya' => '3000', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'alasan' => 'Stok ditemukan', 'tanggal' => $today]);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('inventory_lots')->where('acquisition_kind', 'SURPLUS')->count())->toBe(1);
});
