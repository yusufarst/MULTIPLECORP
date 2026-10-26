<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Authentication\EnforceSessionLimits;
use App\Modules\IAM\Authentication\LoginThrottle;
use App\Modules\IAM\Authentication\VerificationRefused;
use App\Modules\IAM\Authentication\VerificationSlots;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Hashing\HashManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\HashProbe;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-017; SM-14; HO-17; M-25): the races of sign-in and credential links — AU-05's counts exact under a
// burst, each attempt's place taken with one atomic increment before its password is verified (H6-07); H6-12's
// two funnels, a full unauthenticated one answering the throttled answer within its wait while the authenticated
// slot stays free, and an unavailable store refusing (fail closed); a login racing a password change with a rehash
// due, RV-10's conditional update never writing an old password's hash over the newer one; a link's consumption
// locking the account before the link (D-CC-03) and two submissions of one link setting one password (H6-11).

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->user = User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
});

/** A request from one client address, as the limiter keys it. */
function fromAddress(string $address): Request
{
    return Request::create('/masuk', 'POST', server: ['REMOTE_ADDR' => $address]);
}

it('lets a burst of parallel attempts through AU-05\'s counts no more than five, each place taken before verification (H6-07; SM-14)', function () {
    $throttle = app(LoginThrottle::class);
    $request = fromAddress('10.0.0.7');

    // Seven attempts in flight at once, none answered yet.
    $answers = collect(range(1, 7))->map(fn () => $throttle->reserve($request, 'laras@contoh.test'));
    expect($answers->all())->toBe([0, 0, 0, 0, 0, 60, 60]);

    // The five fail: the cooldown starts once, with one strike, however many of them see the fifth.
    $cooldowns = collect(range(1, 5))->map(fn () => $throttle->failed($request, 'laras@contoh.test'));
    expect($cooldowns->filter()->values()->all())->toBe([60])
        ->and($throttle->reserve($request, 'laras@contoh.test'))->toBeGreaterThan(0)
        ->and($throttle->reserve(fromAddress('10.0.0.8'), 'laras@contoh.test'))->toBe(0);
});

it('gives a place back for a success and for an attempt that verified nothing, so only failures fill the address\'s count (AU-05)', function () {
    $throttle = app(LoginThrottle::class);
    $request = fromAddress('10.0.0.9');

    foreach (range(1, LoginThrottle::FAILURES_PER_ADDRESS + 5) as $attempt) {
        expect($throttle->reserve($request, "akun-{$attempt}@contoh.test"))->toBe(0);
        $attempt % 2 === 0 ? $throttle->succeeded($request, "akun-{$attempt}@contoh.test") : $throttle->release($request, "akun-{$attempt}@contoh.test");
    }

    expect($throttle->reserve($request, 'lain@contoh.test'))->toBe(0)
        ->and(LoginThrottle::addressKey('::ffff:10.0.0.9'))->toBe('10.0.0.9');
});

it('answers the throttled answer within the wait when the unauthenticated slots are full, the step-up\'s slot still free (H6-12; M-25)', function () {
    $store = Cache::store('array')->getStore();
    $held = collect(range(1, VerificationSlots::UNAUTHENTICATED_SLOTS))
        ->map(fn (int $slot) => tap($store->lock("verification-slot:unauthenticated:{$slot}", 5, 'banjir'))->get());

    $started = hrtime(true);
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])
        ->assertStatus(429)->assertHeader('Retry-After', '1');
    $waitedMs = (hrtime(true) - $started) / 1_000_000;

    expect($waitedMs)->toBeGreaterThanOrEqual(VerificationSlots::UNAUTHENTICATED_WAIT_MS)->toBeLessThan(VerificationSlots::UNAUTHENTICATED_WAIT_MS + 1500)
        ->and(auth()->check())->toBeFalse()
        ->and(DB::table('security_events')->where('event_kind', 'THROTTLED')->where('reason_code', 'VERIFICATION_CAP')->count())->toBe(1);

    // The refused attempt gave its places back: the five failures of AU-05 are still there to count.
    expect(app(LoginThrottle::class)->reserve(Request::create('/masuk', 'POST'), 'laras@contoh.test'))->toBe(0);

    $this->actingAs($this->user)->postJson('/konfirmasi-kata-sandi', ['password' => 'Kata-Sandi-Uji-9'])->assertOk();
    expect(session(RequireStepUp::CONFIRMED_AT))->toBeInt();

    $held->each(fn ($lock) => $lock->forceRelease());
});

