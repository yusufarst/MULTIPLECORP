<?php

use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientOrganization;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\PartyRaces;

// TL-05 (TASK-026; BD-16; SQ-08; LK-08; criterion 7): two creations of one unit name in one organization, under real
// PostgreSQL sessions, give one COMMITTED and one CONFLICT — the second waits on the organization row and finds the
// first's unit, the same answer the unique key gives a lost race — and the organization keeps one unit of that name.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $company = Company::factory()->create();
    // Two admins: each session's command holds its own actor's row at LK-03 (RV-01).
    $this->first = Authorization::admin([$company->id], ['projects.view', 'parties.manage']);
    $this->second = Authorization::admin([$company->id], ['projects.view', 'parties.manage']);
    $this->organization = ClientOrganization::factory()->create();
});

it('commits one of two concurrent creations of a unit name and answers the other CONFLICT (criterion 7; BD-16)', function () {
    [$first, $second, $organization] = [$this->first->id, $this->second->id, $this->organization->id];

    $results = Sessions::run([
        'first' => static fn (): string => PartyRaces::holdThenAddUnit($first, $organization, 'Bagian Umum'),
        'second' => static function () use ($second, $organization): string {
            Barrier::await('the first creation holds the organization row');

            return PartyRaces::addUnit($second, $organization, 'bagian  umum');
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'CONFLICT SQ-08'])
        ->and(DB::table('client_units')->where('client_organization_id', $organization)->count())->toBe(1);
});
