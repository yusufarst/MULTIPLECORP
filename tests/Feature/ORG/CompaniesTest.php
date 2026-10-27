<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\Uploads;
use App\Modules\ORG\Data\RowHandle;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Files;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 (TASK-025; ROUTE-059, ROUTE-131–ROUTE-135; criteria 2–6, 9): a company created with its legal identity, a
// duplicate refused at its field; its legal data changed at the version read, a stale edit CONFLICT; the step-up asked
// only when an identity asset changes; a referenced bank account changed by a new one and the old one's deactivation;
// an identity asset set from the Owner's own upload; the readiness list derived from what exists; issued versions keep
// their identity.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Storage::fake('files');
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create(['npwp' => '01.234.567.8-901.000', 'director_name' => 'Direktur Uji'])->refresh();
});

afterEach(fn () => @unlink($this->hotFile));

function companyAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** An issued document version of the company. */
function issuedVersion(Company $company, array $attributes = []): DocumentVersion
{
    return DocumentVersion::factory()->issued()->for(Document::factory()->state(['company_id' => $company->id]), 'document')->create($attributes);
}

/** The company's legal data as PG-059's form sends it. */
function legalData(Company $company, array $changes = []): array
{
    return [
        'command_id' => (string) Str::uuid(), 'lock_version' => $company->lock_version, 'legal_name' => $company->legal_name, 'legal_form' => $company->legal_form,
        'npwp' => $company->npwp, 'address_lines' => $company->address_lines, 'city' => $company->city, 'phone' => $company->phone, 'email' => $company->email,
        'director_name' => $company->director_name, ...$changes,
    ];
}

it('creates a company and refuses a duplicate legal identity at its field (criterion 2; ROUTE-131)', function () {
    $body = ['command_id' => (string) Str::uuid(), 'code' => 'BRU', 'legal_name' => 'CV Baru Uji', 'npwp' => '09.876.543.2-109.000'];

    $this->actingAs($this->owner)->post('/pengaturan/perusahaan', $body)->assertRedirect();
    $created = Company::query()->where('code', 'BRU')->sole();
    expect(companyAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($created->is_active)->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'ORG.CompanyCreated')->value('authority_class'))->toBe('OWNER_ONLY');

    $this->actingAs($this->owner)->post('/pengaturan/perusahaan', [...$body, 'command_id' => (string) Str::uuid(), 'code' => 'BRV'])->assertSessionHasErrors('npwp');
    $this->actingAs($this->owner)->post('/pengaturan/perusahaan', [...$body, 'command_id' => (string) Str::uuid(), 'npwp' => null])->assertSessionHasErrors('code');
    expect(Company::query()->count())->toBe(2);
});

it('changes the legal data at the version read, a stale edit answering CONFLICT (criterion 2; ST-01)', function () {
    $this->actingAs($this->owner)->put("/pengaturan/perusahaan/{$this->company->public_id}", legalData($this->company, ['legal_name' => 'PT Nama Baru']))->assertRedirect();
    expect(companyAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($this->company->refresh()->legal_name)->toBe('PT Nama Baru');

    $this->actingAs($this->owner)->put("/pengaturan/perusahaan/{$this->company->public_id}", [...legalData($this->company, ['legal_name' => 'Lain']), 'lock_version' => 0])->assertRedirect();
    expect(companyAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'cause' => 'stale'])->and($this->company->refresh()->legal_name)->toBe('PT Nama Baru');
});

it('asks the step-up only when the change touches an identity asset (criterion 3; AU-15)', function () {
    $this->actingAs($this->owner)->putJson("/pengaturan/perusahaan/{$this->company->public_id}", legalData($this->company, ['director_name' => 'Direktur Baru']))->assertStatus(423);
    $this->actingAs($this->owner)->postJson("/pengaturan/perusahaan/{$this->company->public_id}/rekening", ['command_id' => (string) Str::uuid(), 'action' => 'add'])->assertStatus(423);
    $this->actingAs($this->owner)->postJson("/pengaturan/perusahaan/{$this->company->public_id}/aset-identitas", ['command_id' => (string) Str::uuid()])->assertStatus(423);
    $this->actingAs($this->owner)->putJson("/pengaturan/perusahaan/{$this->company->public_id}", legalData($this->company, ['legal_name' => 'PT Tanpa Step-up']))->assertRedirect();
    $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])
        ->put("/pengaturan/perusahaan/{$this->company->public_id}", legalData($this->company->refresh(), ['director_name' => 'Direktur Baru']))->assertRedirect();

    expect($this->company->refresh()->director_name)->toBe('Direktur Baru');
});

