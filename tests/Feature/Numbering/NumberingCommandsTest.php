<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\NumberingScheme;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\ImportBatchFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 (TASK-021; ROUTE-061, ROUTE-136, ROUTE-137; NM-03; NX-01; DIR-083 OE-11; DIR-098): PG-061 and the numbering
// command over HTTP — a scheme added, changed before it takes effect and refused once in force, in the past or with a
// first period that is not live and has no counter; the start only after go-live and once, refused while a batch
// holds seed rows; the Owner's seed of a period that is not live; the raise after a restore, once per recovery
// point and never lowering, moving no seed; each answer in place as flash data, a validation error at its field;
// an Admin answered by the not-found page (OD-03).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create();
    $this->today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
    $this->year = substr($this->today, 0, 4);
    Numbering::goLive(null);
});

afterEach(function () {
    @unlink($this->hotFile);
});

function owner(object $test): object
{
    return $test->actingAs($test->owner);
}

function scheme(object $test, array $input = []): array
{
    return ['command_id' => (string) Str::uuid(), 'company' => $test->company->public_id, 'scheme_id' => null, 'lock_version' => null,
        'period_granularity' => 'YEAR', 'format_template' => 'ARJ/INV/{tahun}/{nomor:4}', 'revision_format' => 'NEW_NUMBER',
        'effective_from' => $test->today, 'first_number' => null, 'business_date' => $test->today, 'business_date_prefilled' => true, ...$input];
}

function numbering(object $test, string $mode, array $input = []): array
{
    return ['command_id' => (string) Str::uuid(), 'company' => $test->company->public_id, 'mode' => $mode, 'business_date' => $test->today, 'business_date_prefilled' => true, ...$input];
}

function answerOf(object $response): array
{
    $answer = session('inertia.flash_data')['answer'] ?? session('inertia.flash')['answer'] ?? null;

    return is_array($answer) ? $answer : ['kind' => null, 'text' => null];
}

it('shows PG-061 to the Owner with the type\'s schemes, counters, boundary and go-live, and answers an Admin as not found (OD-03; criterion 5)', function () {
    Numbering::scheme($this->company, SequenceType::Doc06);
    Numbering::seed($this->company, SequenceType::Doc06, $this->year, 141);

    owner($this)->get('/pengaturan/penomoran?jenis=doc-06&perusahaan='.$this->company->public_id)->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings/Numbering', false)
            ->where('type', 'doc-06')->where('company', $this->company->public_id)
            ->where('detail.schemes.0.template', 'CTH/{tahun}/{nomor:4}')
            ->where('detail.counters.0.next_value', 141)
            ->where('detail.boundary', null)->where('detail.go_live', null)->where('detail.start_periods', []));

    $admin = Authorization::admin([$this->company->id]);
    $this->actingAs($admin)->get('/pengaturan/penomoran')->assertNotFound();
    $this->actingAs($admin)->put('/pengaturan/penomoran/doc-06', scheme($this))->assertNotFound();
    $this->actingAs($admin)->post('/pengaturan/penomoran/doc-06/mulai', numbering($this, 'start'))->assertNotFound();
    expect(NumberingScheme::query()->count())->toBe(1);
});

it('adds a scheme that creates no counter, audited OWNER_ONLY, and refuses an invalid template at its field (ROUTE-136; NM-03; DIR-098 D1)', function () {
    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['format_template' => 'INV/{bulan}/{nomor}']))
        ->assertSessionHasErrors('format_template');
    expect(NumberingScheme::query()->count())->toBe(0);

    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this))->assertRedirect('/pengaturan/penomoran?perusahaan='.$this->company->public_id.'&jenis=doc-06');

    expect(answerOf($this)['kind'])->toBe('COMMITTED')
        ->and(NumberingScheme::query()->where('sequence_type', 'DOC-06')->count())->toBe(1)
        ->and(DB::table('number_sequences')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'ORG.NumberingSchemeConfigured')->where('authority_class', 'OWNER_ONLY')->count())->toBe(1);
});

it('changes a scheme before it takes effect, refuses a stale version and a scheme already in force (CF; ST-01)', function () {
    $later = Numbering::scheme($this->company, SequenceType::Doc06, from: CarbonImmutable::parse($this->today)->addMonth()->toDateString())->fresh() ?? throw new RuntimeException;
    $inForce = Numbering::scheme($this->company, SequenceType::Doc01, from: $this->today);

    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['scheme_id' => $later->id, 'lock_version' => $later->lock_version, 'effective_from' => $later->effective_from->toDateString(), 'format_template' => 'B/{nomor}']));
    expect(answerOf($this)['kind'])->toBe('COMMITTED')->and($later->fresh()?->format_template)->toBe('B/{nomor}');

    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['scheme_id' => $later->id, 'lock_version' => 0, 'effective_from' => $later->effective_from->toDateString()]));
    expect(answerOf($this)['kind'])->toBe('CONFLICT');

    owner($this)->put('/pengaturan/penomoran/doc-01', scheme($this, ['scheme_id' => $inForce->id, 'lock_version' => 0, 'effective_from' => $this->today]));
    expect(answerOf($this))->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.in_force')]);
});

