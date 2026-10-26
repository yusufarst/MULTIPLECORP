<?php

use App\Modules\IAM\Authentication\BreachedPasswordCheck;
use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\Support\RefreshDatabase;

// TL-04, TL-05 and TL-14 (TASK-017; SM-07, SM-16): AU-11–AU-12's credential links — single-use, expiring, revoked on
// reissue, the token only in the request body and never flashed, the credential pages' headers —; AU-03's password
// profile with the local list, the personal-text rule and the breached-password range check skipped and recorded
// when it cannot answer; the identity step's audit event in its own transaction, a rollback leaving neither it nor
// the security event (RG-11; CI-09; LG-01) — acceptance criteria 3, 4 and 8.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    // The range service answers what the test sets, or is unavailable when it is null; nothing reaches the real one.
    $this->range = "0000000000000000000000000000000000A:1\r\n";
    Http::fake([BreachedPasswordCheck::ENDPOINT.'*' => fn () => $this->range === null ? Http::response('', 503) : Http::response($this->range)]);

    $this->owner = User::factory()->create();
    $this->account = User::factory()->create(['name' => 'Dimas Pratama', 'email' => 'dimas@contoh.test']);
    $this->links = app(CredentialLinks::class);
});

afterEach(fn () => @unlink($this->hotFile));

function setPassword(string $token, string $password, ?string $confirmation = null): TestResponse
{
    return test()->post('/atur-kata-sandi', ['token' => $token, 'password' => $password, 'password_confirmation' => $confirmation ?? $password]);
}

it('sets the password once from an onboarding link, ends the account\'s sessions and records both events (AU-11, AU-12; criteria 4, 8)', function () {
    DB::table('sessions')->insert(['id' => 'sesi-lama', 'user_id' => $this->account->id, 'payload' => '', 'last_activity' => time()]);
    $token = $this->links->issue($this->account, 'ONBOARDING', $this->owner->id);

    setPassword($token, 'Rapat-Gudang-24!')->assertRedirect('/masuk')->assertSessionHas('auth.notice', 'password_saved');

    expect(Hash::check('Rapat-Gudang-24!', (string) $this->account->fresh()?->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $this->account->id)->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'IAM.CredentialLinkConsumed')->where('actor_user_id', $this->account->id)->where('authority_class', 'SYSTEM')->count())->toBe(1)
        ->and(DB::table('security_events')->where('event_kind', 'ACCOUNT_SETUP_COMPLETED')->where('user_id', $this->account->id)->count())->toBe(1)
        ->and(auth()->check())->toBeFalse();

    // Used: the same answer as any invalid link, nothing changed.
    setPassword($token, 'Rapat-Gudang-25!')->assertSessionHasErrors(['token' => __('messages.msg_28')]);
    expect(Hash::check('Rapat-Gudang-24!', (string) $this->account->fresh()?->password))->toBeTrue();
});

it('answers an expired, a revoked and an unknown link alike (AU-11; criterion 4)', function () {
    $expired = $this->links->issue($this->account, 'RESET', $this->owner->id);
    $this->travel(61)->minutes();
    setPassword($expired, 'Rapat-Gudang-24!')->assertSessionHasErrors(['token' => __('messages.msg_28')]);
    $this->travelBack();

    $first = $this->links->issue($this->account, 'RESET', $this->owner->id);
    $this->links->issue($this->account, 'RESET', $this->owner->id);
    setPassword($first, 'Rapat-Gudang-24!')->assertSessionHasErrors(['token' => __('messages.msg_28')]);

    setPassword(str_repeat('A', 43), 'Rapat-Gudang-24!')->assertSessionHasErrors(['token' => __('messages.msg_28')]);
    setPassword('pendek', 'Rapat-Gudang-24!')->assertSessionHasErrors(['token' => __('messages.msg_28')]);

    expect(DB::table('security_events')->where('event_kind', 'CREDENTIAL_LINK_REJECTED')->count())->toBe(3);
});

it('keeps the token out of the session, the address and the cache, with no referrer (AU-12; WS-10)', function () {
    $token = $this->links->issue($this->account, 'RESET', $this->owner->id);

    $this->get('/atur-kata-sandi')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');

    setPassword($token, 'pendek')->assertSessionHasErrors('password');
    expect(session()->getOldInput())->not->toHaveKey('token')->not->toHaveKey('password')
        ->and(DB::table('credential_tokens')->where('token_hash', $token)->exists())->toBeFalse()
        ->and(DB::table('credential_tokens')->where('token_hash', CredentialLinks::hash($token))->exists())->toBeTrue();
});

