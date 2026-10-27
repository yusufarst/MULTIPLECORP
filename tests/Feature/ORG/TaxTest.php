<?php

use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Queries\TaxConfiguration;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 (TASK-025; ROUTE-062, ROUTE-138; DATABASE §12; criterion 8; GAP-026): the tax configuration as data — a tax
// type, its effective-dated rate versions, a company's profile and treatment rules, global or a company's —; an
// overlapping range refused at its field (C-52); a stale edit CONFLICT; a company rule resolved over the global one for
// its date; TEST_STRATEGY §5's configured-treatment fixture set through PG-062 itself.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create();
});

afterEach(fn () => @unlink($this->hotFile));

/** ROUTE-138's request for one kind. */
function configureTax(object $test, array $body)
{
    return $test->actingAs($test->owner)->put('/pengaturan/pajak', ['command_id' => (string) Str::uuid(), ...$body]);
}

function taxAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('configures a tax type, its rate versions without overlap and changes one at its version (criterion 8; C-52; ST-01)', function () {
    configureTax($this, ['kind' => 'tax_type', 'code' => 'PPN', 'name' => 'PPN', 'category' => 'OUTPUT_TAX', 'active' => true])->assertRedirect('/pengaturan/pajak?bagian=tax_type');
    expect(taxAnswer()['kind'])->toBe(Outcome::COMMITTED);
    $rate = ['kind' => 'rate_version', 'type' => 'PPN', 'rate_percent' => '12', 'base_numerator' => 11, 'base_denominator' => 12, 'rounding_mode' => 'HALF_UP'];

    configureTax($this, [...$rate, 'effective_from' => '2025-01-01', 'effective_to' => '2026-01-01']);
    configureTax($this, [...$rate, 'rate_percent' => '11', 'effective_from' => '2025-06-01'])->assertSessionHasErrors('effective_from');
    configureTax($this, [...$rate, 'effective_from' => '2026-01-01']);
    expect(DB::table('tax_rate_versions')->count())->toBe(2);

    $first = DB::table('tax_rate_versions')->orderBy('id')->first();
    configureTax($this, [...$rate, 'rate_percent' => '11.5', 'effective_from' => '2025-01-01', 'effective_to' => '2026-01-01', 'row' => RowHandle::of('tax_rate_versions', (int) $first->id), 'lock_version' => 0]);
    expect(taxAnswer()['kind'])->toBe(Outcome::COMMITTED)->and(DB::table('tax_rate_versions')->where('id', $first->id)->value('rate_percent'))->toBe('11.500000');
    configureTax($this, [...$rate, 'effective_from' => '2025-01-01', 'effective_to' => '2026-01-01', 'row' => RowHandle::of('tax_rate_versions', (int) $first->id), 'lock_version' => 0]);
    expect(taxAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'cause' => 'stale']);
});

it('refuses two overlapping rules of one context and tax type, globally or for a company, and resolves the company\'s over the global one (criterion 8; GAP-026)', function () {
    configureTax($this, ['kind' => 'tax_type', 'code' => 'PPH23', 'name' => 'PPh 23', 'category' => 'WITHHOLDING', 'active' => true]);
    $rule = ['kind' => 'rule', 'context' => 'PAYMENT_WITHHELD', 'type' => 'PPH23', 'evidence_required' => true, 'effective_from' => '2025-01-01'];

    configureTax($this, [...$rule, 'treatment' => 'SETTLEMENT_NO_EXPENSE']);
    configureTax($this, [...$rule, 'treatment' => 'SETTLEMENT_TO_EXPENSE', 'effective_from' => '2025-03-01'])->assertSessionHasErrors('effective_from');
    configureTax($this, [...$rule, 'treatment' => 'SETTLEMENT_TO_EXPENSE', 'company' => $this->company->public_id, 'effective_from' => '2025-03-01']);
    configureTax($this, [...$rule, 'treatment' => 'SETTLEMENT_NO_EXPENSE', 'company' => $this->company->public_id, 'effective_from' => '2025-04-01'])->assertSessionHasErrors('effective_from');

    $type = (int) DB::table('tax_types')->where('code', 'PPH23')->value('id');
    $other = Company::factory()->create();
    $tax = app(TaxConfiguration::class);

    expect($tax->treatment($this->company->id, 'PAYMENT_WITHHELD', $type, '2025-05-01')['treatment'])->toBe('SETTLEMENT_TO_EXPENSE')
        ->and($tax->treatment($this->company->id, 'PAYMENT_WITHHELD', $type, '2025-02-01')['treatment'])->toBe('SETTLEMENT_NO_EXPENSE')
        ->and($tax->treatment($other->id, 'PAYMENT_WITHHELD', $type, '2025-05-01')['treatment'])->toBe('SETTLEMENT_NO_EXPENSE')
        ->and($tax->treatment($this->company->id, 'PAYMENT_COLLECTED', $type, '2025-05-01'))->toBeNull();
});

it('lists the configuration with handles, never an internal identifier, and a company\'s profile (ROUTE-062)', function () {
    configureTax($this, ['kind' => 'profile', 'company' => $this->company->public_id, 'registration_status' => 'PKP', 'effective_from' => '2025-01-01']);

    $this->actingAs($this->owner)->get('/pengaturan/pajak?bagian=profile')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ORG/Tax', false)->where('section', 'profile')->where('profiles.0.company', $this->company->code)->where('profiles.0.registration_status', 'PKP')
        ->where('profiles.0.effective_to', null)->missing('profiles.0.id'));
});
