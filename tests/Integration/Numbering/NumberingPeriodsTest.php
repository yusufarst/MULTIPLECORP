<?php

use App\Modules\ORG\Actions\StartNumbering;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\RetiredNumber;
use App\Modules\ORG\Services\NumberingState;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\ImportBatchFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Clock;
use Tests\Support\GuardReconciliation;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-05 (TASK-021; NM-03–NM-05; HO-19; M-18; TEST_STRATEGY §15; DIR-083 OE-11; GAP-016; criteria 2, 3, 4 and 7) on real
// PostgreSQL with the controlled clock (FX-08): a backdated issue into a seeded period draws its next number and one
// into an unseeded pre-cutover period is REJECTED with nothing consumed; an issue before the start, or backdated to
// before it, is refused until its period is seeded; a lost counter answers SQ-18 and is never re-created; a sequence
// seeded by the opening import starts after the last legacy number; the period follows the WIB day across midnight;
// and the Owner's raise after a restored fixture lifts each counter — or creates a live period's — to the number
// after the paper's highest, once per recovery point, moving no seed and raising no defect alert, the next issue
// drawing it and no number drawn twice.

uses(RefreshDatabase::class);

// The counters' verification of DATABASE §3.1 holds after every numbering test (DIR-098 reading 6).
afterEach(function () {
    expect(GuardReconciliation::verificationDifferences())->toBe([]);
});

/** A fixed instant outside FX-08's named ones, for a test that needs only a known today. */
function at(string $instant): void
{
    Carbon\Carbon::setTestNow(CarbonImmutable::parse($instant));
    CarbonImmutable::setTestNow(CarbonImmutable::parse($instant));
}

beforeEach(function () {
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create();
    Numbering::goLive('2026-01-01');
});

it('draws a backdated issue from its seeded period and refuses one into an unseeded pre-cutover period, consuming nothing (M-18; NM-03, NM-05)', function () {
    at('2026-10-26T03:00:00Z');
    Numbering::scheme($this->company, SequenceType::Doc06, from: '2024-01-01', granularity: 'MONTH', template: 'INV/{bulan}/{tahun}/{nomor:3}');
    // The opening import's batch, cutoff 2025-12-31, seeded December alone; go-live 2026-01-01.
    $batch = ImportBatchFactory::new()->create(['cutoff_date' => '2025-12-31', 'company_scope' => [$this->company->id], 'state' => 'COMMITTED']);
    DB::table('import_rows')->insert(['import_batch_id' => $batch->id, 'row_number' => 1, 'import_kind' => SequenceType::Doc06->seedImportKind(),
        'import_identity' => "{$this->company->id}|2025-12", 'company_id' => $this->company->id, 'validation_state' => 'COMMITTED']);
    Numbering::seed($this->company, SequenceType::Doc06, '2025-12', 88);

    expect(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc06, '2025-12-15'))->toBe('COMMITTED 88 INV/12/2025/088')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc06, '2025-11-15'))->toBe('REJECTED C-10')
        ->and(Numbering::next($this->company, SequenceType::Doc06, '2025-11'))->toBeNull()
        // A live period after the boundary starts at 1 on its first issue.
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc06, '2026-10-26'))->toBe('COMMITTED 1 INV/10/2026/001');
});

it('refuses every issue before the type\'s start, and one backdated before the start until the Owner seeds its period (M-18; HO-19)', function () {
    at('2026-10-26T03:00:00Z');
    Numbering::scheme($this->company, SequenceType::Doc01, from: '2026-01-01', granularity: 'MONTH', template: 'PEN/{tahun}{bulan}/{nomor}');

    expect(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc01, '2026-10-26'))->toBe('REJECTED C-10');

    $outcome = app(StartNumbering::class)->start((string) Str::uuid(), $this->owner->id, $this->company, SequenceType::Doc01, ['2026-10' => 31], '2026-10-26', true);
    expect($outcome->kind)->toBe('COMMITTED')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc01, '2026-10-26'))->toBe('COMMITTED 31 PEN/202610/31')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc01, '2026-09-30'))->toBe('REJECTED C-10');

    $seed = app(StartNumbering::class)->seed((string) Str::uuid(), $this->owner->id, $this->company, SequenceType::Doc01, '2026-09', 12, '2026-10-26', true);
    expect($seed->kind)->toBe('COMMITTED')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc01, '2026-09-30'))->toBe('COMMITTED 12 PEN/202609/12');
});

it('answers a lost counter with SQ-18 and never re-creates it below the numbers issued under it (NM-03; FU-07; GU-02)', function () {
    at('2026-10-26T03:00:00Z');
    Numbering::scheme($this->company, SequenceType::Project, from: '2026-01-01');
    Numbering::started($this->company, SequenceType::Project, CarbonImmutable::parse('2026-02-01 03:00', 'UTC'));
    RetiredNumber::factory()->create(['company_id' => $this->company->id, 'sequence_type' => 'PROJECT', 'period_key' => '2027', 'sequence_no' => 5, 'formatted_number' => 'CTH/2027/0005']);
    at('2027-02-01T03:00:00Z');

    expect(Numbering::draw($this->owner->id, $this->company, SequenceType::Project, '2027-02-01'))->toStartWith('FAILED')
        ->and(Numbering::next($this->company, SequenceType::Project, '2027'))->toBeNull();

    // The verification of DATABASE §3.1 holds once the counter is put back above the tombstone.
    Numbering::seed($this->company, SequenceType::Project, '2027', 6);
});

