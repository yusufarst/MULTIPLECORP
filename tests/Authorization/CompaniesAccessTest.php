<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Queries\CompanyIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-025; OD-03; OD-04; AZ-11; PJ-19; criteria 1 and 10): PG-059, PG-062 and every command of theirs the
// Owner's alone — an Admin holding every other capability, the step-up confirmed, answered by the not-found page on
// every path and writing nothing —; the company as other pages print it under PJ-19: its identity to a viewer of its
// family in scope, its bank accounts with `finance.view`, its stamp and signature to the Owner only, nothing outside
// scope.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $everything = DB::table('capabilities')->where('authority_class', '<>', 'OWNER_ONLY')->pluck('code')->all();
    $this->admin = Authorization::admin([$this->company->id], array_values(array_filter($everything, is_string(...))));
});

afterEach(fn () => @unlink($this->hotFile));

it('answers an Admin on every PG-059 and PG-062 path as a missing page, writing nothing (criterion 1; OD-03; OD-04; IP-21)', function (string $method, string $path) {
    $id = $this->company->public_id;
    $before = [DB::table('companies')->count(), DB::table('company_bank_accounts')->count(), DB::table('tax_types')->count(), DB::table('command_log')->count()];

    $this->actingAs($this->admin)->withSession([RequireStepUp::CONFIRMED_AT => time()])
        ->call($method, str_replace('{id}', $id, $path), ['command_id' => (string) Str::uuid(), 'code' => 'XX', 'legal_name' => 'X', 'kind' => 'tax_type'])->assertNotFound();

    expect([DB::table('companies')->count(), DB::table('company_bank_accounts')->count(), DB::table('tax_types')->count(), DB::table('command_log')->count()])->toBe($before);
})->with([
    ['GET', '/pengaturan/perusahaan'], ['POST', '/pengaturan/perusahaan'], ['PUT', '/pengaturan/perusahaan/{id}'], ['POST', '/pengaturan/perusahaan/{id}/nonaktifkan'],
    ['POST', '/pengaturan/perusahaan/{id}/rekening'], ['POST', '/pengaturan/perusahaan/{id}/aset-identitas'], ['GET', '/pengaturan/pajak'], ['PUT', '/pengaturan/pajak'],
]);

it('prints a company under PJ-19 — identity by family in scope, bank accounts with finance.view, stamp and signature to the Owner only (criterion 10)', function () {
    DB::table('company_bank_accounts')->insert(['company_id' => $this->company->id, 'bank_name' => 'Bank Uji', 'account_number' => '123', 'account_holder' => 'CV Uji']);
    $identity = fn ($user) => app(CompanyIdentity::class)->of(app(EffectiveCapabilities::class)->fresh($user->id), $this->company->id);
    $projects = Authorization::admin([$this->company->id], ['projects.view']);
    $finance = Authorization::admin([$this->company->id], ['finance.view']);
    $outside = Authorization::admin([Company::factory()->create()->id], ['projects.view', 'finance.view']);
    $owner = Authorization::owner();

    expect($identity($projects))->toHaveKeys(['npwp', 'director_name', 'logo'])->not->toHaveKeys(['bank_accounts', 'stamp', 'signature'])
        ->and($identity($finance))->toHaveKey('bank_accounts')->not->toHaveKeys(['stamp', 'signature'])
        ->and($identity($finance)['bank_accounts'][0]['account_number'])->toBe('123')
        ->and($identity($owner))->toHaveKeys(['bank_accounts', 'stamp', 'signature'])
        ->and($identity($outside))->toBeNull();
});
