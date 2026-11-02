<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\AdjustmentQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-15 (TASK-042; PFM-01; PFM-02; PFM-20; QB-05; QB-14): PG-027 within QB-05, the same for one lot and many; a condition change and an adjustment of ten lots proportional to their rows.

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
    $this->evidence = AdjustmentWorld::evidence();
});

afterEach(fn () => @unlink($this->hotFile));

function adjustmentStatements(callable $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** @return list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}> */
function adjustmentLots(object $test, int $lots): array
{
    return array_map(fn (): array => AdjustmentWorld::pick(StockWorld::lot($test->stocker->id, $test->company, $test->product, $test->demand['unit'], $test->rack, '2', '10000'), $test->rack, '1'), range(1, $lots));
}

it('reads PG-027 within QB-05, the same for one lot and many (PFM-01; PFM-02)', function () {
    $queries = app(AdjustmentQueries::class);
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $one = adjustmentStatements(fn () => $queries->screen($this->actor->id, $this->product));
    StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '2');
    StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->demand['unit'], $this->rack, '2');

    expect(adjustmentStatements(fn () => $queries->screen($this->actor->id, $this->product)))->toBe($one)->and($one)->toBeLessThanOrEqual(4);
});

it('records a condition change and an adjustment of ten lots in statements proportional to their rows (PFM-20; QB-14)', function (string $command, int $perLot) {
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $run = fn (array $picks): int => adjustmentStatements(function () use ($command, $picks) {
        $entry = AdjustmentWorld::entry($this->product, $picks, evidence: [$this->evidence]);
        expect(AdjustmentWorld::{$command}($this->actor->id, $entry))->toBe('COMMITTED');
    });
    $one = $run(adjustmentLots($this, 1));
    $ten = $run(adjustmentLots($this, 10));

    expect($ten)->toBeLessThanOrEqual($one + 9 * $perLot);
})->with([['change', 12], ['adjust', 24]]);
