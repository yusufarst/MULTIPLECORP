<?php

use App\Modules\IAM\Authentication\SecondFactorLimit;
use App\Modules\IAM\Authentication\SecondFactorRefused;
use App\Modules\IAM\Authentication\Totp;
use App\Modules\IAM\Authentication\TwoFactorChallenge;
use App\Modules\IAM\Authentication\TwoFactorSteps;
use App\Modules\IAM\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\TwoFactor;

// TL-05 (TASK-018; SM-17 "real-session tests"; M-39, M-40; SM-14; RV-12, RV-13): the races of the code steps under
// real PostgreSQL sessions, each in a process of its own with a barrier before the transaction — one TOTP code
// submitted twice passes once, one recovery code from two pending challenges is removed once —, and AU-05's
// second-factor count on Valkey taking no more than five places from a burst of concurrent processes.

uses(DatabaseTruncation::class);

afterEach(function () {
    // The sessions commit, so the tests after these in this process start from empty tables.
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    $this->user = TwoFactor::enrol(User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']));
});

it('passes one code once when two sessions submit it together (M-39; RV-12)', function () {
    $userId = $this->user->id;
    // The step fixed first: the processes run on the real clock, which may cross a step boundary meanwhile.
    $step = Totp::currentStep();
    $code = app(Totp::class)->codeAt(TwoFactor::SECRET, $step);
    $submit = static function () use ($userId, $code): string {
        $user = User::query()->findOrFail($userId);
        $found = app(Totp::class)->stepOf(TwoFactor::SECRET, $code, (int) $user->getRawOriginal('two_factor_last_step'));
        Barrier::await('both found the step');

        return $found === null ? 'NO_STEP' : app(TwoFactorSteps::class)->acceptCode($user, TwoFactorChallenge::digest($user), $found);
    };

    $results = Sessions::run(['first' => $submit, 'second' => $submit]);

    expect(collect($results)->sort()->values()->all())->toBe([TwoFactorSteps::ACCEPTED, TwoFactorSteps::STALE])
        ->and((int) DB::table('users')->where('id', $userId)->value('two_factor_last_step'))->toBe($step);
});

it('removes one recovery code once when two pending challenges submit it together, and refuses it later (M-40; RV-13)', function () {
    $userId = $this->user->id;
    $submit = static function () use ($userId): string {
        $user = User::query()->findOrFail($userId);
        $digest = TwoFactorChallenge::digest($user);
        Barrier::await('both challenges hold the digest');

        return app(TwoFactorSteps::class)->consumeRecoveryCode($user, $digest, TwoFactor::CODES[2], 'sesi-'.bin2hex(random_bytes(4)));
    };

    $results = Sessions::run(['first' => $submit, 'second' => $submit]);

    expect(collect($results)->sort()->values()->all())->toBe([TwoFactorSteps::ACCEPTED, TwoFactorSteps::VOID])
        ->and(TwoFactor::storedCodes($this->user))->toHaveCount(7)->not->toContain(TwoFactor::CODES[2])
        ->and(DB::table('audit_events')->where('action', 'IAM.RecoveryCodeUsed')->count())->toBe(1);

    $fresh = User::query()->findOrFail($userId);
    expect(app(TwoFactorSteps::class)->consumeRecoveryCode($fresh, TwoFactorChallenge::digest($fresh), TwoFactor::CODES[2], 'sesi-later'))->toBe(TwoFactorSteps::STALE);
});

it('takes no more than five places on Valkey from seven concurrent processes (AU-05; H6-07; SM-14)', function () {
    $account = random_int(100_000_000, 999_999_999);
    $take = static function () use ($account): string {
        config(['cache.default' => 'redis']);
        Barrier::await('every process is ready');

        try {
            app(SecondFactorLimit::class)->take($account, Request::create('/masuk/verifikasi', 'POST'));

            return 'PLACED';
        } catch (SecondFactorRefused) {
            return 'REFUSED';
        }
    };

    $results = Sessions::run(array_fill_keys(['a', 'b', 'c', 'd', 'e', 'f', 'g'], $take));

    expect(collect($results)->countBy()->all())->toEqual(['PLACED' => 5, 'REFUSED' => 2]);
    Cache::store('redis')->forget("two-factor-limit:{$account}");
});
