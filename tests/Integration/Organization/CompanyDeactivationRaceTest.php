<?php

use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\OrganizationRaces;

// TL-05 (TASK-025; BR-ACC-03; C-54; LK-06; criterion 7): a deactivation and a command that starts new business for the
// company, under real PostgreSQL sessions, run one after the other on the company row: the new business committed
// first and the deactivation after it, or the deactivation first and the new business REJECTED — never both against
// stale state —, while a payment or a correction, which does not ask the company row, still commits after it.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    // Two Owners: each session's command holds its own actor's row at LK-03 (RV-01).
    $this->owner = Authorization::owner();
    $this->other = Authorization::owner();
    $this->company = Company::factory()->create();
});

it('refuses new business once the deactivation holding the company row has committed (C-54; LK-06)', function () {
    [$owner, $other, $company] = [$this->owner->id, $this->other->id, $this->company->id];

    $results = Sessions::run([
        'deactivation' => static fn (): string => OrganizationRaces::holdThenDeactivate($owner, $company),
        'new business' => static function () use ($other, $company): string {
            Barrier::await('the deactivation holds the company row');

            return OrganizationRaces::newBusiness($other, $company);
        },
    ]);

    expect($results)->toBe(['deactivation' => 'COMMITTED', 'new business' => 'REJECTED C-54'])
        ->and(OrganizationRaces::existingRecordCorrection($owner, $company))->toBe('COMMITTED')
        ->and(DB::table('companies')->where('id', $company)->value('is_active'))->toBeFalse();
});

it('commits new business started first and the deactivation after it (LK-06)', function () {
    [$owner, $other, $company] = [$this->owner->id, $this->other->id, $this->company->id];

    $results = Sessions::run([
        'new business' => static fn (): string => OrganizationRaces::holdThenNewBusiness($other, $company),
        'deactivation' => static function () use ($owner, $company): string {
            Barrier::await('the new business holds the company row');

            return OrganizationRaces::deactivate($owner, $company);
        },
    ]);

    expect($results)->toBe(['new business' => 'COMMITTED', 'deactivation' => 'COMMITTED']);
});
