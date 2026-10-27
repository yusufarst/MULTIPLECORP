<?php

use App\Modules\IAM\Actions\DeactivateUser;
use App\Modules\IAM\Actions\GrantAccess;
use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\CommandAuthorization;
use App\Modules\ORG\Models\Company;
use App\Support\Command\Outcome;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\TwoFactor;

// TL-05 (TASK-024; RV-02–RV-05; RV-11; RV-16; M-20; M-21; M-22; M-42; M-45; HO-13): PG-060's access changes under real
// PostgreSQL sessions — an access change waits for the target's command in flight and commits after it, the target's
// next command denied; a deactivation racing the target's command; two deactivations of the last two active Owners,
// two Owners against each other and one Owner sending both, at least one active Owner remaining; a Google link racing
// the deactivation in both orders, no link surviving on an inactive account; a grant that makes an account without
// TOTP high-risk racing its own password change, the password unusable and one RESET link left.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage']);
});

/** An NX-02 command's answer as a word: its outcome kind and code, or the denial a stale actor gets (AZ-11). */
function raceOutcome(Closure $command): string
{
    try {
        $outcome = $command();

        return $outcome instanceof Outcome ? trim("{$outcome->kind} {$outcome->code}") : 'none';
    } catch (AuthorizationException) {
        return 'denied';
    }
}

/** The target's command in flight: it holds its own row at LK-03 (RV-01) until another session waits on it. */
function raceHoldTarget(int $userId, string $capability, int $companyId): Closure
{
    return static function () use ($userId, $capability, $companyId): string {
        return DB::transaction(function () use ($userId, $capability, $companyId): string {
            app(CommandAuthorization::class)->reauthorize($userId, $capability, $companyId);
            Barrier::await('the target\'s command holds its row');
            Sessions::awaitBlocked();

            return 'committed';
        });
    };
}

it('commits an access change after the target\'s command in flight, the target\'s next command denied (RV-02; RV-03; M-20)', function () {
    [$owner, $admin, $company] = [$this->owner->id, $this->admin->id, $this->company->id];

    $results = Sessions::run([
        'target' => raceHoldTarget($admin, 'projects.manage', $company),
        'revocation' => static function () use ($owner, $admin, $company): string {
            Barrier::await('the target\'s command holds its row');

            return raceOutcome(fn () => app(GrantAccess::class)->run((string) Str::uuid(), $owner, User::query()->findOrFail($admin), 'ADMIN_OPERASIONAL', [$company], ['projects.view'], 0, null));
        },
    ]);

    expect($results)->toBe(['target' => 'committed', 'revocation' => 'COMMITTED']);

    try {
        DB::transaction(fn () => app(CommandAuthorization::class)->reauthorize($admin, 'projects.manage', $company));
        $next = 'authorized';
    } catch (AuthorizationException) {
        $next = 'denied';
    }

    expect($next)->toBe('denied');
});

it('commits a deactivation after the target\'s command in flight, every grant revoked and the account inactive (RV-05; M-21)', function () {
    [$owner, $admin, $company] = [$this->owner->id, $this->admin->id, $this->company->id];

    $results = Sessions::run([
        'target' => raceHoldTarget($admin, 'projects.manage', $company),
        'deactivation' => static function () use ($owner, $admin): string {
            Barrier::await('the target\'s command holds its row');

            return raceOutcome(fn () => app(DeactivateUser::class)->run((string) Str::uuid(), $owner, User::query()->findOrFail($admin)));
        },
    ]);

    expect($results)->toBe(['target' => 'committed', 'deactivation' => 'COMMITTED'])
        ->and(DB::table('users')->where('id', $admin)->value('is_active'))->toBeFalse()
        ->and(DB::table('user_capability_grants')->where('user_id', $admin)->whereNull('revoked_at')->exists())->toBeFalse();
});

it('keeps an active Owner when two Owners deactivate each other at once (LK-02; RV-04; M-22)', function () {
    $other = TwoFactor::enrol(Authorization::owner());
    [$first, $second] = [$this->owner->id, $other->id];

    $results = Sessions::run([
        'first' => static function () use ($first, $second): string {
            Barrier::await('both are ready');

            return raceOutcome(fn () => app(DeactivateUser::class)->run((string) Str::uuid(), $first, User::query()->findOrFail($second)));
        },
        'second' => static function () use ($first, $second): string {
            Barrier::await('both are ready');

            return raceOutcome(fn () => app(DeactivateUser::class)->run((string) Str::uuid(), $second, User::query()->findOrFail($first)));
        },
    ]);

    expect(collect($results)->sort()->values()->all())->toBe(['COMMITTED', 'denied'])
        ->and(DB::table('users')->whereIn('id', [$first, $second])->where('is_active', true)->count())->toBe(1);
});

