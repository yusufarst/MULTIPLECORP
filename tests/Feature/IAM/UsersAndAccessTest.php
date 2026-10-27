<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Data\SessionHandle;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 (TASK-024; ROUTE-060, ROUTE-121–ROUTE-128, ROUTE-130; NX-02; OD-01; OD-02; RG-02–RG-10; AU-01; AU-11; AU-12;
// AU-18; AU-20 c; AU-24; RV-04; RV-05; RV-11; RV-15; criteria 1–8 and 10): PG-060 and its commands over HTTP — each
// answer in place as flash data, each refusal its contract names: a duplicate e-mail at the field, a stale
// `lock_version`, RG-09, NX-02's checks, a self-reset — and the step-up before every command.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view']);
});

afterEach(fn () => @unlink($this->hotFile));

/** The Owner, its password confirmed within the step-up's window (AU-15). */
function usersOwner(object $test): object
{
    return $test->actingAs($test->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()]);
}

function usersCommand(array $input = []): array
{
    return ['command_id' => (string) Str::uuid(), ...$input];
}

function usersAnswer(): array
{
    $answer = session('inertia.flash_data')['answer'] ?? null;

    return is_array($answer) ? $answer : [];
}

function usersLink(): ?array
{
    $link = session('inertia.flash_data')['credential_link'] ?? null;

    return is_array($link) ? $link : null;
}

function usersAudit(string $action): ?object
{
    return DB::table('audit_events')->where('action', $action)->orderByDesc('id')->first();
}

/** A session row as the store writes it: its attributes serialized as configured, encrypted when configured (AU-06). */
function usersSession(User $user, string $id): void
{
    $attributes = ['auth' => ['signed_in_at' => time() - 60]];
    $data = config('session.serialization') === 'json' ? json_encode($attributes, JSON_THROW_ON_ERROR) : serialize($attributes);
    $payload = base64_encode(config('session.encrypt') === true ? Crypt::encrypt($data) : $data);
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0', 'payload' => $payload, 'last_activity' => time()]);
}

it('shows the directory and an account\'s detail to the Owner, never a secret, and nothing to an Admin (ROUTE-060; PJ-17; OD-01; criterion 1)', function () {
    DB::table('credential_tokens')->insert(['user_id' => $this->admin->id, 'purpose' => 'ONBOARDING', 'issued_by' => $this->owner->id, 'token_hash' => str_repeat('c', 64), 'issued_at' => now(), 'expires_at' => now()->addDay()]);
    usersSession($this->admin, str_repeat('s', 40));

    $page = usersOwner($this)->get('/pengaturan/pengguna?akun='.$this->admin->public_id.'&bagian=sesi')->assertOk();
    $page->assertInertia(fn (AssertableInertia $inertia) => $inertia
        ->component('IAM/UsersAndAccess', false)
        ->has('accounts.rows', 2)
        ->where('accounts.more', false)
        ->where('account.id', $this->admin->public_id)
        ->where('account.capabilities', ['projects.view'])
        ->where('account.companies.0.id', $this->company->public_id)
        ->where('account.sessions.0.handle', SessionHandle::of(str_repeat('s', 40)))
        ->where('account.sessions.0.device', fn (?string $device) => $device !== null)
        ->where('account.sessions.0.started_at', fn (?string $started) => $started !== null)
        ->where('account.links.0.purpose', 'ONBOARDING')
        ->where('section', 'sesi'));

    expect($page->getContent())->not->toContain(str_repeat('s', 40))->not->toContain(str_repeat('c', 64))
        ->not->toContain((string) DB::table('users')->where('id', $this->owner->id)->value('two_factor_secret'));

    $this->actingAs($this->admin)->get('/pengaturan/pengguna')->assertNotFound();
    $this->actingAs($this->admin)->post('/pengaturan/pengguna', usersCommand(['name' => 'X', 'email' => 'x@contoh.test']))->assertNotFound();
    usersOwner($this)->get('/pengaturan/pengguna?akun=00000000-0000-7000-8000-000000000000')->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('account', null));
});

