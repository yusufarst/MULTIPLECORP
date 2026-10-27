<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Services\Uploads;
use App\Modules\ORG\Data\RowHandle;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-08 (TASK-025; IG-01–IG-05; INT-131–INT-135, INT-138; CI-05): each control's server side — its success, and one
// `command_id` replayed answering the recorded outcome with nothing written twice; the same identifier with other
// content a CONFLICT —; the step-up expiring before the bank account, the identity asset and an identity-asset change of
// the legal data (AU-15; IP-10), nothing recorded.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create()->refresh();
    DB::table('company_bank_accounts')->insert(['company_id' => $this->company->id, 'bank_name' => 'Bank Lama', 'account_number' => '111', 'account_holder' => 'CV Uji']);
    DB::table('tax_types')->insert(['code' => 'PPN', 'name' => 'PPN', 'category' => 'OUTPUT_TAX']);
});

afterEach(fn () => @unlink($this->hotFile));

function companiesAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** Each INT row's request: its method, path and payload. */
function companiesInteraction(object $test, string $row): array
{
    $id = $test->company->public_id;
    $account = RowHandle::of('company_bank_accounts', (int) DB::table('company_bank_accounts')->value('id'));

    return match ($row) {
        'INT-131' => ['POST', '/pengaturan/perusahaan', ['code' => 'NEW1', 'legal_name' => 'CV Baru']],
        'INT-132' => ['PUT', "/pengaturan/perusahaan/{$id}", ['lock_version' => 0, 'legal_name' => 'CV Ganti', 'legal_form' => $test->company->legal_form, 'npwp' => $test->company->npwp,
            'address_lines' => $test->company->address_lines, 'city' => $test->company->city, 'director_name' => $test->company->director_name]],
        'INT-133' => ['POST', "/pengaturan/perusahaan/{$id}/nonaktifkan", ['lock_version' => 0, 'reason' => 'Tutup']],
        'INT-134' => ['POST', "/pengaturan/perusahaan/{$id}/rekening", ['action' => 'change', 'account' => $account, 'lock_version' => 0, 'reason' => 'Pindah',
            'bank_name' => 'Bank Baru', 'account_number' => '2222', 'account_holder' => 'CV Uji']],
        'INT-135' => ['POST', "/pengaturan/perusahaan/{$id}/aset-identitas", ['asset' => 'logo', 'lock_version' => 0,
            'file' => app(Uploads::class)->accept(Files::path(Files::png()), 'logo.png', FilePurpose::IdentityAsset, $test->company->id, $test->owner->id)->public_id]],
        default => ['PUT', '/pengaturan/pajak', ['kind' => 'rate_version', 'type' => 'PPN', 'rate_percent' => '12', 'base_numerator' => 1, 'base_denominator' => 1,
            'rounding_mode' => 'HALF_UP', 'effective_from' => '2025-01-01']],
    };
}

it('commits each command once, and its command_id replayed answers the recorded outcome writing nothing (CI-05; IG-05)', function (string $row) {
    [$method, $path, $payload] = companiesInteraction($this, $row);
    $payload = [...$payload, 'command_id' => (string) Str::uuid()];
    $owner = fn () => $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()]);
    $counts = fn (): array => [DB::table('companies')->count(), DB::table('company_bank_accounts')->count(), DB::table('tax_rate_versions')->count(), DB::table('audit_events')->count()];

    $owner()->call($method, $path, $payload)->assertRedirect();
    expect(companiesAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED);
    $before = $counts();
    $owner()->get('/pengaturan/perusahaan')->assertOk();

    $owner()->call($method, $path, $payload)->assertRedirect();
    expect(companiesAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)->and($counts())->toBe($before);

    $owner()->call($method, $path, [...$payload, 'legal_name' => 'Lain', 'reason' => 'Lain', 'rate_percent' => '11', 'account_number' => '3333', 'asset' => 'signature'])->assertRedirect();
    expect(companiesAnswer()['kind'] ?? null)->toBe(Outcome::CONFLICT);
})->with(['INT-131', 'INT-132', 'INT-133', 'INT-134', 'INT-135', 'INT-138']);

it('asks the step-up once 15 minutes have passed, then nothing is recorded (AU-15; IP-10)', function (string $row) {
    [$method, $path, $payload] = companiesInteraction($this, $row);
    $payload = $row === 'INT-132' ? [...$payload, 'director_name' => 'Direktur Lain'] : $payload;

    $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time() - 901])
        ->json($method, $path, ['command_id' => (string) Str::uuid(), ...$payload])->assertStatus(423);
    expect(DB::table('command_log')->count())->toBe(0);
})->with(['INT-132', 'INT-134', 'INT-135']);
