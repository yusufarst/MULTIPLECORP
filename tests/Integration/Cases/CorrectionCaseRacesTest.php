<?php

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\CaseRaces;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\GuardReconciliation;
use Tests\Support\Numbering;

// TL-05 (TASK-031; NX-06; C-46; C-61; criteria 3, 7, 9) on real PostgreSQL sessions: a closure racing a posting of the
// same case serializes at the case's project and the case — the closure sees the posting's residual and is refused, or
// the posting reopens the case it closed —; and the case counters reconcile with the facts that post them, a drift
// reported by `ReconcileGuards`, never repaired.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    // The projects' own counter stands above their numbers (DIR-098 reading 6).
    Numbering::seed($this->company, SequenceType::Project, '2026', 100_000);
    $this->project = ProjectFactory::new()->createOne(['company_id' => $this->company->id]);
    $this->closer = Authorization::admin([$this->company->id], ['projects.view', 'cases.close'])->id;
    $this->poster = Authorization::admin([$this->company->id], ['projects.view'])->id;
    // A purchase return whose figures balance, so it may close; a refund posted to it unbalances it again.
    $this->case = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'case_kind' => 'PURCHASE_RETURN'])->id;
});

it('refuses a closure that waited on a posting\'s residual of the same case (criterion 7; NX-06; C-46)', function () {
    [$closer, $poster, $case] = [$this->closer, $this->poster, $this->case];

    $results = Sessions::run([
        'posting' => static fn (): string => CaseRaces::holdThenPost($poster, $case, ['refunded_value' => '250000.00']),
        'closure' => static function () use ($closer, $case): string {
            Barrier::await('the posting holds the case');

            return CaseRaces::close($closer, $case);
        },
    ]);

    expect($results['posting'])->toBe('COMMITTED')
        ->and($results['closure'])->toBe('REJECTED C-46')
        ->and(CorrectionCase::query()->findOrFail($case)->state)->toBe('OPEN');
});

it('reopens the case a closure closed when the posting comes after it (criterion 3; criterion 7)', function () {
    expect(CaseRaces::close($this->closer, $this->case))->toBe('COMMITTED')
        ->and(CaseRaces::post($this->poster, $this->case, ['refunded_value' => '250000.00']))->toBe('COMMITTED');

    $case = CorrectionCase::query()->findOrFail($this->case);
    expect($case->state)->toBe('OPEN')
        ->and($case->reopened_by)->toBe($this->poster)
        ->and(DB::table('audit_events')->where('action', 'OPS.CorrectionCaseReopened')->count())->toBe(1);
});

it('reconciles a case\'s counters with the facts that post them and reports a drift, never repairing it (criterion 9; C-61)', function () {
    $family = Families::named('correction_cases');
    expect($family)->not->toBeNull();

    // No fact posts to the case yet, and its counters are zero: nothing to report.
    expect((new Reconciler)->differences(DB::connection(), $family))->toBe([]);

    DB::table('correction_cases')->where('id', $this->case)->update(['unbacked_refund_amount' => '1000.00']);
    $differences = (new Reconciler)->differences(DB::connection(), $family);

    expect($differences)->toHaveCount(1)
        ->and(json_encode($differences))->toContain('unbacked_refund_amount')
        // Reported, never repaired: the counter keeps the drifted value until the audited maintenance rebuild.
        ->and(DB::table('correction_cases')->where('id', $this->case)->value('unbacked_refund_amount'))->toBe('1000.00');

    DB::table('correction_cases')->where('id', $this->case)->update(['unbacked_refund_amount' => 0]);
    expect(GuardReconciliation::familyDifferences())->toBe([]);
});
