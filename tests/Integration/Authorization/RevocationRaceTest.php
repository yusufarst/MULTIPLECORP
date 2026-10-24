<?php

use App\Modules\IAM\Services\CommandAuthorization;
use App\Modules\ORG\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;

// TL-05 (TASK-011): the in-command re-authorization of CONCURRENCY_IDEMPOTENCY RV-01–RV-03 under two real
// sessions (SQ-22; architecture challenge 8a): an access change and a command queue on the actor's row, so
// a command arriving during a revocation waits and is then denied, and one already holding the row ends
// before the revocation commits.

uses(DatabaseTruncation::class);

afterEach(function () {
    // The sessions commit, so the tests after these in this process start from empty tables.
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage']);
});

/** A revocation of the account's capability under its row lock (RV-02), held until the other session waits on it. */
function revokeWhileHeld(int $userId, string $capability): Closure
{
    return static function () use ($userId, $capability): string {
        DB::transaction(function () use ($userId, $capability) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->value('id');
            DB::table('user_capability_grants')
                ->where('user_id', $userId)
                ->where('capability_id', DB::table('capabilities')->where('code', $capability)->value('id'))
                ->update(['revoked_at' => now()]);
            Barrier::await('the revocation holds the account');
            Sessions::awaitBlocked();
        });

        return 'revoked';
    };
}

it('denies a command that arrives while its capability is being revoked (RV-03)', function () {
    [$userId, $companyId] = [$this->admin->id, $this->company->id];
    $results = Sessions::run([
        'revocation' => revokeWhileHeld($this->admin->id, 'projects.manage'),
        'command' => static function () use ($userId, $companyId): string {
            Barrier::await('the revocation holds the account');

            try {
                DB::transaction(fn () => app(CommandAuthorization::class)->reauthorize($userId, 'projects.manage', $companyId));
            } catch (AuthorizationException) {
                return 'denied';
            }

            return 'authorized';
        },
    ]);

    expect($results)->toBe(['revocation' => 'revoked', 'command' => 'denied']);
});

it('lets a command that already holds the account end before a revocation commits (RV-03)', function () {
    [$userId, $companyId] = [$this->admin->id, $this->company->id];
    $results = Sessions::run([
        'command' => static function () use ($userId, $companyId): string {
            return DB::transaction(function () use ($userId, $companyId): string {
                app(CommandAuthorization::class)->reauthorize($userId, 'projects.manage', $companyId);
                Barrier::await('the command holds the account');
                Sessions::awaitBlocked();

                return 'authorized';
            });
        },
        'revocation' => static function () use ($userId): string {
            Barrier::await('the command holds the account');
            DB::transaction(fn () => DB::table('users')->where('id', $userId)->lockForUpdate()->value('id'));

            return 'waited';
        },
    ]);

    expect($results)->toBe(['command' => 'authorized', 'revocation' => 'waited']);
});

it('answers a company outside scope as not found and a missing capability as forbidden (AZ-11)', function () {
    $other = Company::factory()->create();

    expect(fn () => DB::transaction(fn () => app(CommandAuthorization::class)->reauthorize($this->admin->id, 'projects.manage', $other->id)))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => DB::transaction(fn () => app(CommandAuthorization::class)->reauthorize($this->admin->id, 'projects.cancel', $this->company->id)))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(CommandAuthorization::class)->reauthorize($this->admin->id, 'projects.manage', $this->company->id))
        ->toThrow(LogicException::class);
});
