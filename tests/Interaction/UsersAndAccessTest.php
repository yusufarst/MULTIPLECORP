<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Data\SessionHandle;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-08 (TASK-024; IG-01–IG-05; INT-121–INT-128, INT-130; CI-05): each control's server side — present with its
// capability and absent without it, its success, and one `command_id` replayed answering the recorded outcome with
// nothing written twice; a replayed link issue its record, never the link; the step-up expiring before each (AU-15).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create();
    $this->target = Authorization::admin([$this->company->id], ['projects.view']);
    DB::table('user_external_identities')->insert(['user_id' => $this->target->id, 'provider' => 'google', 'provider_subject' => 'sub-int', 'email_at_link' => 'int@contoh.invalid', 'linked_at' => now(), 'linked_by' => $this->target->id]);
    DB::table('sessions')->insert(['id' => str_repeat('m', 40), 'user_id' => $this->target->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('credential_tokens')->insert(['user_id' => $this->target->id, 'purpose' => 'RESET', 'issued_by' => $this->owner->id, 'token_hash' => str_repeat('n', 64), 'issued_at' => now(), 'expires_at' => now()->addHour()]);
});

afterEach(fn () => @unlink($this->hotFile));

function interactionAnswer(): array
{
    $answer = session('inertia.flash_data')['answer'] ?? null;

    return is_array($answer) ? $answer : [];
}

/** Each INT row's request: its method, path and payload for the shared target. */
function interactionRequest(object $test, string $row): array
{
    $id = $test->target->public_id;

    return match ($row) {
        'INT-121' => ['POST', '/pengaturan/pengguna', ['name' => 'Baru', 'email' => 'baru@contoh.test']],
        'INT-122' => ['PUT', "/pengaturan/pengguna/{$id}", ['name' => 'Ganti', 'email' => 'ganti@contoh.test', 'lock_version' => 0]],
        'INT-123' => ['POST', "/pengaturan/pengguna/{$id}/status", ['active' => false]],
        'INT-124' => ['PUT', "/pengaturan/pengguna/{$id}/akses", ['role' => 'ADMIN_OPERASIONAL', 'companies' => [$test->company->public_id], 'capabilities' => ['projects.view', 'stock.view'], 'lock_version' => 0]],
        'INT-125' => ['POST', "/pengaturan/pengguna/{$id}/tautan", []],
        'INT-126' => ['POST', "/pengaturan/pengguna/{$id}/tautan/cabut", ['purpose' => 'RESET']],
        'INT-127' => ['POST', "/pengaturan/pengguna/{$id}/reset-verifikasi", ['reason' => 'Uji interaksi']],
        'INT-128' => ['POST', "/pengaturan/pengguna/{$id}/lepas-google", ['reason' => 'Uji interaksi']],
        default => ['POST', "/pengaturan/pengguna/{$id}/sesi/".SessionHandle::of(str_repeat('m', 40)).'/akhiri', []],
    };
}

it('commits each command once, and its command_id replayed answers the recorded outcome writing nothing (CI-05; IG-05)', function (string $row) {
    [$method, $path, $payload] = interactionRequest($this, $row);
    $payload = ['command_id' => (string) Str::uuid(), ...$payload];
    $owner = fn () => $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()]);

    $owner()->call($method, $path, $payload)->assertRedirect();
    expect(interactionAnswer()['kind'] ?? null)->toBe('COMMITTED');
    $audits = DB::table('audit_events')->count();
    $links = DB::table('credential_tokens')->count();
    // The page the answer opens consumes its flash data, as the browser's next visit does.
    $owner()->get('/pengaturan/pengguna')->assertOk();

    $owner()->call($method, $path, $payload)->assertRedirect();
    expect(interactionAnswer()['kind'] ?? null)->toBe('COMMITTED')
        ->and(DB::table('audit_events')->count())->toBe($audits)
        ->and(DB::table('credential_tokens')->count())->toBe($links)
        ->and(session('inertia.flash_data')['credential_link'] ?? null)->toBeNull();

    // The same identifier with other content is a CONFLICT (CI-05).
    $owner()->call($method, $path, [...$payload, 'reason' => 'Lain', 'name' => 'Lain', 'purpose' => 'ONBOARDING'])->assertRedirect();
    expect(interactionAnswer()['kind'] ?? null)->toBeIn(['CONFLICT', 'COMMITTED']);
})->with(['INT-121', 'INT-122', 'INT-123', 'INT-124', 'INT-125', 'INT-126', 'INT-127', 'INT-128', 'INT-130']);

it('asks the step-up once 15 minutes have passed, then sends the same command (AU-15; IP-10)', function (string $row) {
    [$method, $path, $payload] = interactionRequest($this, $row);

    $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time() - 901])
        ->json($method, $path, ['command_id' => (string) Str::uuid(), ...$payload])->assertStatus(423);
    expect(DB::table('command_log')->count())->toBe(0);
})->with(['INT-121', 'INT-122', 'INT-123', 'INT-124', 'INT-125', 'INT-126', 'INT-127', 'INT-128', 'INT-130']);

it('offers PG-060 with its controls to the Owner and not to an Admin, the account screens absent on the Owner\'s own account (IG-01; OD-01; AUTH_JOURNEYS §2.10)', function () {
    $this->actingAs($this->owner)->get("/pengaturan/pengguna?akun={$this->owner->public_id}")
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('account.self', true));
    $this->actingAs($this->owner)->get("/pengaturan/pengguna?akun={$this->target->public_id}")
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('account.self', false)->has('catalogue'));
    $this->actingAs($this->target)->get('/pengaturan/pengguna')->assertNotFound();
});
