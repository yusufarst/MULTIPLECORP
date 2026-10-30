<?php

use App\Modules\OPS\Queries\ActivityHistory;
use App\Modules\OPS\Queries\SecurityLog;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\AuditEventFactory;
use Database\Factories\OPS\SecurityEventFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-061; QB-12; PFM-01; PFM-02; criterion 6): PG-063 and PG-064 read a page in the same number of statements
// for a few events and for more than a page; each filter adds a bounded lookup, never a statement per row. The merged
// branches' one statement is TL-05's (tests/Integration/Audit/ActivityBranchesTest.php).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->owner = Authorization::owner();
    $this->companies = Company::factory()->count(3)->create();
});

function logStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function logEvents(object $test, int $count): void
{
    $start = CarbonImmutable::parse('2026-10-01 00:00:00+00');

    foreach (range(1, $count) as $minute) {
        $company = $minute % 4 === 0 ? null : $test->companies[$minute % 3]->id;
        AuditEventFactory::new()->createOne(['occurred_at' => $start->addMinutes($minute)->toIso8601String(), 'company_id' => $company, 'action' => 'PRJ.ProjectCreated', 'authority_class' => 'ADM']);
        SecurityEventFactory::new()->createOne(['occurred_at' => $start->addMinutes($minute)->toIso8601String(), 'event_kind' => 'LOGOUT']);
    }
}

it('reads both pages in the same number of statements for a few events and a full page (QB-12; PFM-01; PFM-02)', function () {
    $filters = ['dari' => null, 'sampai' => null, 'perusahaan' => null, 'pelaku' => null, 'catatan' => null, 'setelah' => null];
    $history = fn (array $query = []): int => logStatements(fn () => app(ActivityHistory::class)->screen($this->owner->id, [...$filters, ...$query]));
    $security = fn (): int => logStatements(fn () => app(SecurityLog::class)->screen($this->owner->id, ['jenis' => null, 'dari' => null, 'sampai' => null, 'perusahaan' => null, 'setelah' => null, 'peristiwa' => null]));
    logEvents($this, 3);
    $history();
    $few = [$history(), $security(), $history(['pelaku' => $this->owner->public_id]), $history(['dari' => '2026-10-01'])];
    logEvents($this, ActivityHistory::PAGE + 10);

    expect($few[0])->toBeLessThanOrEqual(4)->and($few[1])->toBeLessThanOrEqual(3)
        ->and([$history(), $security(), $history(['pelaku' => $this->owner->public_id]), $history(['dari' => '2026-10-01'])])->toBe($few);
});