it('asks the step-up before every command once 15 minutes have passed (AU-15; IP-10; criterion 8)', function (string $method, string $path) {
    $path = str_replace('{id}', $this->admin->public_id, $path);

    $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time() - 901])->json($method, $path, usersCommand())
        ->assertStatus(423)->assertJsonPath('message', __('messages.msg_27'));
})->with([
    'create' => ['POST', '/pengaturan/pengguna'],
    'edit' => ['PUT', '/pengaturan/pengguna/{id}'],
    'status' => ['POST', '/pengaturan/pengguna/{id}/status'],
    'access' => ['PUT', '/pengaturan/pengguna/{id}/akses'],
    'link' => ['POST', '/pengaturan/pengguna/{id}/tautan'],
    'revoke link' => ['POST', '/pengaturan/pengguna/{id}/tautan/cabut'],
    'reset' => ['POST', '/pengaturan/pengguna/{id}/reset-verifikasi'],
    'unlink' => ['POST', '/pengaturan/pengguna/{id}/lepas-google'],
    'end session' => ['POST', '/pengaturan/pengguna/{id}/sesi/'.str_repeat('a', 32).'/akhiri'],
]);

it('creates one inactive account by its normalized e-mail with no access, a duplicate answered at the field (ROUTE-121; AU-01; RG-04; criterion 2)', function () {
    usersOwner($this)->post('/pengaturan/pengguna', usersCommand(['name' => 'Dewi Uji', 'email' => '  Dewi@Contoh.TEST ']))->assertRedirect();

    $dewi = User::query()->where('email', 'dewi@contoh.test')->firstOrFail();
    expect($dewi->is_active)->toBeFalse()
        ->and(DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_id', $dewi->id)->value('roles.code'))->toBe('ADMIN_OPERASIONAL')
        ->and(DB::table('user_capability_grants')->where('user_id', $dewi->id)->exists())->toBeFalse()
        ->and(DB::table('user_company_grants')->where('user_id', $dewi->id)->exists())->toBeFalse()
        ->and(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(usersAudit('IAM.UserCreated')?->authority_class)->toBe('OWNER_ONLY')
        ->and(json_decode((string) usersAudit('IAM.UserCreated')?->after, true))->toMatchArray(['role' => 'ADMIN_OPERASIONAL', 'is_active' => false]);

    usersOwner($this)->post('/pengaturan/pengguna', usersCommand(['name' => 'Dewi Lain', 'email' => 'DEWI@contoh.test']))
        ->assertSessionHasErrors(['email' => __('users.validation.email_taken', ['nilai' => 'dewi@contoh.test'])]);
});

it('edits an account against its lock_version, a stale one a CONFLICT (ROUTE-122; IP-03)', function () {
    $version = (int) DB::table('users')->where('id', $this->admin->id)->value('lock_version');

    usersOwner($this)->put("/pengaturan/pengguna/{$this->admin->public_id}", usersCommand(['name' => 'Nama Baru', 'email' => 'baru@contoh.test', 'lock_version' => $version]))->assertRedirect();
    expect(DB::table('users')->where('id', $this->admin->id)->value('name'))->toBe('Nama Baru')->and(usersAnswer()['kind'] ?? null)->toBe('COMMITTED');

    usersOwner($this)->put("/pengaturan/pengguna/{$this->admin->public_id}", usersCommand(['name' => 'Lama', 'email' => 'baru@contoh.test', 'lock_version' => $version]))->assertRedirect();
    expect(usersAnswer())->toMatchArray(['kind' => 'CONFLICT', 'cause' => 'stale'])
        ->and(DB::table('users')->where('id', $this->admin->id)->value('name'))->toBe('Nama Baru');

    usersOwner($this)->put("/pengaturan/pengguna/{$this->admin->public_id}", usersCommand(['name' => 'X', 'email' => $this->owner->email, 'lock_version' => $version + 1]))->assertSessionHasErrors('email');
});

it('deactivates an account in one command: grants revoked, Google unlinked, links revoked, sessions ended (ROUTE-123; RG-10; AU-09; RV-05; criterion 5)', function () {
    DB::table('user_external_identities')->insert(['user_id' => $this->admin->id, 'provider' => 'google', 'provider_subject' => 'sub-admin', 'email_at_link' => 'admin@contoh.invalid', 'linked_at' => now(), 'linked_by' => $this->admin->id]);
    DB::table('credential_tokens')->insert(['user_id' => $this->admin->id, 'purpose' => 'RESET', 'issued_by' => $this->owner->id, 'token_hash' => str_repeat('d', 64), 'issued_at' => now(), 'expires_at' => now()->addHour()]);
    usersSession($this->admin, str_repeat('e', 40));

    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/status", usersCommand(['active' => false]))->assertRedirect();

    expect(DB::table('users')->where('id', $this->admin->id)->value('is_active'))->toBeFalse()
        ->and(DB::table('user_capability_grants')->where('user_id', $this->admin->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(DB::table('user_company_grants')->where('user_id', $this->admin->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(DB::table('user_external_identities')->where('user_id', $this->admin->id)->first(['unlink_reason', 'unlinked_by']))->toEqual((object) ['unlink_reason' => 'DEACTIVATION', 'unlinked_by' => $this->owner->id])
        ->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->exists())->toBeFalse()
        ->and(json_decode((string) usersAudit('IAM.UserDeactivated')?->after, true)['is_active'] ?? null)->toBeFalse()
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_UNLINKED')->where('reason_code', 'DEACTIVATION')->exists())->toBeTrue();

    // The account's next request is unauthenticated (AU-09).
    auth()->forgetGuards();
    $this->actingAs(User::query()->findOrFail($this->admin->id))->get('/akun')->assertRedirect('/masuk');
});

it('refuses deactivating the last active Owner with the rule in words, and lets an Owner deactivate itself while another remains (RG-09; M-22; criterion 4)', function () {
    usersOwner($this)->post("/pengaturan/pengguna/{$this->owner->public_id}/status", usersCommand(['active' => false]))->assertRedirect();
    expect(usersAnswer())->toMatchArray(['kind' => 'REJECTED', 'text' => __('users.refusal.rg_09')])
        ->and(DB::table('users')->where('id', $this->owner->id)->value('is_active'))->toBeTrue();

    TwoFactor::enrol(Authorization::owner());
    usersOwner($this)->post("/pengaturan/pengguna/{$this->owner->public_id}/status", usersCommand(['active' => false]))->assertRedirect();
    expect(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')->and(DB::table('users')->where('id', $this->owner->id)->value('is_active'))->toBeFalse();
});

it('grants the role, companies and capabilities after the preview, refusing OWNER_ONLY, a missing prerequisite and an unexplained ADM_PLUS change (ROUTE-124; RG-02; RG-06; RG-08; criterion 2, 3)', function () {
    $second = Company::factory()->create();
    $version = fn (): int => (int) DB::table('users')->where('id', $this->admin->id)->value('lock_version');
    $path = "/pengaturan/pengguna/{$this->admin->public_id}/akses";

    // The preview's partial reload: every granted capability against every granted company.
    usersOwner($this)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'IAM/UsersAndAccess', 'X-Inertia-Partial-Data' => 'preview'])
        ->get('/pengaturan/pengguna?'.http_build_query(['akun' => $this->admin->public_id, 'peran' => 'ADMIN_OPERASIONAL', 'perusahaan' => [$this->company->public_id, $second->public_id], 'kewenangan' => ['projects.view', 'stock.view']]))
        ->assertOk()
        ->assertJsonPath('props.preview.rows.0.code', 'stock.view')
        ->assertJsonPath("props.preview.rows.0.marks.{$second->public_id}", 'added')
        ->assertJsonPath("props.preview.rows.1.marks.{$this->company->public_id}", 'kept');

    usersOwner($this)->put($path, usersCommand(['role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->company->public_id], 'capabilities' => ['projects.view', 'audit.view'], 'lock_version' => $version()]))->assertSessionHasErrors('capabilities.1');
    usersOwner($this)->put($path, usersCommand(['role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->company->public_id], 'capabilities' => ['projects.view', 'profit.view'], 'lock_version' => $version()]));
    expect(usersAnswer()['text'] ?? null)->toBe(__('users.refusal.rg_06'));
    usersOwner($this)->put($path, usersCommand(['role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->company->public_id], 'capabilities' => ['projects.view', 'stock.view', 'stock.allocate_intercompany'], 'lock_version' => $version()]))->assertSessionHasErrors('reason');

    usersOwner($this)->put($path, usersCommand(['role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->company->public_id, $second->public_id], 'capabilities' => ['projects.view', 'stock.view', 'stock.allocate_intercompany'], 'lock_version' => $version(), 'reason' => 'Mengurus alokasi antarperusahaan']));
    expect(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(DB::table('user_capability_grants')->join('capabilities', 'capabilities.id', '=', 'capability_id')->where('user_id', $this->admin->id)->whereNull('revoked_at')->orderBy('code')->pluck('code')->all())->toBe(['projects.view', 'stock.allocate_intercompany', 'stock.view'])
        ->and(DB::table('user_capability_grants')->join('capabilities', 'capabilities.id', '=', 'capability_id')->where('code', 'stock.allocate_intercompany')->value('reason'))->toBe('Mengurus alokasi antarperusahaan')
        ->and(DB::table('user_company_grants')->where('user_id', $this->admin->id)->whereNull('revoked_at')->count())->toBe(2)
        ->and(usersAudit('IAM.GrantsChanged')?->reason)->toBe('Mengurus alokasi antarperusahaan')
        ->and(usersAudit('IAM.GrantsChanged')?->authority_class)->toBe('OWNER_ONLY');

    // A prerequisite removed keeps its dependant's grant, disabled until it is granted again (RG-06).
    usersOwner($this)->put($path, usersCommand(['role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->company->public_id, $second->public_id], 'capabilities' => ['projects.view', 'stock.allocate_intercompany'], 'lock_version' => $version()]));
    Authorization::forget();
    expect(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(DB::table('user_capability_grants')->join('capabilities', 'capabilities.id', '=', 'capability_id')->where('user_id', $this->admin->id)->whereNull('revoked_at')->orderBy('code')->pluck('code')->all())->toBe(['projects.view', 'stock.allocate_intercompany'])
        ->and(app(EffectiveCapabilities::class)->for($this->admin->id)->has('stock.allocate_intercompany'))->toBeFalse();

    // Nobody grants to itself (OD-02).
    usersOwner($this)->put("/pengaturan/pengguna/{$this->owner->public_id}/akses", usersCommand(['role' => 'ADMIN_OPERASIONAL', 'companies' => [], 'capabilities' => [], 'lock_version' => 0]));
    expect(usersAnswer()['text'] ?? null)->toBe(__('users.refusal.od_02_self'));
});

it('reissues the credentials of an active account without TOTP that a grant makes high-risk, shown once (ROUTE-124; AU-18; RV-11; criterion 6)', function () {
    usersSession($this->admin, str_repeat('f', 40));
    $before = (string) DB::table('users')->where('id', $this->admin->id)->value('password');
    $version = (int) DB::table('users')->where('id', $this->admin->id)->value('lock_version');

    usersOwner($this)->put("/pengaturan/pengguna/{$this->admin->public_id}/akses", usersCommand(['role' => 'ADMIN_OPERASIONAL', 'companies' => [$this->company->public_id], 'capabilities' => ['projects.view', 'finance.view'], 'lock_version' => $version]));

    $link = usersLink();
    expect(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and($link['purpose'] ?? null)->toBe('RESET')
        ->and((string) ($link['url'] ?? ''))->toStartWith(rtrim((string) config('app.url'), '/').'/atur-kata-sandi#')
        ->and(DB::table('users')->where('id', $this->admin->id)->value('password'))->not->toBe($before)
        ->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->where('purpose', 'RESET')->whereNull('revoked_at')->where('token_hash', CredentialLinks::hash(Str::after((string) $link['url'], '#')))->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->exists())->toBeFalse()
        ->and(DB::table('security_events')->where('event_kind', 'PASSWORD_RESET_REQUESTED')->where('reason_code', 'HIGH_RISK_GRANT')->exists())->toBeTrue();
});

it('activates an account given high-risk grants while inactive with a RESET link in place of its ONBOARDING one (ROUTE-123; AU-12; RV-11; M-45)', function () {
    DB::table('users')->where('id', $this->admin->id)->update(['is_active' => false]);
    Authorization::grant($this->admin, 'cost.view');
    DB::table('credential_tokens')->insert(['user_id' => $this->admin->id, 'purpose' => 'ONBOARDING', 'issued_by' => $this->owner->id, 'token_hash' => str_repeat('g', 64), 'issued_at' => now(), 'expires_at' => now()->addDay()]);

    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/status", usersCommand(['active' => true]))->assertRedirect();

    expect(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(usersLink()['purpose'] ?? null)->toBe('RESET')
        ->and(DB::table('credential_tokens')->where('token_hash', str_repeat('g', 64))->value('revoked_at'))->not->toBeNull()
        ->and(DB::table('users')->where('id', $this->admin->id)->value('is_active'))->toBeTrue();
});

it('shows a credential link once and a replayed issue its record, never the link (ROUTE-125; ROUTE-126; AU-12; CI-05; criterion 7)', function () {
    $issue = usersCommand();
    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/tautan", $issue)->assertRedirect();
    $link = usersLink();
    expect($link['purpose'] ?? null)->toBe('ONBOARDING')
        ->and(DB::table('audit_events')->where('action', 'IAM.CredentialLinkIssued')->value('metadata'))->not->toContain(Str::after((string) $link['url'], '#'));

    // The page that showed it consumes the flash data; the replay flashes no link (CI-05).
    usersOwner($this)->get("/pengaturan/pengguna?akun={$this->admin->public_id}")->assertOk();
    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/tautan", $issue)->assertRedirect();
    expect(usersLink())->toBeNull()->and(usersAnswer()['text'] ?? null)->toBe(__('users.answer.link_replayed'))
        ->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->count())->toBe(1);

    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/tautan/cabut", usersCommand(['purpose' => 'ONBOARDING']))->assertRedirect();
    expect(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->value('revoked_at'))->not->toBeNull()
        ->and(usersAudit('IAM.CredentialLinkRevoked'))->not->toBeNull();
    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/tautan/cabut", usersCommand(['purpose' => 'ONBOARDING']));
    expect(usersAnswer()['text'] ?? null)->toBe(__('users.refusal.au_11_no_link'));

    // A revoked link answers MSG-28 like a used or expired one (AU-12).
    $this->get('/atur-kata-sandi')->assertOk();
});

it('resets another account\'s TOTP with a reason — fields cleared, password unusable, Google unlinked, one RESET link, sessions ended — and refuses the Owner\'s own (ROUTE-127; AU-20 c; RV-15; H8-18; criterion 8)', function () {
    $target = TwoFactor::enrol(Authorization::admin([$this->company->id], ['projects.view']));
    $codes = TwoFactor::storedCodes($target);
    DB::table('user_external_identities')->insert(['user_id' => $target->id, 'provider' => 'google', 'provider_subject' => 'sub-target', 'email_at_link' => 't@contoh.invalid', 'linked_at' => now(), 'linked_by' => $target->id]);
    usersSession($target, str_repeat('h', 40));

    usersOwner($this)->post("/pengaturan/pengguna/{$target->public_id}/reset-verifikasi", usersCommand(['reason' => '']))->assertSessionHasErrors('reason');
    usersOwner($this)->post("/pengaturan/pengguna/{$target->public_id}/reset-verifikasi", usersCommand(['reason' => 'Ponsel hilang, dilaporkan langsung']))->assertRedirect();

    $row = DB::table('users')->where('id', $target->id)->first(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step', 'password']);
    expect([$row->two_factor_secret, $row->two_factor_recovery_codes, $row->two_factor_confirmed_at, $row->two_factor_last_step])->toBe([null, null, null, null])
        ->and(Hash::check('Kata-Sandi-Uji-9', (string) $row->password))->toBeFalse()
        ->and(usersLink()['purpose'] ?? null)->toBe('RESET')
        ->and(DB::table('user_external_identities')->where('user_id', $target->id)->first(['unlink_reason', 'unlinked_by']))->toEqual((object) ['unlink_reason' => 'CREDENTIAL_REISSUE', 'unlinked_by' => $this->owner->id])
        ->and(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(usersAudit('IAM.TotpReset')?->reason)->toBe('Ponsel hilang, dilaporkan langsung')
        ->and(DB::table('security_events')->where('event_kind', 'TOTP_RESET')->where('user_id', $target->id)->exists())->toBeTrue()
        ->and($codes)->toHaveCount(8);

    usersOwner($this)->post("/pengaturan/pengguna/{$this->owner->public_id}/reset-verifikasi", usersCommand(['reason' => 'Uji']));
    expect(usersAnswer()['text'] ?? null)->toBe(__('users.refusal.au_20_self'));
});

it('reissues the credentials of an account without TOTP, never a TOTP_RESET (ROUTE-127; DIR-044 R-05; LG-02)', function () {
    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/reset-verifikasi", usersCommand(['reason' => 'Lupa kata sandi']))->assertRedirect();

    expect(usersAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(usersAudit('IAM.CredentialsReissued'))->not->toBeNull()
        ->and(DB::table('security_events')->where('event_kind', 'TOTP_RESET')->exists())->toBeFalse()
        ->and(DB::table('security_events')->where('event_kind', 'PASSWORD_RESET_REQUESTED')->where('reason_code', 'CREDENTIAL_REISSUE')->exists())->toBeTrue();
});

it('unlinks another account\'s Google identity with a reason, ending its sessions, and refuses one without a link (ROUTE-128; AU-24 OWNER; criterion 8)', function () {
    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/lepas-google", usersCommand(['reason' => 'Akun Google lama']));
    expect(usersAnswer()['text'] ?? null)->toBe(__('users.refusal.au_24_no_link'));

    DB::table('user_external_identities')->insert(['user_id' => $this->admin->id, 'provider' => 'google', 'provider_subject' => 'sub-a', 'email_at_link' => 'a@contoh.invalid', 'linked_at' => now(), 'linked_by' => $this->admin->id]);
    usersSession($this->admin, str_repeat('i', 40));
    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/lepas-google", usersCommand(['reason' => 'Akun Google lama']))->assertRedirect();

    expect(DB::table('user_external_identities')->where('user_id', $this->admin->id)->first(['unlink_reason', 'unlinked_by']))->toEqual((object) ['unlink_reason' => 'OWNER', 'unlinked_by' => $this->owner->id])
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->exists())->toBeFalse()
        ->and(usersAudit('IAM.GoogleUnlinked')?->reason)->toBe('Akun Google lama');
});

it('ends one session by its handle, refusing an ended one and the acting one (ROUTE-130; AU-10; PJ-17)', function () {
    usersSession($this->admin, str_repeat('j', 40));
    usersSession($this->admin, str_repeat('k', 40));
    $handle = SessionHandle::of(str_repeat('j', 40));

    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/sesi/{$handle}/akhiri", usersCommand())->assertRedirect();
    expect(DB::table('sessions')->where('user_id', $this->admin->id)->pluck('id')->all())->toBe([str_repeat('k', 40)])
        ->and(usersAudit('IAM.SessionEnded')?->metadata)->not->toContain(str_repeat('j', 40));

    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/sesi/{$handle}/akhiri", usersCommand());
    expect(usersAnswer()['text'] ?? null)->toBe(__('users.refusal.au_10_gone'));

    usersOwner($this)->post("/pengaturan/pengguna/{$this->admin->public_id}/sesi/bukan-handle/akhiri", usersCommand())->assertNotFound();
});
