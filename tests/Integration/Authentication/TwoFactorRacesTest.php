<?php

use App\Modules\IAM\Authentication\SecondFactorLimit;
use App\Modules\IAM\Authentication\SecondFactorRefused;
use App\Modules\IAM\Authentication\SessionTerminator;
use App\Modules\IAM\Authentication\Totp;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-05 (TASK-018; M-39; SM-14; RV-12, RV-13; H6-15): one code passing once when two submissions race for the same
// step — the second meets the conditional update of `two_factor_last_step` after the first committed, one effect
// —; a pending challenge's session row in PostgreSQL carrying its account, so deleting the account's sessions voids
// it; AU-05's second-factor count exact under a burst of concurrent places; and the limit closed when its store
// cannot be reached.

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    $this->travelTo(now()->setTimestamp(1_800_000_015));
    $this->user = TwoFactor::enrol(User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']));
});

afterEach(fn () => @unlink($this->hotFile));

it('passes one code once when a second submission races it to the same step (M-39; RV-12)', function () {
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $step = Totp::currentStep();

    // The other submission commits the same step between this one's check and its update, under the row lock.
    DB::listen(function (QueryExecuted $query) use ($step) {
        static $raced = false;

        if (! $raced && str_contains($query->sql, 'from "users"') && str_contains($query->sql, 'for update')) {
            $raced = true;
            DB::table('users')->where('id', $this->user->id)->update(['two_factor_last_step' => $step]);
        }
    });

    $this->postJson('/masuk/verifikasi', ['code' => TwoFactor::code()])->assertStatus(422)->assertJsonPath('message', __('auth.code_mismatch'));

    expect(auth()->check())->toBeFalse()
        ->and((int) DB::table('users')->where('id', $this->user->id)->value('two_factor_last_step'))->toBe($step)
        ->and(DB::table('security_events')->where('event_kind', 'LOGIN_SUCCEEDED')->count())->toBe(0);
});

it('writes the pending challenge\'s account into its session row, so ending the account\'s sessions voids it (AU-16; H6-15; H8-22)', function () {
    $this->post('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertRedirect('/masuk/verifikasi');
    $id = session()->getId();

    expect(DB::table('sessions')->where('id', $id)->value('user_id'))->toBe($this->user->id);

    // A later request reads the session from its row, which is gone with the challenge in it.
    app(SessionTerminator::class)->endAll($this->user->id);
    expect(DB::table('sessions')->where('id', $id)->exists())->toBeFalse();
    expect(app('session')->driver()->getHandler()->read($id))->toBe('');
    expect(auth()->check())->toBeFalse();
});

it('lets a burst of concurrent submissions take no more than five places in the account\'s count (AU-05; H6-07; SM-14)', function () {
    $limit = app(SecondFactorLimit::class);
    $request = Request::create('/masuk/verifikasi', 'POST');
    $places = [];
    $refused = 0;

    foreach (range(1, 7) as $submission) {
        try {
            $places[] = $limit->take($this->user->id, $request);
        } catch (SecondFactorRefused) {
            $refused++;
        }
    }

    expect($places)->toHaveCount(5)->and($refused)->toBe(2);

    // One accepted gives its place back; the others failing make the breach — four failures and this fifth.
    $limit->accepted(array_shift($places));
    $breaches = array_map(fn ($place) => $limit->failed($place, SecondFactorLimit::PATH_PASSWORD, $request), $places);
    expect($breaches)->toBe([false, false, false, false]);

    $fifth = $limit->take($this->user->id, $request);
    expect($limit->failed($fifth, SecondFactorLimit::PATH_PASSWORD, $request))->toBeTrue()
        ->and(fn () => $limit->take($this->user->id, $request))->toThrow(SecondFactorRefused::class);
});

it('counts codes from many client addresses across every code-accepting step in the account\'s one count: five places, one breach, then the cooldown (M-44; AU-05; H6-07)', function () {
    $limit = app(SecondFactorLimit::class);
    $steps = [
        [SecondFactorLimit::PATH_PASSWORD, Request::create('/masuk/verifikasi', 'POST', server: ['REMOTE_ADDR' => '10.0.0.1'])],
        [SecondFactorLimit::PATH_PASSWORD, Request::create('/masuk/verifikasi/pemulihan', 'POST', server: ['REMOTE_ADDR' => '10.0.0.2'])],
        [SecondFactorLimit::PATH_GOOGLE, Request::create('/masuk/verifikasi', 'POST', server: ['REMOTE_ADDR' => '192.0.2.7'])],
        [SecondFactorLimit::PATH_SESSION_STEP, Request::create('/masuk/verifikasi', 'POST', server: ['REMOTE_ADDR' => '2001:db8::1'])],
        [SecondFactorLimit::PATH_SESSION_STEP, Request::create('/masuk/verifikasi', 'POST', server: ['REMOTE_ADDR' => '198.51.100.9'])],
        [SecondFactorLimit::PATH_GOOGLE, Request::create('/masuk/verifikasi', 'POST', server: ['REMOTE_ADDR' => '203.0.113.4'])],
    ];
    $places = [];
    $refused = 0;

    foreach ($steps as [, $request]) {
        try {
            $places[] = $limit->take($this->user->id, $request);
        } catch (SecondFactorRefused) {
            $refused++;
        }
    }

    // At most five codes evaluated in the window, whichever address and step sent them.
    expect($places)->toHaveCount(SecondFactorLimit::THRESHOLD)->and($refused)->toBe(1);

    $breaches = array_map(fn ($place, $step) => $limit->failed($place, $step[0], $step[1]), $places, array_slice($steps, 0, count($places)));

    expect(array_values(array_filter($breaches)))->toHaveCount(1)
        ->and(DB::table('security_events')->where('event_kind', 'TWO_FACTOR_COOLDOWN_STARTED')->count())->toBe(1)
        ->and(fn () => $limit->take($this->user->id, $steps[5][1]))->toThrow(SecondFactorRefused::class);
});

it('refuses every covered step when the limiter store cannot be reached — fail closed (AU-05; H6-07)', function () {
    config(['cache.default' => 'redis', 'database.redis.cache.port' => 1, 'database.redis.default.port' => 1]);
    app('cache')->forgetDriver('redis');
    app('redis')->purge('cache');
    app('redis')->purge('default');

    expect(fn () => app(SecondFactorLimit::class)->take($this->user->id, Request::create('/masuk/verifikasi', 'POST')))->toThrow(SecondFactorRefused::class);
});

it('applies the limit on Valkey, the account\'s entry keyed by its id alone and never expiring (AU-05; HO-24)', function () {
    config(['cache.default' => 'redis']);
    Cache::store('redis')->forget("two-factor-limit:{$this->user->id}");
    $limit = app(SecondFactorLimit::class);
    $request = Request::create('/masuk/verifikasi', 'POST');

    $place = $limit->take($this->user->id, $request);
    expect($limit->failed($place, SecondFactorLimit::PATH_PASSWORD, $request))->toBeFalse();

    $connection = Cache::store('redis')->getStore()->connection();
    $key = Cache::store('redis')->getStore()->getPrefix()."two-factor-limit:{$this->user->id}";
    expect((int) $connection->ttl($key))->toBe(-1)
        ->and((string) $connection->get($key))->not->toContain('laras');

    Cache::store('redis')->forget("two-factor-limit:{$this->user->id}");
    expect(session(TwoFactorChallenge::KEY))->toBeNull();
});
