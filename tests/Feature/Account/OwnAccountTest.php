<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\AccountEvents;
use App\Modules\IAM\Authentication\GoogleSignIn;
use App\Modules\IAM\Authentication\TwoFactorSteps;
use App\Modules\IAM\Models\User;
use App\Support\Localization\Locales;
use App\Support\SecurityLog\SecurityEventKind;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 (TASK-023; ROUTE-065, ROUTE-111–ROUTE-118; SECURITY AU-13, AU-15, AU-16, AU-19, AU-20 a and f, AU-23, AU-24;
// criteria 1–8 and 10): PG-065 on the account's own row with no secret in its props; the password change with its
// current password, AU-03's profile, the other sessions ended and its limit; every sensitive step behind the step-up;
// TOTP's enrolment and replacement, the recovery codes viewed and regenerated, TOTP off only when not high-risk, the
// Google link from a verified identity and the own unlink, the display language — each with its audit event, the
// account the actor, and its security event.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->admin = TwoFactor::enrol(Authorization::admin());
    $this->admin->forceFill(['password' => Hash::make('kata-sandi-lama-123')])->save();
});

afterEach(fn () => @unlink($this->hotFile));

function answered(): array
{
    $answer = session('inertia.flash_data')['answer'] ?? null;

    return is_array($answer) ? $answer : [];
}

function flashedOf(string $key): mixed
{
    return session('inertia.flash_data')[$key] ?? null;
}

function confirmed(): array
{
    return [RequireStepUp::CONFIRMED_AT => time()];
}

function auditOf(string $action): ?object
{
    return DB::table('audit_events')->where('action', $action)->orderByDesc('id')->first();
}

it('shows the account its own profile, factor, link and activity, holding no secret (ROUTE-065; PJ-17; criterion 1)', function () {
    DB::table('security_events')->insert(['event_kind' => SecurityEventKind::PasswordChanged->value, 'user_id' => $this->admin->id, 'occurred_at' => now()]);

    $this->actingAs($this->admin)->get('/akun')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('IAM/OwnAccount', false)
        ->where('account.email', $this->admin->email)
        ->where('totp.active', true)
        ->where('totp.high_risk', false)
        ->where('google.linked', false)
        ->where('google.email', null)
        ->where('events.0.kind', 'PASSWORD_CHANGED'));

    $content = $this->actingAs($this->admin)->get('/akun')->getContent();
    expect($content)->not->toContain((string) DB::table('users')->where('id', $this->admin->id)->value('two_factor_secret'))
        ->and($content)->not->toContain(TwoFactor::SECRET);

    auth()->logout();
    $this->get('/akun')->assertRedirect('/masuk');
});

