<?php

use App\Modules\IAM\Recovery\AccountHistory;
use App\Modules\IAM\Recovery\IncidentRuns;
use App\Modules\IAM\Recovery\OwnerRestoration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\OwnerTrail;

// TL-05 (TASK-020; RV-04, RV-18; M-46; SM-21; TT-F30; GAP-050): the OWNER-restoration command and the end of a run's
// recovery under real PostgreSQL sessions — two restorations of one account with a barrier before LK-02, one restoring
// and the other refused; a role change racing a restoration, waiting at LK-02 and meeting the restored state; and the
// end of recovery against a role change that makes an unreviewed Owner, in both orders, the end atomic under the
// OWNER role row so that it never lifts the guard from an account made Owner before it.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    OwnerTrail::catalogue();
    $this->now = CarbonImmutable::now();
    $this->window = IncidentRuns::format($this->now->subDays(10));
    $this->owner = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'ratna@contoh.test');
    $this->other = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'bayu@contoh.test');
    $this->admin = OwnerTrail::account('ADMIN_OPERASIONAL', $this->now->subDays(30), email: 'citra@contoh.test');
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    OwnerTrail::run($this->now->subDays(10), $this->now->subHour(), $this->now->subMinutes(30));
});

it('restores one account once when two restorations race, a barrier before LK-02 (M-46; RV-18)', function () {
    $window = $this->window;
    $results = Sessions::run([
        'first' => static function () use ($window): string {
            Barrier::await('both are ready');

            return OwnerTrail::attempt(fn () => app(OwnerRestoration::class)->restore('ratna@contoh.test', $window, OwnerTrail::operator()));
        },
        'second' => static function () use ($window): string {
            Barrier::await('both are ready');

            return OwnerTrail::attempt(fn () => app(OwnerRestoration::class)->restore('ratna@contoh.test', $window, OwnerTrail::operator()));
        },
    ]);

    $outcomes = collect($results)->map(fn (string $result) => str_starts_with($result, 'refused') ? 'refused' : $result)->sort()->values()->all();
    expect($outcomes)->toBe(['done', 'refused'])
        ->and(collect($results)->first(fn (string $result) => str_starts_with($result, 'refused')))->toContain('restored already')
        ->and(DB::table('audit_events')->where('action', IncidentRuns::RESTORED)->count())->toBe(1)
        ->and(app(AccountHistory::class)->current($this->owner->id)['role'])->toBe('OWNER');
});

it('lets a role change racing a restoration wait at LK-02 and meet the restored state (M-46; RV-04)', function () {
    $window = $this->window;
    $owner = $this->owner->id;
    $results = Sessions::run([
        'restoration' => static function () use ($window): string {
            return DB::transaction(function () use ($window): string {
                app(AccountHistory::class)->lockOwnerRole();
                Barrier::await('the restoration holds LK-02');
                usleep(400_000);

                return OwnerTrail::attempt(fn () => app(OwnerRestoration::class)->restore('ratna@contoh.test', $window, OwnerTrail::operator()));
            });
        },
        'role change' => static function () use ($owner): string {
            Barrier::await('the restoration holds LK-02');
            $seen = DB::transaction(function () use ($owner): ?string {
                app(AccountHistory::class)->lockOwnerRole();

                return app(AccountHistory::class)->current($owner)['role'];
            });

            return "saw {$seen}";
        },
    ]);

    expect($results)->toBe(['restoration' => 'done', 'role change' => 'saw OWNER']);
});

it('refuses the end of recovery when a role change made an unreviewed Owner first (TT-F30; GAP-050)', function () {
    $window = $this->window;
    $admin = $this->admin->id;
    $other = $this->other->id;
    $results = Sessions::run([
        'role change' => static fn (): string => OwnerTrail::racingRoleChange($admin, 'OWNER', $other, 'the role change holds LK-02'),
        'end' => static function () use ($window): string {
            Barrier::await('the role change holds LK-02');

            return OwnerTrail::attempt(fn () => app(OwnerRestoration::class)->endRecovery($window, OwnerTrail::operator()));
        },
    ]);

    expect($results['role change'])->toBe('changed')
        ->and($results['end'])->toContain('refused')->toContain('citra@contoh.test')
        ->and(DB::table('audit_events')->where('action', IncidentRuns::ENDED)->count())->toBe(0);
});

it('records the end first when it holds LK-02, the role change following as an ordinary act after it (TT-F30; GAP-050)', function () {
    $window = $this->window;
    $admin = $this->admin->id;
    $other = $this->other->id;
    $results = Sessions::run([
        'end' => static function () use ($window): string {
            return DB::transaction(function () use ($window): string {
                app(AccountHistory::class)->lockOwnerRole();
                Barrier::await('the end holds LK-02');
                usleep(400_000);

                return OwnerTrail::attempt(fn () => app(OwnerRestoration::class)->endRecovery($window, OwnerTrail::operator()));
            });
        },
        'role change' => static function () use ($admin, $other): string {
            Barrier::await('the end holds LK-02');

            return OwnerTrail::racingRoleChange($admin, 'OWNER', $other);
        },
    ]);

    // The role change waited at LK-02 for the end's commit: its event follows the end's.
    $ended = DB::table('audit_events')->where('action', IncidentRuns::ENDED)->value('id');
    $changed = DB::table('audit_events')->where('action', 'Test.RoleChanged')->where('entity_id', $this->admin->id)->value('id');
    expect($results)->toBe(['end' => 'done', 'role change' => 'changed'])
        ->and($changed)->toBeGreaterThan($ended);
});
