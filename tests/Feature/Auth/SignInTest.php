<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireStepUp;
use App\Http\Middleware\SessionDeadline;
use App\Modules\IAM\Authentication\AttemptLogin;
use App\Modules\IAM\Authentication\EnforceSessionLimits;
use App\Modules\IAM\Authentication\LoginThrottle;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Tests\Support\HashProbe;
use Tests\Support\RefreshDatabase;

// TL-04 and TL-14 (TASK-017; SM-06–SM-08): ROUTE-101's sign-in without enumeration (AU-04) and its throttling
// without lockout (AU-05), the session it establishes (AU-06, AU-07), ROUTE-108's logout, ROUTE-109's password
// confirmation (AU-15) and the session's end at deactivation and at the absolute limit (AU-09) — acceptance
// criteria 1, 2 and 5.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    $this->user = User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
    Route::middleware(['web', 'auth', 'access:s0'])->get('/__test/halaman', fn () => response()->json(['user' => request()->user()?->getAuthIdentifier()]));
});

afterEach(fn () => @unlink($this->hotFile));

function events(string $kind): int
{
    return DB::table('security_events')->where('event_kind', $kind)->count();
}

it('answers a wrong password, an unknown account and an inactive one alike, recording no identifier (AU-04; criterion 1)', function () {
    User::factory()->deactivated()->create(['email' => 'bayu@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);

    $answers = collect([
        ['laras@contoh.test', 'salah-sekali'],
        ['tidak-ada@contoh.test', 'Kata-Sandi-Uji-9'],
        ['bayu@contoh.test', 'Kata-Sandi-Uji-9'],
    ])->map(fn (array $attempt) => $this->postJson('/masuk', ['email' => $attempt[0], 'password' => $attempt[1]]));

    foreach ($answers as $answer) {
        $answer->assertStatus(422)->assertExactJson(['message' => __('messages.msg_19'), 'errors' => ['email' => [__('messages.msg_19')]]]);
    }

    expect(events('LOGIN_FAILED'))->toBe(3)
        ->and(DB::table('security_events')->pluck('identifier_hash')->filter(fn ($hash) => str_contains((string) $hash, '@')))->toBeEmpty()
        ->and(auth()->check())->toBeFalse();
});

it('does the same hashing work for every failed case — one verification, no hash made, a hash of another algorithm included (AU-04; H9-11; criterion 1)', function () {
    User::factory()->deactivated()->create(['email' => 'bayu@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
    $old = User::factory()->create(['email' => 'lama@contoh.test']);
    DB::table('users')->where('id', $old->id)->update(['password' => password_hash('Kata-Sandi-Uji-9', PASSWORD_BCRYPT)]);
    $work = [];
    // As the framework's verifier does, a hash of another algorithm is refused with an exception.
    HashProbe::install(make: fn () => throw new LogicException('A failed sign-in makes no hash.'), check: function (string $value, ?string $hash) use (&$work) {
        $algorithm = password_get_info((string) $hash)['algoName'];
        $work[] = $algorithm;

        if ($algorithm !== 'argon2id') {
            throw new RuntimeException('This password does not use the Argon2id algorithm.');
        }

        return password_verify($value, (string) $hash);
    });

    foreach (['laras@contoh.test' => 'salah-sekali', 'tidak-ada@contoh.test' => 'Kata-Sandi-Uji-9', 'bayu@contoh.test' => 'Kata-Sandi-Uji-9'] as $email => $password) {
        $work = [];
        $this->postJson('/masuk', ['email' => $email, 'password' => $password])->assertStatus(422);
        expect($work)->toBe(['argon2id']);
    }

    // The dummy verification still runs after the refused hash.
    $work = [];
    $this->postJson('/masuk', ['email' => 'lama@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertStatus(422);
    expect($work)->toBe(['bcrypt', 'argon2id']);
});

it('generates the dummy hash from the configured driver and parameters, with no hashing of its own (AU-04; H9-11)', function () {
    config(['hashing.argon.memory' => 19456, 'hashing.argon.time' => 2, 'hashing.argon.threads' => 1]);

    $dummy = AttemptLogin::dummyHash();
    expect(password_get_info($dummy))->toBe(['algo' => PASSWORD_ARGON2ID, 'algoName' => 'argon2id', 'options' => ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]])
        ->and(password_verify('apa saja', $dummy))->toBeFalse()
        ->and(AttemptLogin::dummyHash())->not->toBe($dummy);

    config(['hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 12]);
    expect(password_get_info(AttemptLogin::dummyHash()))->toMatchArray(['algoName' => 'bcrypt', 'options' => ['cost' => 12]]);
});

it('bounds a signed-in session that has no sign-in time by the absolute limit from its next request (AU-07)', function () {
    $this->actingAs($this->user)->getJson('/__test/halaman')->assertOk();
    expect(session(SessionDeadline::SIGNED_IN_AT))->toBeInt();

    $this->travel(SessionDeadline::ABSOLUTE_HOURS * 60 + 1)->minutes();
    $this->getJson('/__test/halaman')->assertStatus(401);
});

it('signs in with the normalized e-mail, a new session identifier, the sign-in time and no remembering (AU-01, AU-06, AU-08)', function () {
    $this->get('/masuk')->assertOk();
    $before = session()->getId();

    $this->postJson('/masuk', ['email' => '  LARAS@Contoh.test ', 'password' => 'Kata-Sandi-Uji-9', 'remember' => 'on'])->assertOk()->assertJson(['signed_in' => true]);

    expect(session()->getId())->not->toBe($before)
        ->and(session(SessionDeadline::SIGNED_IN_AT))->toBeGreaterThanOrEqual(now()->getTimestamp() - 5)->toBeLessThanOrEqual(now()->getTimestamp())
        ->and(session(EnforceSessionLimits::DIGEST)['user'] ?? null)->toBe($this->user->id)
        ->and(events('LOGIN_SUCCEEDED'))->toBe(1)
        ->and(auth()->id())->toBe($this->user->id)
        ->and(auth()->viaRemember())->toBeFalse();
});

it('loads the next page as a full document after the sign-in page\'s request (AU-06; H7-12)', function () {
    $version = (string) app(HandleInertiaRequests::class)->version(request());

    $this->post('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'], ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/'));
});

it('throttles five failures a minute per identifier and address, without locking the account (AU-05; criterion 2)', function () {
    foreach (range(1, LoginThrottle::FAILURES_PER_MINUTE) as $attempt) {
        $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'salah'])->assertStatus(422);
    }

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])
        ->assertStatus(429)
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', trans_choice('messages.msg_20', 1, ['count' => 1]));

    // Another address is not held back by this key, and the account itself was never locked.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();

    expect(events('THROTTLED'))->toBe(1)
        ->and($this->user->fresh()?->is_active)->toBeTrue();
});

it('doubles the cooldown after a second strike, up to fifteen minutes (AU-05)', function () {
    $fail = fn () => $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'salah']);

    foreach (range(1, 5) as $attempt) {
        $fail();
    }

    $this->travel(61)->seconds();

    foreach (range(1, 5) as $attempt) {
        $fail();
    }

    $fail()->assertStatus(429)->assertHeader('Retry-After', '120');
});

it('holds back an address after thirty failures in fifteen minutes, whatever the identifier (AU-05)', function () {
    foreach (range(1, LoginThrottle::FAILURES_PER_ADDRESS) as $attempt) {
        $this->postJson('/masuk', ['email' => "orang{$attempt}@contoh.test", 'password' => 'salah']);
    }

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertStatus(429);
});

it('logs out: the session ended, its forgery token renewed and LOGOUT recorded (ROUTE-108; AU-06)', function () {
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $token = session()->token();

    $this->postJson('/keluar')->assertNoContent();

    expect(auth()->check())->toBeFalse()
        ->and(session()->token())->not->toBe($token)
        ->and(events('LOGOUT'))->toBe(1);
    $this->getJson('/__test/halaman')->assertUnauthorized();
});

it('ends the session at the absolute limit and when the account is deactivated (AU-07, AU-09; criterion 5)', function () {
    $fresh = User::query()->findOrFail($this->user->id);
    $signedIn = now()->getTimestamp() - SessionDeadline::ABSOLUTE_HOURS * 3600;

    $this->actingAs($fresh)->withSession([SessionDeadline::SIGNED_IN_AT => $signedIn + 60])->getJson('/__test/halaman')->assertOk();
    $this->actingAs($fresh)->withSession([SessionDeadline::SIGNED_IN_AT => $signedIn])->getJson('/__test/halaman')->assertUnauthorized();

    DB::table('users')->where('id', $this->user->id)->update(['is_active' => false, 'deactivated_at' => now()]);
    $this->actingAs(User::query()->findOrFail($this->user->id))->getJson('/__test/halaman')->assertUnauthorized();

    expect(DB::table('security_events')->where('event_kind', 'SESSION_INVALIDATED')->pluck('reason_code')->all())->toBe(['ABSOLUTE_LIMIT', 'INACTIVE_ACCOUNT']);
});

it('ends a session whose account\'s password changed elsewhere (RV-10)', function () {
    $fresh = User::query()->findOrFail($this->user->id);
    $digest = [EnforceSessionLimits::DIGEST => ['user' => $fresh->id, 'digest' => EnforceSessionLimits::digest($fresh)]];
    $this->actingAs($fresh)->withSession($digest)->getJson('/__test/halaman')->assertOk();

    $fresh->forceFill(['password' => 'Kata-Sandi-Baru-7'])->save();
    $this->actingAs($fresh)->withSession($digest)->getJson('/__test/halaman')->assertUnauthorized();
});

it('confirms the password for the step-up, a wrong one answered, throttled and recorded (ROUTE-109; AU-15)', function () {
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();

    $this->postJson('/konfirmasi-kata-sandi', ['password' => 'salah'])->assertStatus(422)->assertJsonPath('message', __('messages.msg_19'));
    expect(session(RequireStepUp::CONFIRMED_AT))->toBeNull()->and(events('PASSWORD_CONFIRMATION_FAILED'))->toBe(1);

    $this->postJson('/konfirmasi-kata-sandi', ['password' => 'Kata-Sandi-Uji-9'])->assertOk();
    expect(session(RequireStepUp::CONFIRMED_AT))->toBeGreaterThanOrEqual(time() - 5);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/konfirmasi-kata-sandi', ['password' => 'salah']);
    }

    $this->postJson('/konfirmasi-kata-sandi', ['password' => 'Kata-Sandi-Uji-9'])->assertStatus(429);
});

it('shows the sign-in page to a guest and sends a signed-in account to its landing page (NAV-001)', function () {
    $this->get('/masuk')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/SignIn', false)->where('notice', null));

    $this->actingAs($this->user)->get('/masuk')->assertRedirect('/');
});