it('changes the password with the current one and AU-03\'s profile, ending the other sessions and voiding the links (ROUTE-111; RV-10; criterion 2)', function () {
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('credential_tokens')->insert(['user_id' => $this->admin->id, 'purpose' => 'RESET', 'issued_by' => $this->admin->id, 'token_hash' => str_repeat('a', 64), 'issued_at' => now(), 'expires_at' => now()->addHour()]);

    $this->actingAs($this->admin)->put('/akun/kata-sandi', ['current_password' => 'salah-sekali-123', 'password' => 'Pelangi-Biru#8492', 'password_confirmation' => 'Pelangi-Biru#8492'])
        ->assertSessionHasErrors('current_password');
    $this->actingAs($this->admin)->put('/akun/kata-sandi', ['current_password' => 'kata-sandi-lama-123', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
        ->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->put('/akun/kata-sandi', ['current_password' => 'kata-sandi-lama-123', 'password' => 'Pelangi-Biru#8492', 'password_confirmation' => 'Pelangi-Biru#8492'])
        ->assertRedirect('/akun');

    expect(Hash::check('Pelangi-Biru#8492', (string) User::query()->findOrFail($this->admin->id)->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->count())->toBe(0)
        ->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(answered()['kind'] ?? null)->toBe('COMMITTED')
        ->and(auditOf('IAM.PasswordChanged')?->actor_user_id)->toBe($this->admin->id)
        ->and((string) auditOf('IAM.PasswordChanged')?->before.(string) auditOf('IAM.PasswordChanged')?->after)->not->toContain('$argon')
        ->and(DB::table('security_events')->where('event_kind', 'PASSWORD_CHANGED')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('limits the password change to 5 per 15 minutes, answering MSG-20 at the field (WS-12; criterion 2)', function () {
    foreach (range(1, 5) as $attempt) {
        $this->actingAs($this->admin)->put('/akun/kata-sandi', ['current_password' => 'salah-sekali-123', 'password' => 'Pelangi-Biru#8492', 'password_confirmation' => 'Pelangi-Biru#8492']);
    }

    $this->actingAs($this->admin)->put('/akun/kata-sandi', ['current_password' => 'kata-sandi-lama-123', 'password' => 'Pelangi-Biru#8492', 'password_confirmation' => 'Pelangi-Biru#8492'])
        ->assertSessionHasErrors(['current_password' => trans_choice('messages.msg_20', 15, ['count' => 15])]);
});

it('asks the step-up before every sensitive step once 15 minutes have passed (AU-15; criterion 3)', function (string $method, string $url) {
    $this->actingAs($this->admin)->withSession([RequireStepUp::CONFIRMED_AT => time() - 901])->json($method, $url, ['code' => '123456'])
        ->assertStatus(423)->assertJsonPath('message', __('messages.msg_27'));
})->with([
    'enrolment' => ['POST', '/akun/verifikasi-dua-langkah'],
    'view codes' => ['POST', '/akun/kode-pemulihan'],
    'regenerate codes' => ['POST', '/akun/kode-pemulihan/baru'],
    'turn off' => ['DELETE', '/akun/verifikasi-dua-langkah'],
    'link Google' => ['POST', '/akun/google'],
    'unlink Google' => ['DELETE', '/akun/google'],
]);

it('enrols TOTP with its QR code shown once, then the codes shown once (ROUTE-112; AU-16; AU-19; criterion 4)', function () {
    $fresh = Authorization::admin();
    $this->actingAs($fresh)->withSession(confirmed())->post('/akun/verifikasi-dua-langkah')->assertRedirect('/akun');
    $enrolment = flashedOf('enrolment');
    $secret = Crypt::decryptString(session(TwoFactorSteps::ENROLMENT)['secret']);

    expect($enrolment['qr'] ?? null)->toBeArray()->and($enrolment['key'] ?? null)->toBeString();

    $this->actingAs($fresh)->post('/akun/verifikasi-dua-langkah', ['code' => TwoFactor::code($secret)])->assertRedirect('/akun');

    expect(flashedOf('recovery_codes'))->toHaveCount(8)
        ->and(User::query()->findOrFail($fresh->id)->two_factor_confirmed_at)->not->toBeNull()
        ->and(auditOf('IAM.TotpEnrolled')?->actor_user_id)->toBe($fresh->id);
});

it('replaces the device only after a current code of the device being replaced (AU-20 a)', function () {
    $this->actingAs($this->admin)->withSession(confirmed())->post('/akun/verifikasi-dua-langkah', ['current_code' => '000000'])->assertSessionHasErrors('current_code');
    $this->actingAs($this->admin)->post('/akun/verifikasi-dua-langkah', ['current_code' => TwoFactor::code()])->assertRedirect('/akun');
    $secret = Crypt::decryptString(session(TwoFactorSteps::ENROLMENT)['secret']);

    $this->actingAs($this->admin)->post('/akun/verifikasi-dua-langkah', ['code' => TwoFactor::code($secret)])->assertRedirect('/akun');

    expect(auditOf('IAM.TotpReplaced')?->actor_user_id)->toBe($this->admin->id)
        ->and(flashedOf('recovery_codes'))->toHaveCount(8);
});

it('shows the recovery codes once per code, and regenerates them voiding the old set (ROUTE-113; ROUTE-114; criterion 4)', function () {
    $old = TwoFactor::storedCodes($this->admin);

    $this->actingAs($this->admin)->withSession(confirmed())->post('/akun/kode-pemulihan', ['code' => TwoFactor::code()])->assertRedirect('/akun');
    expect(flashedOf('recovery_codes'))->toBe($old);

    // The same code again: accepted once only (M-39).
    $this->actingAs($this->admin)->post('/akun/kode-pemulihan', ['code' => TwoFactor::code()])->assertSessionHasErrors('code');

    $this->actingAs($this->admin)->post('/akun/kode-pemulihan/baru', ['code' => TwoFactor::code(offset: 1)])->assertRedirect('/akun');
    $new = flashedOf('recovery_codes');

    expect($new)->toHaveCount(8)->and($new)->not->toBe($old)
        ->and(TwoFactor::storedCodes(User::query()->findOrFail($this->admin->id)))->toBe($new)
        ->and(auditOf('IAM.RecoveryCodesRegenerated')?->actor_user_id)->toBe($this->admin->id)
        ->and(DB::table('security_events')->where('event_kind', 'RECOVERY_CODES_VIEWED')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

it('refuses TOTP off for a high-risk account, and turns it off for another, unlinking Google (ROUTE-115; AU-20 f; criterion 5)', function () {
    $owner = TwoFactor::enrol(Authorization::owner());
    $this->actingAs($owner)->withSession(confirmed())->delete('/akun/verifikasi-dua-langkah', ['code' => TwoFactor::code()])->assertRedirect('/akun');
    expect(answered()['text'] ?? null)->toBe(__('account.totp.high_risk'))
        ->and(User::query()->findOrFail($owner->id)->two_factor_confirmed_at)->not->toBeNull();

    DB::table('user_external_identities')->insert(['user_id' => $this->admin->id, 'provider' => 'google', 'provider_subject' => 'sub-admin', 'email_at_link' => 'admin@contoh.invalid', 'linked_at' => now(), 'linked_by' => $this->admin->id]);
    DB::table('credential_tokens')->insert(['user_id' => $this->admin->id, 'purpose' => 'RESET', 'issued_by' => $this->admin->id, 'token_hash' => str_repeat('b', 64), 'issued_at' => now(), 'expires_at' => now()->addHour()]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($this->admin)->withSession(confirmed())->delete('/akun/verifikasi-dua-langkah', ['code' => TwoFactor::code()])->assertRedirect('/akun');
    $row = DB::table('users')->where('id', $this->admin->id)->first(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);

    expect(array_filter((array) $row, fn ($value) => $value !== null))->toBe([])
        ->and(DB::table('user_external_identities')->where('user_id', $this->admin->id)->value('unlink_reason'))->toBe('TOTP_DISABLED')
        ->and(auditOf('IAM.TotpDisabled')?->actor_user_id)->toBe($this->admin->id)
        // AU-20 f; RV-14: the links voided, the other sessions ended, the step-up's confirmation cleared.
        ->and(DB::table('credential_tokens')->where('user_id', $this->admin->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->count())->toBe(0)
        ->and(session(RequireStepUp::CONFIRMED_AT))->toBeNull();
});

it('links the verified Google identity on the holder\'s confirming request, and refuses a conflict generically (ROUTE-116; AU-23; criterion 6)', function () {
    $pending = fn (string $sub): array => [...confirmed(), GoogleSignIn::LINK => ['user' => $this->admin->id, 'sub' => $sub, 'email' => 'saya@contoh.invalid', 'expires_at' => time() + 600]];

    $this->actingAs($this->admin)->withSession(confirmed())->post('/akun/google', ['code' => TwoFactor::code()])->assertRedirect('/akun');
    expect(answered()['text'] ?? null)->toBe(__('account.google.no_attempt'));

    $other = TwoFactor::enrol(Authorization::admin());
    DB::table('user_external_identities')->insert(['user_id' => $other->id, 'provider' => 'google', 'provider_subject' => 'sub-diambil', 'email_at_link' => 'lain@contoh.invalid', 'linked_at' => now(), 'linked_by' => $other->id]);
    $this->actingAs($this->admin)->withSession($pending('sub-diambil'))->post('/akun/google', ['code' => TwoFactor::code()])->assertRedirect('/akun');
    expect(answered()['text'] ?? null)->toBe(__('account.google.refused'))
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_LINK_CONFLICT')->exists())->toBeTrue();

    $this->actingAs($this->admin)->withSession($pending('sub-saya'))->post('/akun/google', ['code' => TwoFactor::code(offset: 1)])->assertRedirect('/akun');
    expect(answered()['kind'] ?? null)->toBe('COMMITTED')
        ->and(DB::table('user_external_identities')->where('user_id', $this->admin->id)->whereNull('unlinked_at')->value('provider_subject'))->toBe('sub-saya')
        ->and(session(GoogleSignIn::LINK))->toBeNull();
});

it('unlinks the holder\'s own Google identity as SELF, ending its other sessions (ROUTE-117; AU-24; criterion 7)', function () {
    DB::table('user_external_identities')->insert(['user_id' => $this->admin->id, 'provider' => 'google', 'provider_subject' => 'sub-admin', 'email_at_link' => 'admin@contoh.invalid', 'linked_at' => now(), 'linked_by' => $this->admin->id]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->admin)->withSession(confirmed())->delete('/akun/google')->assertRedirect('/akun');
    $link = DB::table('user_external_identities')->where('user_id', $this->admin->id)->first(['unlink_reason', 'unlinked_by']);

    expect($link?->unlink_reason)->toBe('SELF')->and($link?->unlinked_by)->toBe($this->admin->id)
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->count())->toBe(0);
});

it('keeps the display language in users.locale, applies it at once and refuses an unknown one (ROUTE-118; RB-10; criterion 8)', function () {
    $other = Authorization::admin();

    $this->actingAs($this->admin)->from('/akun')->put('/akun/bahasa', ['locale' => 'en'])->assertRedirect('/akun');
    expect(User::query()->findOrFail($this->admin->id)->locale)->toBe('en')->and(session(Locales::SESSION_KEY))->toBe('en')
        ->and(User::query()->findOrFail($other->id)->locale)->toBeNull()
        ->and(auditOf('IAM.LocaleChanged')?->actor_user_id)->toBe($this->admin->id);

    $this->actingAs($this->admin)->from('/akun')->put('/akun/bahasa', ['locale' => 'default'])->assertRedirect('/akun');
    expect(User::query()->findOrFail($this->admin->id)->locale)->toBeNull();

    $this->actingAs($this->admin)->from('/akun')->put('/akun/bahasa', ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

it('names the newest account event since the previous sign-in for MSG-29, once (AU-13; H7-18; criterion 9)', function () {
    $events = app(AccountEvents::class);
    DB::table('security_events')->insert(['event_kind' => 'LOGIN_SUCCEEDED', 'user_id' => $this->admin->id, 'occurred_at' => now()->subHours(3)]);
    DB::table('security_events')->insert(['event_kind' => 'TOTP_RESET', 'user_id' => $this->admin->id, 'actor_user_id' => Authorization::owner()->id, 'occurred_at' => now()->subHour()]);

    expect($events->noticeSince($this->admin)['kind'] ?? null)->toBe('TOTP_RESET')
        ->and($events->noticeSince($this->admin)['by'] ?? null)->toBeString();

    DB::table('security_events')->insert(['event_kind' => 'LOGIN_SUCCEEDED', 'user_id' => $this->admin->id, 'occurred_at' => now()]);
    expect($events->noticeSince($this->admin))->toBeNull();
});
