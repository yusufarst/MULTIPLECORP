<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Queries\CountQueries;
use App\Modules\INV\Queries\DiscrepancyQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\CountWorld;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-15 (TASK-043; PFM-01; PFM-02; PFM-20; QB-05; QB-14): PG-026 and PG-029 the same for one row and many; AX-06, AX-07 and AX-37 proportional to their rows.

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
    $this->foreignStocker = ReceivingWorld::actor($this->foreign->company, DispatchWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->evidence = AdjustmentWorld::evidence();
    $this->owner = Authorization::owner();
});

afterEach(fn () => @unlink($this->hotFile));

function countStatements(callable $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('reads the counts in one statement and more, the same for one row and many (PFM-01; PFM-02; QB-05)', function () {
    $queries = app(CountQueries::class);
    StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    CountWorld::open($this->actor->id, $this->product, $this->rack);
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $one = countStatements(fn () => $queries->list($this->actor->id, null, null));
    CountWorld::open($this->actor->id, $this->product, $this->rack);
    CountWorld::open($this->actor->id, $this->product, $this->rack);

    expect(countStatements(fn () => $queries->list($this->actor->id, null, null)))->toBe($one)->and($one)->toBeLessThanOrEqual(2);

    $count = (int) DB::table('stock_counts')->max('id');
    $public = (string) DB::table('stock_counts')->where('id', $count)->value('public_id');
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $detail = countStatements(fn () => $queries->count($this->actor->id, $public));
    expect(CountWorld::line($this->actor->id, $count, $this->product, $this->rack, '1'))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor->id, $count, $this->product, $this->rack, '2'))->toBe('COMMITTED')
        ->and(countStatements(fn () => $queries->count($this->actor->id, $public)))->toBe($detail)->and($detail)->toBeLessThanOrEqual(14);
});

it('reads PG-029 the same for one case and several (PFM-01; QB-05)', function () {
    $queries = app(DiscrepancyQueries::class);
    StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    StockWorld::lot($this->foreignStocker->id, $this->foreign->company, $this->product, $this->unit, $this->rack, '10', '1000');
    app(EffectiveCapabilities::class)->for($this->owner->id);
    $record = function (string $qty): void {
        $count = CountWorld::open($this->actor->id, $this->product, $this->rack);
        $system = (string) DB::table('stock_count_scopes')->where('stock_count_id', $count)->where('condition', 'USABLE')->value('system_qty_at_start');
        expect(CountWorld::line($this->actor->id, $count, $this->product, $this->rack, (string) ((int) $system - (int) $qty)))->toBe('COMMITTED')
            ->and(CountWorld::apply($this->actor->id, CountWorld::entry($count, pending: $qty, evidence: [$this->evidence])))->toBe('COMMITTED');
    };
    $record('1');
    $one = countStatements(fn () => $queries->cases($this->owner->id));
    $record('2');

    expect(countStatements(fn () => $queries->cases($this->owner->id)))->toBeLessThanOrEqual($one + 2)->and($one)->toBeLessThanOrEqual(8);
});

it('applies a shortage of ten lots, attributes a finding and resolves a case in statements proportional to their rows (PFM-20; QB-14)', function () {
    $lots = array_map(fn (): int => StockWorld::lot($this->stocker->id, $this->company, $this->product, $this->unit, $this->rack, '2', '1000'), range(1, 10));
    $apply = function (array $take): int {
        $count = CountWorld::open($this->actor->id, $this->product, $this->rack);
        $system = (string) DB::table('stock_count_scopes')->where('stock_count_id', $count)->where('condition', 'USABLE')->value('system_qty_at_start');
        expect(CountWorld::line($this->actor->id, $count, $this->product, $this->rack, (string) ((int) $system - count($take))))->toBe('COMMITTED');
        $entry = CountWorld::entry($count, array_map(fn (int $lot): array => AdjustmentWorld::pick($lot, $this->rack, '1'), $take), evidence: [$this->evidence]);
        app(EffectiveCapabilities::class)->for($this->actor->id);

        return countStatements(fn () => expect(CountWorld::apply($this->actor->id, $entry))->toBe('COMMITTED'));
    };
    $one = $apply([$lots[0]]);
    $nine = $apply(array_slice($lots, 1));
    expect($nine)->toBeLessThanOrEqual($one + 8 * 24);

    // AX-07: a flagged lot in a bounded statement count.
    $count = CountWorld::open($this->actor->id, $this->product, $this->rack);
    $system = (string) DB::table('stock_count_scopes')->where('stock_count_id', $count)->where('condition', 'USABLE')->value('system_qty_at_start');
    expect(CountWorld::line($this->actor->id, $count, $this->product, $this->rack, (string) ((int) $system + 2)))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor->id, CountWorld::entry($count)))->toBe('COMMITTED');
    $finding = (int) DB::table('stock_count_findings')->where('state', 'OPEN')->value('id');
    expect(countStatements(fn () => expect(CountWorld::attribute($this->owner->id, $finding, $this->company->id, '500'))->toBe('COMMITTED')))->toBeLessThan(120);
});
