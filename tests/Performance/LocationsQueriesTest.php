<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-028; PERFORMANCE_TARGETS PFM-01, PFM-02): PG-057 in a number of statements that never depends on its racks
// or minimums.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->viewer = Authorization::admin([Company::factory()->create()->id], ['stock.view', 'warehouse.manage']);
});

afterEach(fn () => @unlink($this->hotFile));

function locationStatements(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('renders PG-057 in the same number of statements for few racks and many (PFM-01; PFM-02)', function () {
    $page = fn () => locationStatements(fn () => $this->actingAs($this->viewer)->get('/data-master/lokasi-rak')->assertOk());
    $page();
    $few = $page();

    foreach (range(1, 30) as $number) {
        DB::table('locations')->insert(['code' => "R-{$number}", 'name' => "Rak {$number}"]);
    }
    Product::factory()->count(20)->create(['min_stock_qty_base' => '3']);

    expect($page())->toBe($few)->and($few)->toBeLessThanOrEqual(30);
});
