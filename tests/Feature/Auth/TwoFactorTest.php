<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\EnforceSessionLimits;
use App\Modules\IAM\Authentication\EstablishSession;
use App\Modules\IAM\Authentication\SecondFactorLimit;
use App\Modules\IAM\Authentication\Totp;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Authentication\TwoFactorSteps;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 and TL-14 (TASK-018; SM-17–SM-19): AU-16's challenge after the password — one code once, the generic code
// answer for every failing code, the expiry and "Kembali" —, AU-19's recovery codes after a password only, AU-18's
// enrolment-only session reaching PG-004 alone and its confirmation, and AU-05's second-factor limit with its
// cooldown, escalation and durable floor — acceptance criteria 1, 3, 4 and 6.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    $this->travelTo(now()->setTimestamp(1_800_000_015));
    $this->user = TwoFactor::enrol(User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']));
    Route::middleware(['web', 'auth', 'access:s0'])->get('/__test/halaman', fn () => response()->json(['user' => request()->user()?->getAuthIdentifier()]));
});

afterEach(fn () => @unlink($this->hotFile));

function signInWithPassword(string $email = 'laras@contoh.test'): void
{
    test()->postJson('/masuk', ['email' => $email, 'password' => 'Kata-Sandi-Uji-9'])->assertOk()->assertExactJson(['two_factor' => true]);
}

function securityEvents(string $kind): int
{
    return DB::table('security_events')->where('event_kind', $kind)->count();
}

it('meets a correct password with the challenge and no session, then signs in with one code (AU-16; SM-17)', function () {
    $this->post('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertRedirect('/masuk/verifikasi');
    expect(auth()->check())->toBeFalse()
        ->and(session(TwoFactorChallenge::KEY)['user'] ?? null)->toBe($this->user->id);

    $this->get('/masuk/verifikasi')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/TwoFactorChallenge', false)->where('recovery', true)->where('auth.capabilities', []));
    $before = session()->getId();

    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk()->assertExactJson(['signed_in' => true]);

    expect(auth()->id())->toBe($this->user->id)
        ->and(session(TwoFactorChallenge::METHOD))->toBe(EstablishSession::TOTP)
        ->and(session(TwoFactorChallenge::KEY))->toBeNull()
        ->and(session()->getId())->not->toBe($before)
        ->and(DB::table('security_events')->where('event_kind', 'LOGIN_SUCCEEDED')->value('details'))->toContain('totp');
});

it('passes one code once: the same code again, an older one and a wrong one get the generic code answer (AU-16; M-39; HO-40)', function () {
    signInWithPassword();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
    $this->postJson('/keluar')->assertNoContent();

    foreach ([TwoFactor::code(), TwoFactor::code(offset: -1), '000000', 'abc'] as $code) {
        signInWithPassword();
        $this->postJson('/masuk/verifikasi', ['code' => $code])->assertStatus(422)
            ->assertExactJson(['message' => __('auth.code_mismatch'), 'errors' => ['code' => [__('auth.code_mismatch')]]]);
        expect(auth()->check())->toBeFalse();
        $this->get('/masuk');
    }

    expect(securityEvents('TWO_FACTOR_FAILED'))->toBe(4);
});

it('sends an expired attempt back to PG-001 with the e-mail kept (AU-16; NAV-002)', function () {
    $this->post('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
    $this->travel(TwoFactorChallenge::LIFETIME_SECONDS + 1)->seconds();

    $this->post('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertRedirect('/masuk');
    $this->get('/masuk')->assertInertia(fn ($page) => $page->component('Auth/SignIn', false)->where('email', 'laras@contoh.test'));
    expect(auth()->check())->toBeFalse();
});

it('discards the pending attempt when PG-001 is requested — "Kembali" — the e-mail kept (NAV-002; OQ-14; criterion 6)', function () {
    $this->post('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);

    $this->get('/masuk')->assertInertia(fn ($page) => $page->component('Auth/SignIn', false)->where('email', 'laras@contoh.test'));
    $this->post('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertRedirect('/masuk');
    expect(auth()->check())->toBeFalse()
        ->and(session(TwoFactorChallenge::KEY))->toBeNull();
});

it('voids the challenge when the account\'s credential state changes meanwhile (AU-16)', function () {
    signInWithPassword();
    DB::table('users')->where('id', $this->user->id)->update(['password' => bcrypt('lain')]);

    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertStatus(422);
    expect(auth()->check())->toBeFalse()->and(session(TwoFactorChallenge::KEY))->toBeNull();
});

it('uses a recovery code once, after a password only, into an enrolment-only session (AU-19; AU-20 b; criterion 3)', function () {
    $other = User::factory()->create();
    DB::table('credential_tokens')->insert(['user_id' => $this->user->id, 'purpose' => 'RESET', 'issued_by' => $other->id, 'token_hash' => str_repeat('a', 64), 'issued_at' => now(), 'expires_at' => now()->addHour()]);
    signInWithPassword();

    $this->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => TwoFactor::CODES[3]])->assertOk()
        ->assertExactJson(['signed_in' => true, 'enrolment_only' => true, 'location' => '/verifikasi-dua-langkah']);

    expect(TwoFactor::storedCodes($this->user))->toHaveCount(7)->not->toContain(TwoFactor::CODES[3])
        ->and(session(TwoFactorChallenge::ENROLMENT_ONLY))->toBeTrue()
        ->and(session(TwoFactorChallenge::RECOVERY_MARK))->toBeTrue()
        ->and(DB::table('credential_tokens')->whereNull('revoked_at')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'IAM.RecoveryCodeUsed')->where('actor_user_id', $this->user->id)->where('authority_class', 'SYSTEM')->count())->toBe(1)
        ->and(securityEvents('RECOVERY_CODE_USED'))->toBe(1);

    $this->postJson('/keluar')->assertNoContent();
    signInWithPassword();
    $this->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => TwoFactor::CODES[3]])->assertStatus(422)
        ->assertJsonPath('errors.recovery_code.0', __('auth.code_mismatch'));
});