it('refuses every verification when the cap\'s store cannot be reached — fail closed (H6-12; H6-07)', function () {
    config(['cache.default' => 'redis', 'database.redis.cache.port' => 1, 'database.redis.default.port' => 1]);
    app('cache')->forgetDriver('redis');
    app('redis')->purge('cache');
    app('redis')->purge('default');

    $slots = app(VerificationSlots::class);

    expect(fn () => $slots->unauthenticated('login.store', fn () => true))->toThrow(VerificationRefused::class)
        ->and(fn () => $slots->authenticated('password.confirm', fn () => true))->toThrow(VerificationRefused::class);
});

it('never writes an old password\'s rehash over a password changed while the login was verifying (RV-10; SM-14)', function () {
    config(['hashing.argon.memory' => 1024]);
    $stale = Hash::make('Kata-Sandi-Uji-9', ['memory' => 512, 'time' => 1]);
    DB::table('users')->where('id', $this->user->id)->update(['password' => $stale]);
    $newer = Hash::make('Rapat-Gudang-24!');

    // The reset commits between the login's verification and its rehash update.
    HashProbe::install(make: function (string $value, array $options, HashManager $hasher) use ($newer) {
        DB::table('users')->where('id', $this->user->id)->update(['password' => $newer]);

        return $hasher->make($value, $options);
    });

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])
        ->assertStatus(422)->assertJsonValidationErrors(['email' => __('messages.msg_19')]);

    expect(DB::table('users')->where('id', $this->user->id)->value('password'))->toBe($newer)
        ->and(auth()->check())->toBeFalse();
});

it('stamps the session with the digest of the hash its rehash wrote, never one read afterwards (RV-10)', function () {
    config(['hashing.argon.memory' => 1024]);
    DB::table('users')->where('id', $this->user->id)->update(['password' => Hash::make('Kata-Sandi-Uji-9', ['memory' => 512, 'time' => 1])]);

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();

    $written = User::query()->findOrFail($this->user->id);
    expect(session(EnforceSessionLimits::DIGEST))->toBe(['user' => $this->user->id, 'digest' => EnforceSessionLimits::digest($written)])
        ->and(Hash::needsRehash($written->password))->toBeFalse();
});

it('locks the account before the link and sets one password from two submissions of one link (D-CC-03; H6-11; SM-07)', function () {
    $owner = User::factory()->create();
    $links = app(CredentialLinks::class);
    $token = $links->issue($this->user, 'RESET', $owner->id);
    $locks = [];
    DB::listen(function (QueryExecuted $query) use (&$locks) {
        if (str_contains($query->sql, 'for update') || str_contains($query->sql, 'update "credential_tokens" set "used_at"')) {
            $locks[] = str_contains($query->sql, '"users"') ? 'users' : 'credential_tokens';
        }
    });

    // The second submission completes while the first computes its hash, outside any transaction.
    $second = null;
    HashProbe::install(make: function (string $value, array $options, HashManager $hasher) use ($links, $token, &$second) {
        if ($second === null) {
            $second = false;
            $second = $links->consume($token, 'Rapat-Gudang-25!', '10.0.0.1', null);
        }

        return $hasher->make($value, $options);
    });

    $first = $links->consume($token, 'Rapat-Gudang-24!', '10.0.0.1', null);

    expect($second)->toBeInstanceOf(User::class)
        ->and($first)->toBeNull()
        ->and(Hash::check('Rapat-Gudang-25!', (string) DB::table('users')->where('id', $this->user->id)->value('password')))->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'IAM.CredentialLinkConsumed')->count())->toBe(1)
        ->and(array_slice($locks, 0, 2))->toBe(['users', 'credential_tokens']);
});