it('starts numbering only once go-live is recorded, seeding today\'s period, its day the seed boundary (NM-03; UXS-32; criterion 3)', function () {
    Numbering::scheme($this->company, SequenceType::Project);

    owner($this)->post('/pengaturan/penomoran/project/mulai', numbering($this, 'start', ['first_numbers' => [$this->year => 148]]));
    expect(answerOf($this))->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.go_live')])
        ->and(DB::table('number_sequences')->count())->toBe(0);

    Numbering::goLive(CarbonImmutable::parse($this->today)->subMonth()->toDateString());
    owner($this)->get('/pengaturan/penomoran?jenis=project&perusahaan='.$this->company->public_id)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('detail.start_periods', [$this->year]));

    owner($this)->post('/pengaturan/penomoran/project/mulai', numbering($this, 'start', ['first_numbers' => [$this->year => 148]]));
    expect(answerOf($this)['kind'])->toBe('COMMITTED')
        ->and(Numbering::next($this->company, SequenceType::Project, $this->year))->toBe(148)
        ->and(DB::table('number_sequences')->value('seed_value'))->toBe(148)
        ->and(DB::table('audit_events')->where('action', 'ORG.NumberingStarted.PROJECT')->where('company_id', $this->company->id)->count())->toBe(1);

    owner($this)->post('/pengaturan/penomoran/project/mulai', numbering($this, 'start', ['first_numbers' => [$this->year => 1]]));
    expect(answerOf($this))->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.started', ['jenis' => __('glossary.gl_004')])]);
});

it('refuses a start while a batch that is not aborted holds valid seed rows of the type (NM-03; UXS-31; criterion 6)', function () {
    Numbering::scheme($this->company, SequenceType::Doc06);
    Numbering::goLive(CarbonImmutable::parse($this->today)->subMonth()->toDateString());
    $batch = ImportBatchFactory::new()->create(['cutoff_date' => CarbonImmutable::parse($this->today)->subDay()->toDateString(), 'company_scope' => [$this->company->id], 'state' => 'DRY_RUN_PASSED'])->id;
    DB::table('import_rows')->insert(['import_batch_id' => $batch, 'row_number' => 1, 'import_kind' => SequenceType::Doc06->seedImportKind(), 'import_identity' => "{$this->company->id}|{$this->year}",
        'company_id' => $this->company->id, 'validation_state' => 'VALID']);

    owner($this)->post('/pengaturan/penomoran/doc-06/mulai', numbering($this, 'start', ['first_numbers' => [$this->year => 1]]));

    expect(answerOf($this)['kind'])->toBe('REJECTED')->and(answerOf($this)['text'])->toContain('Batch impor')
        ->and(DB::table('number_sequences')->count())->toBe(0);
});

it('lets the Owner seed a period that is not live and has no counter, and refuses the others (NM-03; UXS-29)', function () {
    $start = CarbonImmutable::parse($this->today)->subMonths(2);
    Numbering::scheme($this->company, SequenceType::Doc06, from: '2020-01-01', granularity: 'MONTH', template: 'INV/{bulan_romawi}/{tahun}/{nomor:3}');
    Numbering::goLive($start->subMonths(6)->toDateString());
    Numbering::started($this->company, SequenceType::Doc06, $start);
    $boundaryMonth = $start->format('Y-m');
    $beforeGoLive = $start->subMonths(8)->format('Y-m');
    $live = CarbonImmutable::parse($this->today)->format('Y-m');

    owner($this)->post('/pengaturan/penomoran/doc-06/mulai', numbering($this, 'seed', ['period_key' => $boundaryMonth, 'first_number' => 57]));
    expect(answerOf($this)['kind'])->toBe('COMMITTED')->and(Numbering::next($this->company, SequenceType::Doc06, $boundaryMonth))->toBe(57);

    foreach ([[$boundaryMonth, 'has_counter'], [$beforeGoLive, 'before_go_live'], [$live, 'live'], [$this->year, 'not_governed']] as [$period, $reason]) {
        owner($this)->post('/pengaturan/penomoran/doc-06/mulai', numbering($this, 'seed', ['period_key' => $period, 'first_number' => 9]));
        expect(answerOf($this)['kind'])->toBe('REJECTED', $reason)
            ->and(answerOf($this)['text'])->toBe(__("numbering.refusal.{$reason}", ['jenis' => __('documents.doc_06'), 'periode' => $period]), $reason);
    }
});