it('keeps an emptied recovery-code set an encrypted empty list, never NULL (AU-19; C-62)', function () {
    DB::table('users')->where('id', $this->user->id)->update(['two_factor_recovery_codes' => Crypt::encryptString(json_encode([TwoFactor::CODES[0]]))]);
    signInWithPassword();

    $this->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => TwoFactor::CODES[0]])->assertOk();

    expect(DB::table('users')->where('id', $this->user->id)->value('two_factor_recovery_codes'))->not->toBeNull()
        ->and(TwoFactor::storedCodes($this->user))->toBe([]);
});

it('refuses a recovery code at a challenge after Google, like any failing code (AU-19; AU-22)', function () {
    signInWithPassword();
    $challenge = session(TwoFactorChallenge::KEY);
    session([TwoFactorChallenge::KEY => ['purpose' => TwoFactorChallenge::PURPOSE_GOOGLE] + $challenge]);

    $this->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => TwoFactor::CODES[0]])->assertStatus(422);
    expect(TwoFactor::storedCodes($this->user))->toHaveCount(8)->and(auth()->check())->toBeFalse();
});

it('starts the account-wide cooldown at the fifth failed code, across addresses, refusing even a right code unevaluated (AU-05; SM-18)', function () {
    foreach (range(1, SecondFactorLimit::THRESHOLD) as $attempt) {
        signInWithPassword();
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])->postJson('/masuk/verifikasi', ['code' => '000000'])->assertStatus(422);
        $this->get('/masuk');
    }

    $failures = DB::table('security_events')->where('event_kind', 'TWO_FACTOR_FAILED')->pluck('details')->map(fn ($details) => json_decode((string) $details, true));
    expect($failures)->toHaveCount(5)
        ->and($failures->every(fn (array $details): bool => $details['path'] === 'PASSWORD' && is_string($details['submission_id']) && is_int($details['place_time'])))->toBeTrue()
        ->and(json_decode((string) DB::table('security_events')->where('event_kind', 'TWO_FACTOR_COOLDOWN_STARTED')->value('details'), true))
        ->toMatchArray(['escalation_level' => 1, 'cooldown_seconds' => 60, 'path' => 'PASSWORD']);

    signInWithPassword();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertStatus(429)
        ->assertJsonPath('message', trans_choice('messages.msg_20', 1, ['count' => 1]));
    expect(auth()->check())->toBeFalse()
        ->and(securityEvents('TWO_FACTOR_FAILED'))->toBe(5)
        ->and(securityEvents('TWO_FACTOR_COOLDOWN_REFUSED'))->toBe(1);

    // The cooldown ends; the account was never locked.
    $this->travel(61)->seconds();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
});

