<?php

use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\LocationRaces;

// TL-05 (TASK-028; BD-16; SQ-08; criterion 2): two creations of one rack code under real PostgreSQL sessions give one
// COMMITTED and one CONFLICT — the second waits on the first's unique key and loses — and one rack keeps the code.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $company = Company::factory()->create();
    // Two managers: each session's command holds its own actor's row at LK-03 (RV-01).
    $this->first = Authorization::admin([$company->id], ['stock.view', 'warehouse.manage']);
    $this->second = Authorization::admin([$company->id], ['stock.view', 'warehouse.manage']);
});

it('commits one of two concurrent creations of one code and answers the other CONFLICT (criterion 2; BD-16)', function () {
    [$first, $second] = [$this->first->id, $this->second->id];

    $results = Sessions::run([
        'first' => static fn (): string => LocationRaces::holdThenCreate($first, 'R-RACE'),
        'second' => static function () use ($second): string {
            Barrier::await('the first rack is written');

            return LocationRaces::create($second, 'R-RACE');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toStartWith('CONFLICT')
        ->and(DB::table('locations')->where('code', 'R-RACE')->count())->toBe(1);
});
