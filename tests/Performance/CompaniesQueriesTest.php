<?php

use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-15 (TASK-025; PERFORMANCE_TARGETS PFM-01, PFM-02, PFM-20): PG-059's directory and a company's detail, and PG-062's
// configuration, in a number of statements that never depends on the rows they show; an NX-01 command within its
// budget, never one statement per row.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create()->refresh();
});

afterEach(fn () => @unlink($this->hotFile));

function companyStatements(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('renders PG-059\'s directory and a company in the same number of statements for few rows and many (PFM-01; PFM-02)', function () {
    $page = fn (string $path) => companyStatements(fn () => $this->actingAs($this->owner)->get($path)->assertOk());
    $detail = "/pengaturan/perusahaan?perusahaan={$this->company->public_id}";
    $page('/pengaturan/perusahaan');
    [$directory, $one] = [$page('/pengaturan/perusahaan'), $page($detail)];

    Company::factory()->count(20)->create();
    foreach (range(1, 20) as $number) {
        DB::table('company_bank_accounts')->insert(['company_id' => $this->company->id, 'bank_name' => "Bank {$number}", 'account_number' => (string) $number, 'account_holder' => 'CV Uji']);
    }

    expect($page('/pengaturan/perusahaan'))->toBe($directory)->and($page($detail))->toBe($one)->and($one)->toBeLessThanOrEqual(60);
});

it('renders PG-062 in the same number of statements whatever its rows (PFM-01)', function () {
    $page = fn () => companyStatements(fn () => $this->actingAs($this->owner)->get('/pengaturan/pajak')->assertOk());
    $page();
    $few = $page();

    foreach (range(1, 20) as $number) {
        $type = DB::table('tax_types')->insertGetId(['code' => "T{$number}", 'name' => "Pajak {$number}", 'category' => 'OTHER']);
        DB::table('tax_rate_versions')->insert(['tax_type_id' => $type, 'rate_percent' => 1, 'rounding_mode' => 'HALF_UP', 'effective' => '[2025-01-01,)']);
        DB::table('tax_treatment_rules')->insert(['transaction_context' => 'SALES_INVOICE', 'tax_type_id' => $type, 'treatment' => 'OUTPUT_EXCLUDED_FROM_REVENUE', 'effective' => '[2025-01-01,)']);
    }

    expect($page())->toBe($few)->and($few)->toBeLessThanOrEqual(30);
});

it('runs a legal-data change within an NX-01 command\'s budget (PFM-20)', function () {
    $count = companyStatements(fn () => $this->actingAs($this->owner)->put("/pengaturan/perusahaan/{$this->company->public_id}", [
        'command_id' => (string) Str::uuid(), 'lock_version' => 0, 'legal_name' => 'CV Ganti', 'legal_form' => $this->company->legal_form, 'npwp' => $this->company->npwp,
        'address_lines' => $this->company->address_lines, 'city' => $this->company->city, 'director_name' => $this->company->director_name,
    ])->assertRedirect());

    expect($count)->toBeLessThanOrEqual(40);
});
