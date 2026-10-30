<?php

use App\Modules\OPS\Queries\ActivityHistory;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\AuditEventFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-061; PF-13; PF-41; IX-13; criterion 6) on real PostgreSQL: PG-063 reads one bounded branch per company of
// the Owner's scope and one for the events without a company, merged newest first in one statement; a company filter
// narrows the branches, and the merged order holds across them — an event recorded at the same instant in two
// companies ordered by its id.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->owner = Authorization::owner();
    $this->companies = Company::factory()->count(3)->create();
});

/** @return array{rows: list<array<string, mixed>>, statements: list<string>} */
function branchesRead(object $test, ?string $company = null): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $screen = app(ActivityHistory::class)->screen($test->owner->id, ['dari' => null, 'sampai' => null, 'perusahaan' => $company, 'pelaku' => null, 'catatan' => null, 'setelah' => null]);
    $statements = array_values(array_filter(array_map(fn (array $query): string => (string) $query['query'], DB::getQueryLog()), fn (string $sql): bool => str_contains($sql, 'audit_events')));
    DB::disableQueryLog();

    return ['rows' => $screen['rows'], 'statements' => $statements];
}

it('merges the branch of each company and the one without a company in one statement, newest first (PF-41; IX-13)', function () {
    $start = CarbonImmutable::parse('2026-10-01 00:00:00+00');

    foreach (range(1, 8) as $minute) {
        AuditEventFactory::new()->createOne(['occurred_at' => $start->addMinutes($minute)->toIso8601String(), 'company_id' => $minute % 4 === 0 ? null : $this->companies[$minute % 3]->id,
            'action' => 'PRJ.ProjectCreated', 'authority_class' => 'ADM', 'reason' => "n{$minute}"]);
    }

    // Two companies' events at one instant: the later id first.
    AuditEventFactory::new()->createOne(['occurred_at' => $start->addMinutes(9)->toIso8601String(), 'company_id' => $this->companies[0]->id, 'action' => 'PRJ.ProjectCreated', 'authority_class' => 'ADM', 'reason' => 'same-a']);
    AuditEventFactory::new()->createOne(['occurred_at' => $start->addMinutes(9)->toIso8601String(), 'company_id' => $this->companies[1]->id, 'action' => 'PRJ.ProjectCreated', 'authority_class' => 'ADM', 'reason' => 'same-b']);

    $all = branchesRead($this);

    expect($all['statements'])->toHaveCount(1)
        // Three companies and the branch without a company: three unions, one `company_id is null`.
        ->and(substr_count($all['statements'][0], 'union all'))->toBe(3)
        ->and(substr_count($all['statements'][0], '"company_id" is null'))->toBe(1)
        ->and(array_column($all['rows'], 'reason'))->toBe(['same-b', 'same-a', 'n8', 'n7', 'n6', 'n5', 'n4', 'n3', 'n2', 'n1']);

    $one = branchesRead($this, (string) $this->companies[1]->code);
    expect($one['statements'])->toHaveCount(1)
        ->and(substr_count($one['statements'][0], 'union all'))->toBe(0)
        // Minutes 1 and 7 fell to the second company (minute 4 had none).
        ->and(array_column($one['rows'], 'reason'))->toBe(['same-b', 'n7', 'n1']);
});
