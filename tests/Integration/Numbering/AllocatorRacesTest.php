<?php

use App\Modules\ORG\Actions\StartNumbering;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\ImportBatchFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Numbering;
use Tests\Support\TwoFactor;

// TL-05 (TASK-021; NM-01–NM-04; FU-07; LK-06, LK-31; C-10 ⚡; criteria 1, 2 and 6): the allocator under real
// PostgreSQL sessions — concurrent issues of one counter drawing distinct consecutive numbers (M-15), two first issues
// of a new live period sharing one counter (M-16), a rolled-back issue consuming nothing (M-17), and the numbering
// command and a batch's commit request serialized at the company row (LK-06; NX-13; M-18).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner())->id;
    $this->company = Company::factory()->create()->id;
    $company = Company::query()->findOrFail($this->company);
    Numbering::scheme($company, SequenceType::Doc06, from: '2025-01-01');
    Numbering::started($company, SequenceType::Doc06, CarbonImmutable::parse('2025-06-01 03:00', 'UTC'));
    $this->year = substr(CarbonImmutable::now('Asia/Jakarta')->toDateString(), 0, 4);
    $this->today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
});

it('draws N distinct consecutive numbers for N concurrent issues of one counter (M-15; NM-02; criterion 1)', function () {
    Numbering::seed(Company::query()->findOrFail($this->company), SequenceType::Doc06, $this->year, 41);
    [$owner, $company, $today] = [$this->owner, $this->company, $this->today];
    $issue = static function () use ($owner, $company, $today): string {
        Numbering::goLive('2025-06-01');
        Barrier::await('every issue is ready');

        return Numbering::draw($owner, Company::query()->findOrFail($company), SequenceType::Doc06, $today);
    };

    $results = Sessions::run(['a' => $issue, 'b' => $issue, 'c' => $issue]);
    $numbers = array_map(fn (string $result): int => (int) explode(' ', $result)[1], $results);
    sort($numbers);

    expect($numbers)->toBe([41, 42, 43])
        ->and(Numbering::next(Company::query()->findOrFail($this->company), SequenceType::Doc06, $this->year))->toBe(44);
});

it('creates one counter for two first issues of a new live period, numbers 1 and 2 (M-16; FU-07)', function () {
    [$owner, $company, $today] = [$this->owner, $this->company, $this->today];
    $issue = static function () use ($owner, $company, $today): string {
        Numbering::goLive('2025-06-01');
        Barrier::await('both issues are ready');

        return Numbering::draw($owner, Company::query()->findOrFail($company), SequenceType::Doc06, $today);
    };

    $results = Sessions::run(['a' => $issue, 'b' => $issue]);
    $numbers = array_map(fn (string $result): int => (int) explode(' ', $result)[1], $results);
    sort($numbers);

    expect($numbers)->toBe([1, 2])
        ->and(DB::table('number_sequences')->where('period_key', $this->year)->count())->toBe(1)
        ->and(DB::table('number_sequences')->where('period_key', $this->year)->value('seed_value'))->toBeNull();
});

it('consumes nothing when an issue rolls back after its draw; the next issue takes that number (M-17; NM-04; criterion 2)', function () {
    Numbering::goLive('2025-06-01');
    $company = Company::query()->findOrFail($this->company);
    Numbering::seed($company, SequenceType::Doc06, $this->year, 7);

    try {
        $failed = Numbering::draw($this->owner, $company, SequenceType::Doc06, $this->today, function () {
            throw new RuntimeException('the issue fails after its draw');
        });
    } catch (RuntimeException) {
        $failed = 'FAILED';
    }

    expect($failed)->toStartWith('FAILED')
        ->and(Numbering::next($company, SequenceType::Doc06, $this->year))->toBe(7)
        ->and(Numbering::draw($this->owner, $company, SequenceType::Doc06, $this->today))->toBe("COMMITTED 7 CTH/{$this->year}/0007");
});

it('serializes the start with a batch\'s commit request at the company row: the start meets the batch and is refused (LK-06; NX-13; M-18; criterion 6)', function () {
    $company = Company::factory()->create();
    Numbering::scheme($company, SequenceType::Purchase, from: '2025-01-01');
    $batch = ImportBatchFactory::new()->create(['cutoff_date' => CarbonImmutable::parse($this->today)->subDay()->toDateString(), 'company_scope' => [$company->id], 'state' => 'SIGNED_OFF']);
    [$owner, $companyId, $batchId, $today, $year] = [$this->owner, $company->id, $batch->id, $this->today, $this->year];

    $results = Sessions::run([
        // The commit request takes LK-06 and, while it holds it, a seed row of the type becomes VALID in the batch.
        'commit' => static function () use ($companyId, $batchId, $year): string {
            return DB::transaction(function () use ($companyId, $batchId, $year): string {
                DB::table('companies')->where('id', $companyId)->lock('for no key update')->first();
                Barrier::await('the commit holds the company row');
                Sessions::awaitBlocked(1);
                DB::table('import_rows')->insert(['import_batch_id' => $batchId, 'row_number' => 1, 'import_kind' => SequenceType::Purchase->seedImportKind(),
                    'import_identity' => "{$companyId}|{$year}", 'company_id' => $companyId, 'validation_state' => 'VALID']);

                return 'held';
            });
        },
        'start' => static function () use ($owner, $companyId, $today, $year): string {
            Numbering::goLive('2025-06-01');
            Barrier::await('the commit holds the company row');
            $outcome = app(StartNumbering::class)->start((string) Str::uuid(), $owner, Company::query()->findOrFail($companyId), SequenceType::Purchase, [$year => 1], $today, true);

            return trim($outcome->kind.' '.($outcome->code ?? '').' '.($outcome->state['reason'] ?? ''));
        },
    ]);

    expect($results)->toBe(['commit' => 'held', 'start' => 'REJECTED NM-03 batch'])
        ->and(DB::table('number_sequences')->where('company_id', $company->id)->count())->toBe(0);
});