it('refuses, once the type has its boundary, a scheme in the past and one whose first period needs a seed, unless it seeds it or extends backwards (NM-03; UXS-30)', function () {
    Numbering::scheme($this->company, SequenceType::Doc06, from: '2025-01-01');
    Numbering::goLive('2025-06-01');
    // Started today: this month began on or before the boundary, so it is not live and has no counter.
    Numbering::started($this->company, SequenceType::Doc06, CarbonImmutable::now());
    $past = CarbonImmutable::parse($this->today)->subDays(3)->toDateString();
    $month = substr($this->today, 0, 7);

    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['effective_from' => $past]));
    expect(answerOf($this))->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.past', ['tanggal' => $this->today])]);

    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['period_granularity' => 'MONTH', 'format_template' => 'B/{bulan}/{nomor}']));
    expect(answerOf($this))->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.first_period', ['periode' => $month])]);

    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['period_granularity' => 'MONTH', 'format_template' => 'B/{bulan}/{nomor}', 'first_number' => 12]));
    expect(answerOf($this)['kind'])->toBe('COMMITTED')->and(Numbering::next($this->company, SequenceType::Doc06, $month))->toBe(12)
        ->and(DB::table('number_sequences')->value('seed_value'))->toBe(12);

    // The backward extension over dates no scheme covered is admitted without a seed.
    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['effective_from' => '2024-01-01', 'format_template' => 'OLD/{nomor}']));
    expect(answerOf($this)['kind'])->toBe('COMMITTED');
});

it('raises each affected counter after a restore to the number after the highest on paper, once per recovery point, never lowering, moving no seed (DIR-083 OE-11; criteria 3 and 4)', function () {
    Numbering::scheme($this->company, SequenceType::Doc06);
    Numbering::goLive('2025-01-01');
    Numbering::started($this->company, SequenceType::Doc06, CarbonImmutable::parse("{$this->year}-01-01 01:00", 'UTC')->subYear());
    Numbering::seed($this->company, SequenceType::Doc06, $this->year, 10);
    DB::table('number_sequences')->update(['next_value' => 25]);
    $raise = fn (array $lines, string $point = '2026-10-20T03:00:00Z') => owner($this)->post('/pengaturan/penomoran/doc-06/mulai', numbering($this, 'raise', [
        'restore_reference' => 'Pemulihan cadangan 20 Okt', 'recovery_point' => $point, 'lines' => $lines]));

    $raise([['period_key' => $this->year, 'highest' => 20]]);
    expect(answerOf($this))->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.raise_not_higher', ['periode' => $this->year, 'nomor' => '25'])]);

    $raise([['period_key' => $this->year, 'highest' => 31]]);
    expect(answerOf($this)['kind'])->toBe('COMMITTED')
        ->and(Numbering::next($this->company, SequenceType::Doc06, $this->year))->toBe(32)
        ->and(DB::table('number_sequences')->value('seed_value'))->toBe(10);

    $event = DB::table('audit_events')->where('action', 'ORG.NumberingRaised')->sole();
    expect($event->reason)->toBe('Pemulihan cadangan 20 Okt')
        ->and(json_decode((string) $event->metadata, true))->toMatchArray(['recovery_point' => '2026-10-20T03:00:00.000000Z', 'from' => 25, 'to' => 32]);

    $raise([['period_key' => $this->year, 'highest' => 40]]);
    expect(answerOf($this))->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.raise_repeated', ['periode' => $this->year])])
        ->and(Numbering::next($this->company, SequenceType::Doc06, $this->year))->toBe(32);

    expect(Numbering::draw($this->owner->id, $this->company, SequenceType::Doc06, $this->today))->toBe("COMMITTED 32 CTH/{$this->year}/0032");
});

it('answers a stale prefilled date with MSG-16 and a replayed command_id with its recorded outcome (ST-08; CI-05)', function () {
    Numbering::scheme($this->company, SequenceType::Doc06);
    $yesterday = CarbonImmutable::parse($this->today)->subDay()->toDateString();

    owner($this)->put('/pengaturan/penomoran/doc-06', scheme($this, ['business_date' => $yesterday, 'effective_from' => CarbonImmutable::parse($this->today)->addYear()->toDateString()]));
    expect(answerOf($this)['kind'])->toBe('CONFLICT')->and(answerOf($this)['text'])->toContain($yesterday);

    $payload = scheme($this, ['effective_from' => CarbonImmutable::parse($this->today)->addYear()->toDateString()]);
    owner($this)->put('/pengaturan/penomoran/doc-06', $payload);
    owner($this)->put('/pengaturan/penomoran/doc-06', $payload);
    expect(answerOf($this)['kind'])->toBe('COMMITTED')->and(NumberingScheme::query()->count())->toBe(2);
});