it('doubles the cooldown at the next breach within 24 hours, and a success resets nothing (AU-05)', function () {
    // Each from its own address, so the per-account-and-address throttle of the route stays out of the count.
    $breach = function (int $round) {
        foreach (range(1, SecondFactorLimit::THRESHOLD) as $attempt) {
            signInWithPassword();
            $this->withServerVariables(['REMOTE_ADDR' => "10.{$round}.0.{$attempt}"])->postJson('/masuk/verifikasi', ['code' => '000000'])->assertStatus(422);
            $this->get('/masuk');
        }
    };

    $breach(1);
    $this->travel(61)->seconds();
    signInWithPassword();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
    $this->postJson('/keluar');
    $breach(2);

    expect(DB::table('security_events')->where('event_kind', 'TWO_FACTOR_COOLDOWN_STARTED')->orderBy('id')->pluck('details')->map(fn ($d) => json_decode((string) $d, true)['cooldown_seconds'])->all())->toBe([60, 120]);
});

it('keeps the count through a loss of the limiter store, from the durable floor: two more failures breach (AU-05; IX-19)', function () {
    $fail = function (int $attempt) {
        signInWithPassword();
        $this->withServerVariables(['REMOTE_ADDR' => "10.9.0.{$attempt}"])->postJson('/masuk/verifikasi', ['code' => '000000'])->assertStatus(422);
        $this->get('/masuk');
    };

    foreach (range(1, 3) as $attempt) {
        $fail($attempt);
    }

    Cache::store('array')->flush();

    foreach (range(4, 5) as $attempt) {
        $fail($attempt);
    }

    expect(securityEvents('TWO_FACTOR_COOLDOWN_STARTED'))->toBe(1);
});

it('keeps the cooldown through a loss of the limiter store, from the durable floor (AU-05; IX-19)', function () {
    foreach (range(1, SecondFactorLimit::THRESHOLD) as $attempt) {
        signInWithPassword();
        $this->postJson('/masuk/verifikasi', ['code' => '000000']);
        $this->get('/masuk');
    }

    Cache::store('array')->flush();

    signInWithPassword();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertStatus(429);
    expect(auth()->check())->toBeFalse();
});

it('refuses the step when its failure cannot be recorded — fail closed (AU-05)', function () {
    signInWithPassword();
    config(['security.event_connection' => 'missing']);

    $this->postJson('/masuk/verifikasi', ['code' => '000000'])->assertStatus(429);
});