it('starts a sequence seeded by the opening import after the last legacy number, unique and monotonic (DB24-09; architecture challenge 14a; criterion 7)', function () {
    at('2026-10-26T03:00:00Z');
    Numbering::scheme($this->company, SequenceType::Purchase, from: '2025-01-01');
    $batch = ImportBatchFactory::new()->create(['cutoff_date' => '2026-09-30', 'company_scope' => [$this->company->id], 'state' => 'COMMITTED']);
    DB::table('import_rows')->insert(['import_batch_id' => $batch->id, 'row_number' => 1, 'import_kind' => SequenceType::Purchase->seedImportKind(),
        'import_identity' => "{$this->company->id}|2026", 'company_id' => $this->company->id, 'validation_state' => 'COMMITTED']);
    // The last legacy purchase number of 2026 is 215: the seed states 216.
    Numbering::seed($this->company, SequenceType::Purchase, '2026', 216);

    expect(app(NumberingState::class)->boundary($this->company->id, SequenceType::Purchase))->toBe('2026-09-30')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Purchase, '2026-10-26'))->toBe('COMMITTED 216 CTH/2026/0216')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Purchase, '2026-08-01'))->toBe('COMMITTED 217 CTH/2026/0217');
});

it('takes the period of the WIB day across midnight, never the UTC one (GAP-016; BR-DT-06; FX-08)', function () {
    Numbering::scheme($this->company, SequenceType::Doc05, from: '2025-01-01');
    Numbering::started($this->company, SequenceType::Doc05, CarbonImmutable::parse('2025-12-15 03:00', 'UTC'));
    Numbering::goLive('2026-01-01');

    Clock::freeze('before-year-end');
    expect(BusinessDate::today())->toBe('2026-12-31')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc05, BusinessDate::today()))->toBe('COMMITTED 1 CTH/2026/0001');

    Clock::freeze('year-boundary');
    expect(BusinessDate::today())->toBe('2027-01-01')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc05, BusinessDate::today()))->toBe('COMMITTED 1 CTH/2027/0001');
});

it('raises after a restore each affected counter, and a live period\'s new one, to the number after the paper\'s highest, once per recovery point (TEST_STRATEGY §15; DIR-083 OE-11; criteria 3 and 4)', function () {
    Log::spy();
    at('2026-10-26T03:00:00Z');
    Numbering::scheme($this->company, SequenceType::Doc06, from: '2026-01-01', granularity: 'MONTH', template: 'INV/{bulan}/{tahun}/{nomor:3}');
    Numbering::started($this->company, SequenceType::Doc06, CarbonImmutable::parse('2026-02-10 03:00', 'UTC'));
    // The restored fixture: September's counter at the recovery point; October began after it and has none.
    Numbering::seed($this->company, SequenceType::Doc06, '2026-09', 20);
    DB::table('number_sequences')->where('period_key', '2026-09')->update(['next_value' => 34]);
    $raise = fn (array $highest, string $point = '2026-10-20T03:00:00.000000Z') => app(StartNumbering::class)
        ->raise((string) Str::uuid(), $this->owner->id, $this->company, SequenceType::Doc06, 'Pemulihan 20 Okt', $point, $highest, '2026-10-26', true);

    $lower = $raise(['2026-09' => 30, '2026-10' => 9]);
    expect($lower->kind)->toBe('REJECTED')->and($lower->state['reason'])->toBe('raise_not_higher')
        ->and(Numbering::next($this->company, SequenceType::Doc06, '2026-10'))->toBeNull();

    $raised = $raise(['2026-09' => 40, '2026-10' => 9]);
    expect($raised->kind)->toBe('COMMITTED')
        ->and(Numbering::next($this->company, SequenceType::Doc06, '2026-09'))->toBe(41)
        ->and(Numbering::next($this->company, SequenceType::Doc06, '2026-10'))->toBe(10)
        ->and(DB::table('number_sequences')->where('period_key', '2026-09')->value('seed_value'))->toBe(20)
        ->and(DB::table('number_sequences')->where('period_key', '2026-10')->value('seed_value'))->toBeNull()
        ->and(app(NumberingState::class)->boundary($this->company->id, SequenceType::Doc06))->toBe('2026-02-10')
        ->and(DB::table('audit_events')->where('action', 'ORG.NumberingRaised')->whereRaw("metadata->>'recovery_point' = '2026-10-20T03:00:00.000000Z'")->count())->toBe(2);

    expect($raise(['2026-09' => 60])->state['reason'])->toBe('raise_repeated')
        ->and(Numbering::next($this->company, SequenceType::Doc06, '2026-09'))->toBe(41)
        // A later restore's own recovery point raises again.
        ->and($raise(['2026-09' => 60], '2026-10-25T03:00:00.000000Z')->kind)->toBe('COMMITTED');

    expect(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc06, '2026-10-26'))->toBe('COMMITTED 10 INV/10/2026/010')
        ->and(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc06, '2026-09-30'))->toBe('COMMITTED 61 INV/09/2026/061');
    Log::shouldNotHaveReceived('alert');
});
