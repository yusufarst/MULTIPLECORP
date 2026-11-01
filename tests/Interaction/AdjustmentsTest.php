<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-08 (TASK-042; INT-190; INT-194–INT-196; IG-05; CI-05; M-09; criterion 19): each command answered, one `command_id` replayed with one effect after re-authentication, a changed payload CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, AdjustmentWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->product = $this->demand['product'];
    $this->stocker = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->productId = (string) DB::table('products')->where('id', $this->product)->value('public_id');
    $this->lot = StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '10', '10000');
    $this->evidence = (string) DB::table('evidence_documents')->where('id', AdjustmentWorld::evidence())->value('public_id');
    Numbering::goLive(date('Y').'-01-01');
});

afterEach(fn () => @unlink($this->hotFile));

function adjustmentCommandBody(object $test): array
{
    return ['command_id' => (string) Str::uuid(), 'produk' => $test->productId, 'jenis' => 'SHRINKAGE', 'unit' => [['lot' => RowHandle::of('inventory_lots', $test->lot), 'rak' => RowHandle::of('locations', $test->rack), 'kondisi' => 'USABLE', 'jumlah' => '2']], 'bukti' => [$test->evidence], 'alasan' => 'Susut terhitung', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
}

function adjustmentCommandAnswer(object $test, object $actor, string $url, array $body): array
{
    return $test->actingAs($actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
}

it('answers each command per INT-195, INT-190 and INT-196, one command_id replayed with one effect, a changed payload CONFLICT, after re-authentication too (CI-05; M-09; criterion 19)', function () {
    $condition = ['command_id' => (string) Str::uuid(), 'aksi' => 'kondisi', 'produk' => $this->productId, 'unit' => [['lot' => RowHandle::of('inventory_lots', $this->lot), 'rak' => RowHandle::of('locations', $this->rack), 'kondisi' => 'USABLE', 'jumlah' => '1']], 'bukti' => [$this->evidence], 'alasan' => 'Penyok', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
    $twice = function (string $url, array $body): array {
        $first = adjustmentCommandAnswer($this, $this->actor, $url, $body);
        Auth::forgetGuards();

        return [$first, adjustmentCommandAnswer($this, $this->actor, $url, $body)];
    };

    [$first, $replay] = $twice('/gudang/kondisi', $condition);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('movement_type', 'CONDITION_CHANGE')->count())->toBe(1)
        ->and(adjustmentCommandAnswer($this, $this->actor, '/gudang/kondisi', [...$condition, 'alasan' => 'Alasan lain'])['kind'])->toBe('CONFLICT');

    $check = [...$condition, 'command_id' => (string) Str::uuid(), 'aksi' => 'periksa', 'bukti' => []];
    $check['unit'][0]['kondisi'] = 'UNUSABLE';
    expect(adjustmentCommandAnswer($this, $this->actor, '/gudang/kondisi', $check)['kind'])->toBe('COMMITTED');

    $other = ReceivingWorld::rack();
    $move = ['command_id' => (string) Str::uuid(), 'lot' => RowHandle::of('inventory_lots', $this->lot), 'dari' => RowHandle::of('locations', $this->rack), 'ke' => RowHandle::of('locations', $other), 'kondisi' => 'USABLE', 'jumlah' => '2', 'kebijakan_seri' => false, 'kebijakan_skala' => 0, 'tanggal' => BusinessDate::today()];
    [$first, $replay] = $twice("/gudang/stok/{$this->productId}/pindah", $move);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('movement_type', 'LOCATION_MOVE')->count())->toBe(1);

    $adjust = adjustmentCommandBody($this);
    [$first, $replay] = $twice('/gudang/penyesuaian', $adjust);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(1)
        ->and(adjustmentCommandAnswer($this, $this->actor, '/gudang/penyesuaian', [...$adjust, 'alasan' => 'Lain'])['kind'])->toBe('CONFLICT');

    $movement = (string) DB::table('stock_movements')->where('movement_type', 'ADJUSTMENT_DECREASE')->value('public_id');
    $reverse = ['command_id' => (string) Str::uuid(), 'alasan' => 'Keliru dicatat', 'tanggal' => BusinessDate::today()];
    [$first, $replay] = $twice("/gudang/penyesuaian/{$movement}/balikkan", $reverse);
    expect($first['kind'])->toBe('COMMITTED')->and($replay['kind'])->toBe('COMMITTED')->and(DB::table('stock_restorations')->where('kind', 'LOSS_REVERSAL')->count())->toBe(1);
});