it('keeps an active Owner when one Owner deactivates both of the last two at once, the second REJECTED by RG-09 (M-22)', function () {
    $other = TwoFactor::enrol(Authorization::owner());
    [$first, $second] = [$this->owner->id, $other->id];

    $results = Sessions::run([
        'the other' => static function () use ($first, $second): string {
            Barrier::await('both are ready');

            return raceOutcome(fn () => app(DeactivateUser::class)->run((string) Str::uuid(), $first, User::query()->findOrFail($second)));
        },
        'itself' => static function () use ($first): string {
            Barrier::await('both are ready');

            return raceOutcome(fn () => app(DeactivateUser::class)->run((string) Str::uuid(), $first, User::query()->findOrFail($first)));
        },
    ]);

    // Whichever commits first leaves one active Owner; the other is RG-09's refusal, or a denial once its actor is gone.
    expect(collect($results)->filter(fn (string $result) => $result === 'COMMITTED'))->toHaveCount(1)
        ->and(collect($results)->reject(fn (string $result) => $result === 'COMMITTED')->first())->toBeIn(['REJECTED RG-09', 'denied'])
        ->and(DB::table('users')->whereIn('id', [$first, $second])->where('is_active', true)->count())->toBe(1);
});

it('unlinks a Google link that committed before the deactivation, with reason DEACTIVATION (RV-05; RV-16; M-42 link first)', function () {
    $admin = TwoFactor::enrol(User::query()->findOrFail($this->admin->id));
    [$owner, $target] = [$this->owner->id, $admin->id];

    $results = Sessions::run([
        'link' => static function () use ($target): string {
            return DB::transaction(function () use ($target): string {
                DB::table('users')->where('id', $target)->lock('for no key update')->value('id');
                Barrier::await('the link holds the account');
                Sessions::awaitBlocked();

                return app(GoogleIdentities::class)->link(User::query()->findOrFail($target), 'sub-m42', 'm42@contoh.invalid');
            });
        },
        'deactivation' => static function () use ($owner, $target): string {
            Barrier::await('the link holds the account');

            return raceOutcome(fn () => app(DeactivateUser::class)->run((string) Str::uuid(), $owner, User::query()->findOrFail($target)));
        },
    ]);

    expect($results)->toBe(['link' => GoogleIdentities::LINKED, 'deactivation' => 'COMMITTED'])
        ->and(DB::table('user_external_identities')->where('user_id', $target)->value('unlink_reason'))->toBe('DEACTIVATION')
        ->and(DB::table('user_external_identities')->where('user_id', $target)->whereNull('unlinked_at')->exists())->toBeFalse();
});

it('refuses a Google link that waits for the deactivation, no link surviving (RV-16; M-42 deactivation first)', function () {
    $admin = TwoFactor::enrol(User::query()->findOrFail($this->admin->id));
    [$owner, $target] = [$this->owner->id, $admin->id];

    $results = Sessions::run([
        'deactivation' => static function () use ($owner, $target): string {
            return DB::transaction(function () use ($owner, $target): string {
                DB::table('users')->where('id', $target)->lock('for no key update')->value('id');
                Barrier::await('the deactivation holds the account');
                Sessions::awaitBlocked();
                DB::table('users')->where('id', $target)->update(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $owner]);

                return 'deactivated';
            });
        },
        'link' => static function () use ($target): string {
            Barrier::await('the deactivation holds the account');

            return app(GoogleIdentities::class)->link(User::query()->findOrFail($target), 'sub-m42b', 'm42b@contoh.invalid');
        },
    ]);

    expect($results)->toBe(['deactivation' => 'deactivated', 'link' => GoogleIdentities::REFUSED])
        ->and(DB::table('user_external_identities')->where('user_id', $target)->exists())->toBeFalse();
});

it('makes the password unusable with one RESET link when a high-risk grant meets the account\'s own password change (RV-11; RV-10; M-45)', function () {
    [$owner, $admin, $company] = [$this->owner->id, $this->admin->id, $this->company->id];
    $hash = (string) DB::table('users')->where('id', $admin)->value('password');

    $results = Sessions::run([
        'password change' => static function () use ($admin): string {
            return DB::transaction(function () use ($admin): string {
                DB::table('users')->where('id', $admin)->lock('for no key update')->value('id');
                Barrier::await('the change holds the account');
                Sessions::awaitBlocked();
                DB::table('users')->where('id', $admin)->update(['password' => Hash::make('Pelangi-Biru#8492')]);

                return 'changed';
            });
        },
        'grant' => static function () use ($owner, $admin, $company): string {
            Barrier::await('the change holds the account');

            return raceOutcome(fn () => app(GrantAccess::class)->run((string) Str::uuid(), $owner, User::query()->findOrFail($admin), 'ADMIN_OPERASIONAL', [$company], ['projects.view', 'projects.manage', 'finance.view'], 0, null));
        },
    ]);

    expect($results)->toBe(['password change' => 'changed', 'grant' => 'COMMITTED'])
        ->and(DB::table('users')->where('id', $admin)->value('password'))->not->toBe($hash)
        ->and(Hash::check('Pelangi-Biru#8492', (string) DB::table('users')->where('id', $admin)->value('password')))->toBeFalse()
        ->and(DB::table('credential_tokens')->where('user_id', $admin)->where('purpose', 'RESET')->whereNull('revoked_at')->whereNull('used_at')->count())->toBe(1);
});
