<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Actions\DeactivateUser;
use App\Modules\IAM\Actions\GrantAccess;
use App\Modules\IAM\Actions\ResetTotp;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-05 (TASK-024; HO-20; HO-41; M-13; M-21; M-45; AU-09; AU-18; AU-20 c; RV-05; RV-10; RV-11; RV-15; CI-05): the
// Owner's commands against the target's own requests with real database sessions — a high-risk grant and a reset
// ending the sessions the target signed in for real, a deactivation deleting them by account, the next request
// unauthenticated; the reissue's orders with the target's TOTP confirmation and sign-in; and a `command_id` reused by
// another account, replayed after its actor lost the authority, and after its actor was deactivated.

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->admin = Authorization::admin([$this->company->id], ['projects.view']);
    DB::table('users')->where('id', $this->admin->id)->update(['email' => 'sari@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')]);
});

afterEach(fn () => @unlink($this->hotFile));

/** The target signs in for real through PG-001 — and PG-002 with its code when it has TOTP. */
function accountSignsIn(object $test, bool $totp = false): void
{
    $test->postJson('/masuk', ['email' => 'sari@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();

    if ($totp) {
        $test->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
    }

    $test->get('/akun')->assertOk();
}

it('ends the sessions a high-risk grant meets, the target\'s next request unauthenticated (HO-41; AU-18; RV-11)', function () {
    accountSignsIn($this);

    $outcome = app(GrantAccess::class)->run((string) Str::uuid(), $this->owner->id, User::query()->findOrFail($this->admin->id), 'ADMIN_OPERASIONAL', [$this->company->id], ['projects.view', 'finance.view'], 0, null);

    expect($outcome->kind)->toBe('COMMITTED')->and(DB::table('sessions')->where('user_id', $this->admin->id)->exists())->toBeFalse();
    $this->get('/akun')->assertRedirect('/masuk');
    $this->postJson('/masuk', ['email' => 'sari@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertStatus(422);
});

it('keeps the password of a target whose TOTP confirmation committed first, ending its sessions (M-45 confirmation first)', function () {
    $hash = (string) DB::table('users')->where('id', $this->admin->id)->value('password');
    TwoFactor::enrol(User::query()->findOrFail($this->admin->id));
    accountSignsIn($this, totp: true);

    $outcome = app(GrantAccess::class)->run((string) Str::uuid(), $this->owner->id, User::query()->findOrFail($this->admin->id), 'ADMIN_OPERASIONAL', [$this->company->id], ['projects.view', 'cost.view'], 0, null);

    expect($outcome->kind)->toBe('COMMITTED')
        ->and(DB::table('users')->where('id', $this->admin->id)->value('password'))->toBe($hash)
        ->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->exists())->toBeFalse();
});

it('ends the target\'s own sessions at the Owner\'s reset and voids its recovery codes (HO-41; AU-20 c; RV-15; H8-18)', function () {
    TwoFactor::enrol(User::query()->findOrFail($this->admin->id));
    accountSignsIn($this, totp: true);

    $outcome = app(ResetTotp::class)->run((string) Str::uuid(), $this->owner->id, User::query()->findOrFail($this->admin->id), 'Perangkat hilang');

    expect($outcome->kind)->toBe('COMMITTED')
        ->and(DB::table('users')->where('id', $this->admin->id)->value('two_factor_recovery_codes'))->toBeNull();
    $this->get('/akun')->assertRedirect('/masuk');
    $this->postJson('/masuk', ['email' => 'sari@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertStatus(422);
});

it('deletes the target\'s sessions by account at its deactivation, the next request unauthenticated and a new sign-in refused (HO-20; AU-09; RV-05; M-21)', function () {
    accountSignsIn($this);

    expect(app(DeactivateUser::class)->run((string) Str::uuid(), $this->owner->id, User::query()->findOrFail($this->admin->id))->kind)->toBe('COMMITTED')
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->exists())->toBeFalse();
    $this->get('/akun')->assertRedirect('/masuk');
    $this->postJson('/masuk', ['email' => 'sari@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertStatus(422);
});

it('answers a command_id reused by another account as a CONFLICT disclosing nothing, a stale actor as a missing page, a deactivated one as unauthenticated (M-13; CI-05; UXI-16)', function () {
    $other = TwoFactor::enrol(Authorization::owner());
    $commandId = (string) Str::uuid();
    $path = "/pengaturan/pengguna/{$this->admin->public_id}/tautan";

    $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post($path, ['command_id' => $commandId])->assertRedirect();
    $this->actingAs($other)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post($path, ['command_id' => $commandId])->assertRedirect();
    $answer = session('inertia.flash_data')['answer'] ?? [];
    expect($answer['kind'] ?? null)->toBe('CONFLICT')->and($answer['state'] ?? [])->toBe([])
        ->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->count())->toBe(1);

    // The original actor without the authority: the same answer as an unknown address (AZ-11).
    DB::table('user_roles')->where('user_id', $this->owner->id)->update(['role_id' => DB::table('roles')->where('code', 'ADMIN_OPERASIONAL')->value('id')]);
    Authorization::forget();
    $replay = $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post($path, ['command_id' => $commandId]);
    $unknown = $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])->post('/pengaturan/pengguna/00000000-0000-7000-8000-000000000000/tautan', ['command_id' => (string) Str::uuid()]);
    expect($replay->status())->toBe(404)->and($unknown->status())->toBe(404);

    // A deactivated actor is unauthenticated.
    DB::table('users')->where('id', $other->id)->update(['is_active' => false]);
    auth()->forgetGuards();
    $this->actingAs(User::query()->findOrFail($other->id))->withSession([RequireStepUp::CONFIRMED_AT => time()])->post($path, ['command_id' => $commandId])->assertRedirect('/masuk');
});