it('refuses a password outside AICWDF-COMPAT-8, on the local list or with personal text (AU-03; criterion 3)', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Company::factory()->create(['legal_name' => 'PT Arjuna Sarana']);
    $token = $this->links->issue($this->account, 'RESET', $this->owner->id);

    foreach (['Pendek1!' => true, 'tanpahurufbesar1!' => false, 'TANPAKECIL1!' => false, 'TanpaAngka!!' => false, 'TanpaSimbol123' => false] as $password => $valid) {
        $answer = setPassword($token, $password);
        $valid ? $answer->assertSessionHasNoErrors() : $answer->assertSessionHasErrors(['password' => __('auth_rules.password_profile')]);

        if ($valid) {
            $token = $this->links->issue($this->account, 'RESET', $this->owner->id);
        }
    }

    setPassword($token, 'Password123!')->assertSessionHasErrors(['password' => __('auth_rules.password_common')]);
    setPassword($token, 'Bismillah2026!')->assertSessionHasErrors(['password' => __('auth_rules.password_common')]);
    setPassword($token, 'Pratama-Gudang-9')->assertSessionHasErrors(['password' => __('auth_rules.password_personal')]);
    setPassword($token, 'Arjuna-Gudang-9!')->assertSessionHasErrors(['password' => __('auth_rules.password_personal')]);
    setPassword($token, 'Multiplecorp-9!')->assertSessionHasErrors(['password' => __('auth_rules.password_personal')]);
    setPassword($token, 'Rapat-Gudang-24!', 'Rapat-Gudang-25!')->assertSessionHasErrors(['password' => __('auth_rules.password_mismatch')]);
    setPassword($token, 'Rapat Gudang 24 é!')->assertSessionHasNoErrors();
});

it('refuses a breached password, sending only the hash prefix, and skips an unreachable check with its record (AU-03; criterion 3)', function () {
    $hash = strtoupper(sha1('Rapat-Gudang-24!'));
    $this->range = substr($hash, 5).":3\r\n";
    $token = $this->links->issue($this->account, 'RESET', $this->owner->id);

    setPassword($token, 'Rapat-Gudang-24!')->assertSessionHasErrors(['password' => __('auth_rules.password_breached')]);
    Http::assertSent(fn ($request) => $request->url() === BreachedPasswordCheck::ENDPOINT.substr($hash, 0, 5) && $request->hasHeader('Add-Padding', 'true'));

    $this->range = null;
    setPassword($token, 'Rapat-Gudang-24!')->assertSessionHasNoErrors();
    expect(DB::table('security_events')->where('event_kind', 'PASSWORD_CHECK_SKIPPED')->count())->toBe(1);
});

it('names the link and no actor for an OWNER_RECOVERY link (RG-11; AU-14)', function () {
    $token = $this->links->issue($this->account, 'OWNER_RECOVERY', null);

    setPassword($token, 'Rapat-Gudang-24!')->assertSessionHasNoErrors();

    $audit = DB::table('audit_events')->where('action', 'IAM.CredentialLinkConsumed')->first();
    expect($audit?->actor_user_id)->toBeNull()
        ->and(json_decode((string) $audit?->metadata, true)['credential_link'] ?? null)->toBe(DB::table('credential_tokens')->value('id'));
});

it('leaves neither the audit event nor the security event, nor the password, when the step rolls back (CI-09; LG-01; criterion 8)', function () {
    $token = $this->links->issue($this->account, 'RESET', $this->owner->id);
    $before = (string) $this->account->fresh()?->password;
    // The step fails just after its audit row is written, inside its transaction.
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'insert into "audit_events"')) {
            throw new RuntimeException('the step fails after its audit row');
        }
    });

    expect(fn () => $this->withoutExceptionHandling()->post('/atur-kata-sandi', ['token' => $token, 'password' => 'Rapat-Gudang-24!', 'password_confirmation' => 'Rapat-Gudang-24!']))->toThrow(RuntimeException::class);

    expect(DB::table('audit_events')->where('action', 'IAM.CredentialLinkConsumed')->count())->toBe(0)
        ->and(DB::table('security_events')->where('event_kind', 'PASSWORD_RESET_COMPLETED')->count())->toBe(0)
        ->and((string) $this->account->fresh()?->password)->toBe($before)
        ->and(DB::table('credential_tokens')->whereNull('used_at')->count())->toBe(1);
});

it('limits a link\'s use per token hash and per address, never by the raw token (AU-12)', function () {
    $token = $this->links->issue($this->account, 'RESET', $this->owner->id);

    foreach (range(1, 5) as $attempt) {
        setPassword($token, 'pendek')->assertSessionHasErrors('password');
    }

    setPassword($token, 'Rapat-Gudang-24!')->assertStatus(429);
    expect(Hash::check('Rapat-Gudang-24!', (string) $this->account->fresh()?->password))->toBeFalse();
});
