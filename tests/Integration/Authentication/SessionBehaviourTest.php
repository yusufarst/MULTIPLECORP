<?php

use App\Http\BackgroundRequest;
use App\Modules\IAM\Authentication\LoginThrottle;
use App\Modules\IAM\Authentication\SessionTerminator;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-017; HO-20; TEST_STRATEGY §6; acceptance criterion 9): the framework behaviours the sessions rely on,
// pinned so that a changed version fails here — the `database` session store in PostgreSQL keeping one row per
// session with its account, so an account's sessions end by deleting its rows (AU-06, AU-09); the session variant
// that skips the write of a background request (RV-06); how a rehash at login is written (AU-02; RV-10) — and the
// sign-in limiter on Valkey (AU-05; WS-12). Deactivation's use of the deletion is TASK-024's to test.

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->user = User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);
    Route::middleware(['web', 'auth', 'access:s0'])->get('/__test/halaman', fn () => 'halaman');
});

/** The session cookie a response sets, decrypted to the session identifier the store keeps. */
function sessionIdOf(TestResponse $response): string
{
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    return (string) $cookie?->getValue();
}

it('keeps each session as a PostgreSQL row with its account, ended by deleting the account\'s rows (AU-06, AU-09; HO-20)', function () {
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();

    $rows = DB::table('sessions')->where('user_id', $this->user->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->payload)->not->toContain('laras@contoh.test');

    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);
    $kept = (string) $rows->first()->id;

    expect(app(SessionTerminator::class)->endAll($this->user->id, $kept))->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->pluck('id')->all())->toBe([$kept])
        ->and(app(SessionTerminator::class)->endAll($this->user->id))->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0);
});

it('writes nothing to the session row for a background request (RV-06; HO-20)', function () {
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $id = (string) DB::table('sessions')->value('id');
    DB::table('sessions')->update(['last_activity' => 1_000]);
    $cookie = [(string) config('session.cookie') => $id];

    $this->withCookies($cookie)->get('/__test/halaman', [BackgroundRequest::HEADER => '1']);
    expect(DB::table('sessions')->where('id', $id)->value('last_activity'))->toBe(1_000);

    $this->withCookies($cookie)->get('/__test/halaman');
    expect(DB::table('sessions')->where('id', $id)->value('last_activity'))->toBeGreaterThan(1_000);
});

it('rehashes a hash below the current parameters at login, as an update of the account\'s password (AU-02; RV-10; HO-20)', function () {
    config(['hashing.argon.memory' => 1024]);
    $old = Hash::make('Kata-Sandi-Uji-9', ['memory' => 1024, 'time' => 1]);
    DB::table('users')->where('id', $this->user->id)->update(['password' => $old]);
    config(['hashing.argon.memory' => 2048]);
    Hash::forgetDrivers();

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();

    $new = (string) DB::table('users')->where('id', $this->user->id)->value('password');
    expect($new)->not->toBe($old)
        ->and(Hash::needsRehash($new))->toBeFalse()
        ->and(Hash::check('Kata-Sandi-Uji-9', $new))->toBeTrue()
        ->and(password_get_info($new)['algoName'])->toBe('argon2id');
});

it('counts sign-in failures on Valkey, keyed without the raw identifier or address (AU-05; WS-12)', function () {
    config(['cache.default' => 'redis']);
    Cache::store('redis')->flush();

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'salah'])->assertStatus(422);

    $keys = collect(Cache::store('redis')->getStore()->connection()->keys('*login:*'));
    expect($keys)->not->toBeEmpty()
        ->and($keys->filter(fn (string $key) => str_contains($key, 'laras') || str_contains($key, '127.0.0.1')))->toBeEmpty();

    Cache::store('redis')->flush();
});

it('refuses the attempt when the limiter store cannot be read — fail closed (AU-05; H6-07)', function () {
    config(['cache.default' => 'redis', 'database.redis.cache.port' => 1]);
    app('cache')->forgetDriver('redis');
    app('redis')->purge('cache');

    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertStatus(429);
    expect(auth()->check())->toBeFalse()
        ->and(LoginThrottle::addressKey('2001:db8:aa:bb:1:2:3:4'))->toBe(LoginThrottle::addressKey('2001:db8:aa:bb:ffff::9'));
});
