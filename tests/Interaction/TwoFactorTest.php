<?php

use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-08 (TASK-018): INT-104, INT-105 and INT-107 on the server's side — each present with its session state and
// absent without it; success, validation and each rejection named —, and SM-17's path from the in-place re-login of
// IP-11 to the code step in the same dialog (AUTH_JOURNEYS §2.5; with TASK-017's criterion 7).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    $this->travelTo(now()->setTimestamp(1_800_000_015));
    $this->user = TwoFactor::enrol(User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']));
    Route::middleware(['web', 'auth', 'access:s0'])->get('/__test/halaman', fn () => response()->json(['ok' => true]));
});

afterEach(fn () => @unlink($this->hotFile));

it('offers PG-002\'s code steps only with a pending attempt, and names each answer (INT-104, INT-105)', function () {
    // Absent without the session state: no attempt, nothing to verify.
    $this->get('/masuk/verifikasi')->assertRedirect('/masuk');
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertStatus(422)->assertJsonPath('expired', true);

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertExactJson(['two_factor' => true]);

    // Validation: MSG-12's field answer for an empty code; a rejection: the generic code answer.
    $this->postJson('/masuk/verifikasi', ['code' => ''])->assertStatus(422)->assertJsonValidationErrors(['code' => __('forms.required')]);
    $this->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => ''])->assertStatus(422)->assertJsonValidationErrors(['recovery_code' => __('forms.required')]);
    $this->postJson('/masuk/verifikasi', ['code' => '000000'])->assertStatus(422)->assertJsonPath('message', __('auth.code_mismatch'));

    // Success: the session established with TOTP.
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk()->assertExactJson(['signed_in' => true]);
    $this->getJson('/__test/halaman')->assertOk();
    $this->get('/masuk/verifikasi')->assertRedirect('/');
});

it('offers PG-004\'s confirmation only to the enrolment-only session (INT-107; SM-02)', function () {
    Authorization::catalogue();
    $owner = Authorization::owner();
    $owner->forceFill(['email' => 'owner@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')])->save();

    $this->get('/verifikasi-dua-langkah')->assertRedirect('/masuk');
    $this->postJson('/verifikasi-dua-langkah', ['code' => '000000'])->assertUnauthorized();

    // A full session, established with TOTP, is refused it.
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
    $this->get('/verifikasi-dua-langkah')->assertRedirect('/');
    $this->postJson('/verifikasi-dua-langkah', ['code' => '000000'])->assertForbidden();
    $this->postJson('/keluar')->assertNoContent();

    $this->postJson('/masuk', ['email' => 'owner@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertJsonPath('enrolment_only', true);
    $this->postJson('/verifikasi-dua-langkah', ['code' => ''])->assertStatus(422)->assertJsonValidationErrors(['code' => __('forms.required')]);
    $this->postJson('/verifikasi-dua-langkah', ['code' => '000000'])->assertStatus(422)->assertJsonPath('message', __('auth.code_mismatch'));
});

it('takes the in-place re-login through the code step in the same dialog, never past the password alone (IP-11; H7-19; SM-17)', function () {
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();

    // The session ends; the dialog's password alone signs nothing in.
    DB::table('sessions')->delete();
    $this->postJson('/keluar');
    auth()->forgetGuards();
    $this->getJson('/__test/halaman')->assertUnauthorized();

    $this->travel(30)->seconds();
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertExactJson(['two_factor' => true]);
    $this->getJson('/__test/halaman')->assertUnauthorized();
    expect(session(TwoFactorChallenge::KEY))->not->toBeNull();

    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertExactJson(['signed_in' => true]);
    $this->getJson('/__test/halaman')->assertOk();
});