it('gives a high-risk account without TOTP an enrolment-only session that reaches PG-004 alone (AU-18; criterion 1)', function () {
    Authorization::catalogue();
    $owner = Authorization::owner();
    DB::table('users')->where('id', $owner->id)->update(['email' => 'owner@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')]);

    $this->postJson('/masuk', ['email' => 'owner@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk()
        ->assertJsonPath('enrolment_only', true)->assertJsonPath('location', '/verifikasi-dua-langkah');

    expect(session(TwoFactorChallenge::ENROLMENT_ONLY))->toBeTrue();
    $this->get('/__test/halaman')->assertRedirect('/verifikasi-dua-langkah');
    $this->getJson('/__test/halaman')->assertForbidden();
    $this->postJson('/cari', ['term' => 'x'])->assertForbidden();
    $this->get('/verifikasi-dua-langkah')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/TotpEnrolment', false)
        ->where('auth.capabilities', [])->where('auth.companies', [])->where('auth.name', $owner->name));
    $this->postJson('/konfirmasi-kata-sandi', ['password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $this->postJson('/keluar')->assertNoContent();
});

it('confirms the enrolment with one code: the four fields together, eight codes once, other sessions ended (AU-16, AU-19; criterion 4)', function () {
    Authorization::catalogue();
    $owner = Authorization::owner();
    DB::table('users')->where('id', $owner->id)->update(['email' => 'owner@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')]);
    $this->postJson('/masuk', ['email' => 'owner@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();

    $page = $this->get('/verifikasi-dua-langkah')->assertOk();
    $key = str_replace(' ', '', (string) $page->viewData('page')['flash']['key']);
    expect($key)->toMatch('/^[A-Z2-7]{32}$/')
        ->and(DB::table('users')->where('id', $owner->id)->value('two_factor_secret'))->toBeNull()
        ->and(json_encode(session()->all()))->not->toContain($key)->not->toContain((string) $page->viewData('page')['flash']['key']);

    $this->post('/verifikasi-dua-langkah', ['code' => '000000'])->assertSessionHasErrors(['code' => __('auth.code_mismatch')]);
    $answer = $this->post('/verifikasi-dua-langkah', ['code' => TwoFactor::code($key)])->assertOk();
    $codes = $answer->viewData('page')['flash']['recovery_codes'];

    $row = DB::table('users')->where('id', $owner->id)->first();
    expect($codes)->toHaveCount(8)
        ->and($answer->viewData('page')['props'])->not->toHaveKey('recovery_codes')
        ->and(TwoFactor::storedCodes(User::query()->findOrFail($owner->id)))->toBe($codes)
        ->and($row->two_factor_confirmed_at)->not->toBeNull()
        ->and((int) $row->two_factor_last_step)->toBe(Totp::currentStep())
        ->and(session(TwoFactorChallenge::ENROLMENT_ONLY))->toBeNull()
        ->and(session(TwoFactorChallenge::METHOD))->toBe(EstablishSession::TOTP)
        ->and(DB::table('audit_events')->where('action', 'IAM.TotpEnrolled')->where('actor_user_id', $owner->id)->count())->toBe(1)
        ->and(securityEvents('TOTP_ENROLLED'))->toBe(1);

    // A confirmed secret is never shown again: PG-004 is the enrolment-only session's alone.
    $this->get('/verifikasi-dua-langkah')->assertRedirect('/');
    $this->getJson('/__test/halaman')->assertOk();
});

it('asks for the password again past 15 minutes before confirming, and holds a new secret once the old lapses (AU-15; AU-16; H7-15)', function () {
    Authorization::catalogue();
    $owner = Authorization::owner();
    DB::table('users')->where('id', $owner->id)->update(['email' => 'owner@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')]);
    $this->postJson('/masuk', ['email' => 'owner@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $first = $this->get('/verifikasi-dua-langkah')->viewData('page')['flash']['key'];

    $this->travel(TwoFactorSteps::ENROLMENT_SECONDS + 1)->seconds();
    $second = $this->get('/verifikasi-dua-langkah')->viewData('page')['flash']['key'];
    expect($second)->not->toBe($first);

    $this->post('/verifikasi-dua-langkah', ['code' => TwoFactor::code(str_replace(' ', '', (string) $second))])->assertSessionHasErrors(['password' => __('messages.msg_27')]);
    $this->postJson('/konfirmasi-kata-sandi', ['password' => 'Kata-Sandi-Uji-9'])->assertOk();
    expect(session(RequireStepUp::CONFIRMED_AT))->toBeInt();
    $this->post('/verifikasi-dua-langkah', ['code' => TwoFactor::code(str_replace(' ', '', (string) $second))])->assertOk();
});

it('replaces the device from the recovery-code session alone: old secret and codes destroyed (AU-20 b)', function () {
    signInWithPassword();
    $this->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => TwoFactor::CODES[0]])->assertOk();
    $key = str_replace(' ', '', (string) $this->get('/verifikasi-dua-langkah')->assertInertia(fn ($page) => $page->where('replacing', true))->viewData('page')['flash']['key']);

    $codes = $this->post('/verifikasi-dua-langkah', ['code' => TwoFactor::code($key)])->assertOk()->viewData('page')['flash']['recovery_codes'];

    expect(TwoFactor::storedCodes($this->user))->toBe($codes)->not->toContain(TwoFactor::CODES[1])
        ->and(Crypt::decryptString((string) DB::table('users')->where('id', $this->user->id)->value('two_factor_secret')))->toBe($key)
        ->and(securityEvents('TOTP_REPLACED'))->toBe(1);
});

it('ends a high-risk account\'s session that was not established with TOTP (AU-18; TOTP_REQUIRED)', function () {
    $plain = User::factory()->create(['email' => 'bayu@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
    $this->postJson('/masuk', ['email' => 'bayu@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertExactJson(['signed_in' => true]);
    $this->getJson('/__test/halaman')->assertOk();

    Authorization::catalogue();
    Authorization::grant($plain, 'finance.view');
    Authorization::grantCompany($plain, Company::factory()->create()->id);

    $this->getJson('/__test/halaman')->assertUnauthorized();
    expect(DB::table('security_events')->where('event_kind', 'SESSION_INVALIDATED')->value('reason_code'))->toBe('TOTP_REQUIRED');
});

it('leaves neither the audit event nor the security event, nor the factor, when the confirmation rolls back (CI-09; LG-01; criterion 5)', function () {
    Authorization::catalogue();
    $owner = Authorization::owner();
    DB::table('users')->where('id', $owner->id)->update(['email' => 'owner@contoh.test', 'password' => Hash::make('Kata-Sandi-Uji-9')]);
    $this->postJson('/masuk', ['email' => 'owner@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $key = str_replace(' ', '', (string) $this->get('/verifikasi-dua-langkah')->viewData('page')['flash']['key']);
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "audit_events"')) {
            throw new RuntimeException('the step fails after its audit row');
        }
    });

    expect(fn () => $this->withoutExceptionHandling()->post('/verifikasi-dua-langkah', ['code' => TwoFactor::code($key)]))->toThrow(RuntimeException::class);

    expect(DB::table('audit_events')->where('action', 'IAM.TotpEnrolled')->count())->toBe(0)
        ->and(securityEvents('TOTP_ENROLLED'))->toBe(0)
        ->and(DB::table('users')->where('id', $owner->id)->value('two_factor_secret'))->toBeNull();
});

it('keeps a digest, not the factor state, in the session after the challenge (RV-10)', function () {
    signInWithPassword();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();

    expect(session(EnforceSessionLimits::DIGEST)['user'] ?? null)->toBe($this->user->id)
        ->and(json_encode(session()->all()))->not->toContain(TwoFactor::SECRET);
});

it('counts failed codes alone toward the address\'s throttle, so successes from one address never fill it (AU-05)', function () {
    foreach (range(1, 7) as $round) {
        $this->travel(Totp::PERIOD)->seconds();
        signInWithPassword();
        $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
        $this->postJson('/keluar')->assertNoContent();
    }

    expect(DB::table('security_events')->where('event_kind', 'LOGIN_SUCCEEDED')->count())->toBe(7);
});

it('ends a session established under an enrolment that has since been replaced (AU-06; TOTP_CHANGED)', function () {
    signInWithPassword();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();
    $this->getJson('/__test/halaman')->assertOk();

    // Another session of the account replaces the device: a new confirmation time.
    DB::table('users')->where('id', $this->user->id)->update(['two_factor_confirmed_at' => now()]);
    auth()->forgetGuards();

    $this->getJson('/__test/halaman')->assertUnauthorized();
    expect(DB::table('security_events')->where('event_kind', 'SESSION_INVALIDATED')->value('reason_code'))->toBe('TOTP_CHANGED');
});

it('leaves neither the audit event nor the security event, nor the code\'s removal, when a recovery-code use rolls back (CI-09; LG-01; criterion 5)', function () {
    signInWithPassword();
    DB::listen(function ($query) {
        if (str_contains($query->sql, 'insert into "audit_events"')) {
            throw new RuntimeException('the step fails after its audit row');
        }
    });

    expect(fn () => $this->withoutExceptionHandling()->postJson('/masuk/verifikasi/pemulihan', ['recovery_code' => TwoFactor::CODES[5]]))->toThrow(RuntimeException::class);

    expect(DB::table('audit_events')->where('action', 'IAM.RecoveryCodeUsed')->count())->toBe(0)
        ->and(securityEvents('RECOVERY_CODE_USED'))->toBe(0)
        ->and(TwoFactor::storedCodes($this->user))->toHaveCount(8);
});

it('reads secrets encrypted under a retired key from APP_PREVIOUS_KEYS, and encrypts new ones under the current key (AU-21; SM-21)', function () {
    $old = config('app.key');
    $new = 'base64:'.base64_encode(random_bytes(32));
    config(['app.key' => $new, 'app.previous_keys' => [$old]]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');

    signInWithPassword();
    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertOk();

    // The new enrolment's ciphertext is the current key's: readable without the retired one.
    $fresh = new Encrypter(base64_decode(substr($new, 7)), config('app.cipher'));
    $ciphertext = Crypt::encryptString(TwoFactor::SECRET);
    expect($fresh->decryptString($ciphertext))->toBe(TwoFactor::SECRET);
});
