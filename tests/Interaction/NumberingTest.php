<?php

use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Numbering;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-08 (TASK-021; IG-01, IG-02, IG-05): INT-136 and INT-137 on the server's side — present with `companies.manage`
// and absent without it, the success, the validation answer and each rejection their contracts name, and one
// `command_id` replayed answering its recorded outcome with nothing written twice (CI-05).

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
    Numbering::goLive('2025-01-01');
});

afterEach(fn () => @unlink($this->hotFile));

function flashed(): array
{
    $answer = session('inertia.flash_data')['answer'] ?? null;

    return is_array($answer) ? $answer : [];
}

it('is present for the Owner — PG-061 with its start — and absent without companies.manage (INT-136, INT-137; IG-01)', function () {
    Numbering::scheme($this->company, SequenceType::Project);

    $this->actingAs($this->owner)->get('/pengaturan/penomoran?jenis=project')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings/Numbering', false)->where('detail.start_periods', [$this->year]));

    $admin = Authorization::admin([$this->company->id], ['projects.manage', 'documents.issue']);
    $this->actingAs($admin)->get('/pengaturan/penomoran')->assertNotFound();
});

it('answers INT-136\'s success, its validation at the fields and its stale-version conflict (INT-136; IP-14; ST-01)', function () {
    $send = fn (array $input) => $this->actingAs($this->owner)->put('/pengaturan/penomoran/doc-01', ['command_id' => (string) Str::uuid(), 'company' => $this->company->public_id,
        'scheme_id' => null, 'lock_version' => null, 'period_granularity' => 'YEAR', 'format_template' => 'PEN/{tahun}/{nomor:3}', 'revision_format' => 'BASE_SUFFIX',
        'effective_from' => CarbonImmutable::parse($this->today)->addMonth()->toDateString(), 'business_date' => $this->today, 'business_date_prefilled' => true, ...$input]);

    $send(['format_template' => 'PEN'])->assertSessionHasErrors('format_template');
    $send(['period_granularity' => 'DAY'])->assertSessionHasErrors('period_granularity');
    $send([]);
    expect(flashed()['kind'])->toBe('COMMITTED');

    $scheme = DB::table('numbering_schemes')->first();
    $send(['scheme_id' => $scheme?->id, 'lock_version' => 5, 'effective_from' => CarbonImmutable::parse($this->today)->addMonths(2)->toDateString()]);
    expect(flashed())->toMatchArray(['kind' => 'CONFLICT', 'cause' => 'stale']);
});

it('confirms then answers INT-137\'s start, each rejection it names, and replays one command_id (INT-137; NX-01; CI-05)', function () {
    Numbering::scheme($this->company, SequenceType::Project);
    $payload = ['command_id' => (string) Str::uuid(), 'company' => $this->company->public_id, 'mode' => 'start', 'first_numbers' => [$this->year => 148],
        'business_date' => $this->today, 'business_date_prefilled' => true];

    $this->actingAs($this->owner)->post('/pengaturan/penomoran/project/mulai', $payload);
    expect(flashed()['kind'])->toBe('COMMITTED');

    $this->actingAs($this->owner)->post('/pengaturan/penomoran/project/mulai', $payload);
    expect(flashed()['kind'])->toBe('COMMITTED')
        ->and(DB::table('number_sequences')->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'ORG.NumberingStarted.PROJECT')->count())->toBe(1)
        ->and(DB::table('command_log')->where('command_id', $payload['command_id'])->count())->toBe(1);

    // A seed the period rule no longer admits (NM-03), MSG-17's family, and a raise that would lower (NX-01), MSG-02's.
    $this->actingAs($this->owner)->post('/pengaturan/penomoran/project/mulai', [...$payload, 'command_id' => (string) Str::uuid(), 'mode' => 'seed', 'period_key' => $this->year, 'first_number' => 3]);
    expect(flashed()['kind'])->toBe('REJECTED');
    $this->actingAs($this->owner)->post('/pengaturan/penomoran/project/mulai', [...$payload, 'command_id' => (string) Str::uuid(), 'mode' => 'raise', 'restore_reference' => 'Uji',
        'recovery_point' => '2026-10-01T00:00:00Z', 'lines' => [['period_key' => $this->year, 'highest' => 10]]]);
    expect(flashed())->toMatchArray(['kind' => 'REJECTED', 'text' => __('numbering.refusal.raise_not_higher', ['periode' => $this->year, 'nomor' => '148'])]);

    $this->actingAs($this->owner)->post('/pengaturan/penomoran/project/mulai', [...$payload, 'command_id' => (string) Str::uuid(), 'mode' => 'raise', 'restore_reference' => '',
        'recovery_point' => '2999-01-01T00:00:00Z', 'lines' => []])->assertSessionHasErrors(['restore_reference', 'recovery_point', 'lines']);
});