it('changes a referenced bank account by adding the new and deactivating the old, the payment keeping its own (criterion 4; BR-SN-02)', function () {
    $stepUp = fn () => $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()]);
    $stepUp()->post("/pengaturan/perusahaan/{$this->company->public_id}/rekening", ['command_id' => (string) Str::uuid(), 'action' => 'add',
        'bank_name' => 'Bank Uji', 'account_number' => '1234567890', 'account_holder' => 'CV Uji']);
    $old = DB::table('company_bank_accounts')->sole();
    $version = issuedVersion($this->company, ['company_bank_account_id' => $old->id]);

    // In a savepoint of its own: the refused update must not abort the test's transaction.
    expect(fn () => DB::transaction(fn () => DB::table('company_bank_accounts')->where('id', $old->id)->update(['account_number' => '999'])))->toThrow(QueryException::class);

    $stepUp()->post("/pengaturan/perusahaan/{$this->company->public_id}/rekening", ['command_id' => (string) Str::uuid(), 'action' => 'change',
        'account' => RowHandle::of('company_bank_accounts', $old->id), 'lock_version' => 0, 'reason' => 'Pindah rekening',
        'bank_name' => 'Bank Uji', 'account_number' => '5555666677', 'account_holder' => 'CV Uji']);

    expect(companyAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and(DB::table('company_bank_accounts')->where('id', $old->id)->value('is_active'))->toBeFalse()
        ->and(DB::table('company_bank_accounts')->where('is_active', true)->value('account_number'))->toBe('5555666677')
        ->and(DB::table('document_versions')->where('id', $version->id)->value('company_bank_account_id'))->toBe($old->id);
});

it('sets an identity asset from the Owner\'s own upload of the company, and serves the stamp to the Owner only (criteria 5, 6; PJ-19)', function () {
    $file = app(Uploads::class)->accept(Files::path(Files::png()), 'stempel.png', FilePurpose::IdentityAsset, $this->company->id, $this->owner->id);
    $issued = issuedVersion($this->company);
    $payload = DB::table('document_versions')->where('id', $issued->id)->value('payload');

    $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post("/pengaturan/perusahaan/{$this->company->public_id}/aset-identitas",
        ['command_id' => (string) Str::uuid(), 'asset' => 'stamp', 'file' => $file->public_id, 'lock_version' => $this->company->lock_version]);
    $admin = Authorization::admin([$this->company->id], ['projects.view', 'finance.view']);

    expect(companyAnswer()['kind'])->toBe(Outcome::COMMITTED)
        ->and($this->company->refresh()->stamp_file_id)->toBe($file->id)
        ->and(DB::table('document_versions')->where('id', $issued->id)->value('payload'))->toBe($payload);
    $this->actingAs($this->owner)->get("/berkas/{$file->public_id}/unduh")->assertOk();
    $this->actingAs($admin)->get("/berkas/{$file->public_id}/unduh")->assertNotFound();
});

it('derives the readiness list from what exists each time it is shown (criterion 9; ADMIN_FLOW §12.4)', function () {
    $lines = fn () => collect($this->actingAs($this->owner)->get("/pengaturan/perusahaan?perusahaan={$this->company->public_id}")->assertOk()
        ->viewData('page')['props']['company']['readiness'])->keyBy('key');

    expect($lines()['bank_account']['complete'])->toBeFalse();
    DB::table('company_bank_accounts')->insert(['company_id' => $this->company->id, 'bank_name' => 'Bank Uji', 'account_number' => '777', 'account_holder' => 'CV Uji']);
    expect($lines()['bank_account']['complete'])->toBeTrue();
    DB::table('company_tax_profiles')->insert(['company_id' => $this->company->id, 'registration_status' => 'PKP', 'effective' => '[2020-01-01,)']);
    expect($lines()['tax']['complete'])->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasColumn('companies', 'readiness'))->toBeFalse();
});

it('deactivates a company after its confirmation with a reason, a second time refused (ROUTE-133; C-54)', function () {
    $this->actingAs($this->owner)->post("/pengaturan/perusahaan/{$this->company->public_id}/nonaktifkan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0])->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->post("/pengaturan/perusahaan/{$this->company->public_id}/nonaktifkan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'reason' => 'Tidak beroperasi']);
    expect(companyAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($this->company->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->owner)->post("/pengaturan/perusahaan/{$this->company->public_id}/nonaktifkan", ['command_id' => (string) Str::uuid(), 'lock_version' => 1, 'reason' => 'Lagi']);
    expect(companyAnswer()['kind'])->toBe(Outcome::REJECTED);
    $this->actingAs($this->owner)->get('/pengaturan/perusahaan')->assertInertia(fn (AssertableInertia $page) => $page->component('ORG/Companies', false)->where('companies.0.active', false));
});
