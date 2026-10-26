<?php

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Recovery\IncidentRuns;
use App\Modules\IAM\Recovery\KeyCompromise;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\OwnerTrail;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 and TL-14 (TASK-020; SECURITY AU-21 (a)–(d); RV-17; HO-41; SM-21; criterion 3): the key-compromise incident
// command refuses to start or to continue outside the maintenance mode or with a bypass; records the window start
// once, every event of the run naming it; runs the opening sweep, voids every account — one created after the run
// started included — and the closing sweep; keeps Google links; and voids each account exactly once across a
// restart, which refuses another window start. Synthetic keys and accounts only.

uses(RefreshDatabase::class);

beforeEach(function () {
    // The maintenance mode on this process's array store, never the file other test processes would read.
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
    app()->maintenanceMode()->activate(['secret' => null, 'status' => 503]);
    OwnerTrail::catalogue();
    $this->now = CarbonImmutable::now();
    $this->window = IncidentRuns::format($this->now->subDays(3));
    $this->owner = TwoFactor::enrol(OwnerTrail::account('OWNER', $this->now->subDays(30)));
    $this->admin = TwoFactor::enrol(OwnerTrail::account('ADMIN_OPERASIONAL', $this->now->subDays(30)));
    $this->incident = app(KeyCompromise::class);
});

afterEach(function () {
    app()->maintenanceMode()->deactivate();
});

function voidedIds(string $runId): array
{
    return DB::table('audit_events')->where('action', IncidentRuns::VOIDED)->whereRaw("metadata->>'incident_run' = ?", [$runId])
        ->orderBy('entity_id')->pluck('entity_id')->map(fn ($id) => (int) $id)->all();
}

it('refuses to start outside the maintenance mode, or with a bypass, writing nothing (AU-21 b)', function () {
    app()->maintenanceMode()->deactivate();
    expect(fn () => $this->incident->run($this->window, OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'not active');

    app()->maintenanceMode()->activate(['secret' => 'jalan-pintas']);
    expect(fn () => $this->incident->run($this->window, OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'bypass')
        ->and(fn () => $this->incident->run('besok', OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'ISO 8601')
        ->and(DB::table('audit_events')->where('action', 'like', 'IAM.Incident%')->count())->toBe(0);
});

it('runs protocol (a)–(d): both sweeps, every account voided once — one created during the run included —, Google kept (AU-21; RV-17; criterion 3)', function () {
    app(GoogleIdentities::class)->link($this->owner, 'sub-ratna', null);
    DB::table('sessions')->insert([
        ['id' => 'sesi-owner', 'user_id' => $this->owner->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'sesi-tamu', 'user_id' => null, 'payload' => '', 'last_activity' => time()],
    ]);
    $reset = app(CredentialLinks::class)->issue($this->admin, 'RESET', $this->owner->id);
    $before = DB::table('users')->pluck('password', 'id');
    $created = null;

    $result = $this->incident->run($this->window, OwnerTrail::operator(), function (string $line) use (&$created) {
        // An account created while the run lasts — after its start — is processed too (AU-21 c).
        if ($created === null && str_starts_with($line, 'Opening sweep')) {
            $created = User::factory()->create();
            DB::table('sessions')->insert(['id' => 'sesi-baru', 'user_id' => $created->id, 'payload' => '', 'last_activity' => time()]);
        }
    });

    $runId = $result['run']->id;
    $users = DB::table('users')->orderBy('id')->get();
    expect($result['run']->completed())->toBeTrue()
        ->and($result['run']->windowStartText())->toBe($this->window)
        ->and(voidedIds($runId))->toBe($users->pluck('id')->map(fn ($id) => (int) $id)->all())
        ->and($users->whereNotNull('two_factor_secret')->count())->toBe(0)
        ->and($users->every(fn ($user) => $before->get($user->id) === null || $before->get($user->id) !== $user->password))->toBeTrue()
        ->and(DB::table('sessions')->count())->toBe(0)
        ->and(DB::table('credential_tokens')->where('token_hash', CredentialLinks::hash($reset))->value('revoked_at'))->not->toBeNull()
        ->and(DB::table('user_external_identities')->whereNull('unlinked_at')->count())->toBe(1)
        ->and(DB::table('security_events')->where('event_kind', 'CREDENTIALS_VOIDED')->count())->toBe($users->count())
        ->and(DB::table('audit_events')->where('action', IncidentRuns::SWEPT)->whereRaw("metadata->>'phase' = 'opening'")->count())->toBe(2)
        ->and(DB::table('audit_events')->where('action', IncidentRuns::COMPLETED)->count())->toBe(1);

    // Every event of the run carries the operator and the window start, with no user row as actor.
    $events = DB::table('audit_events')->whereRaw("metadata->>'incident_run' = ?", [$runId])->get();
    expect($events->whereNotNull('actor_user_id')->count())->toBe(0)
        ->and($events->every(fn ($event) => json_decode((string) $event->metadata, true)['window_start'] === $this->window))->toBeTrue()
        ->and($events->every(fn ($event) => json_decode((string) $event->metadata, true)['operator']['name'] === OwnerTrail::OPERATOR))->toBeTrue();
});

it('refuses to continue once the maintenance mode is lifted, and a restart voids each account once with the same window start only (RV-17; criterion 3)', function () {
    $third = TwoFactor::enrol(OwnerTrail::account('ADMIN_OPERASIONAL', $this->now->subDays(30)));

    expect(fn () => $this->incident->run($this->window, OwnerTrail::operator(), function (string $line) {
        if (str_starts_with($line, 'Opening sweep')) {
            app()->maintenanceMode()->deactivate();
        }
    }))->toThrow(RecoveryRefused::class, 'not active');

    $run = app(IncidentRuns::class)->latest();
    expect($run?->completed())->toBeFalse()->and(voidedIds((string) $run?->id))->toBe([]);

    // A stop part-way: the first account voided, then the run stops.
    app()->maintenanceMode()->activate(['secret' => null]);
    $voided = 0;
    DB::listen(function ($query) use (&$voided) {
        if (str_contains($query->sql, 'insert into "audit_events"') && in_array(IncidentRuns::VOIDED, $query->bindings, true) && ++$voided === 2) {
            app()->maintenanceMode()->deactivate();
        }
    });
    expect(fn () => $this->incident->run($this->window, OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'not active')
        ->and(voidedIds((string) $run?->id))->toHaveCount(2);

    app()->maintenanceMode()->activate(['secret' => null]);
    expect(fn () => $this->incident->run(IncidentRuns::format($this->now->subDays(4)), OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'restart it with that value');

    $result = $this->incident->run($this->window, OwnerTrail::operator());

    expect($result['restarted'])->toBeTrue()
        ->and($result['run']->id)->toBe($run?->id)
        ->and(voidedIds((string) $run?->id))->toBe([$this->owner->id, $this->admin->id, $third->id])
        ->and(DB::table('audit_events')->where('action', IncidentRuns::STARTED)->count())->toBe(1);
});

it('reads the accounts again after the last one, voiding one created while the voiding runs (AU-21 c; RV-17)', function () {
    $created = null;
    DB::listen(function ($query) use (&$created) {
        if ($created === null && str_contains($query->sql, 'insert into "audit_events"') && in_array(IncidentRuns::VOIDED, $query->bindings, true)) {
            $created = false;
            $created = User::factory()->create();
        }
    });

    $result = $this->incident->run($this->window, OwnerTrail::operator());

    expect($created)->toBeInstanceOf(User::class)
        ->and(voidedIds($result['run']->id))->toBe([$this->owner->id, $this->admin->id, $created->id]);
});

it('voids an account created during the closing sweep before the run completes (AU-21 c, d; RV-17)', function () {
    $created = null;

    $result = $this->incident->run($this->window, OwnerTrail::operator(), function (string $line) use (&$created) {
        // The last read of the accounts is over; the account appears while the closing sweep runs.
        if ($created === null && str_starts_with($line, 'Accounts voided (c)')) {
            $created = User::factory()->create();
            DB::table('sessions')->insert(['id' => 'sesi-penutup', 'user_id' => $created->id, 'payload' => '', 'last_activity' => time()]);
        }
    });

    expect(voidedIds($result['run']->id))->toBe([$this->owner->id, $this->admin->id, $created?->id])
        ->and(DB::table('users')->where('id', $created?->id)->value('password'))->not->toBe($created?->password)
        ->and(DB::table('sessions')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', IncidentRuns::COMPLETED)->count())->toBe(1);
});

it('writes on a restart the CREDENTIALS_VOIDED a stop lost, voiding no account twice (LG-02; RV-17)', function () {
    $run = OwnerTrail::run(IncidentRuns::parse($this->window), $this->now->subMinutes(10), null);
    OwnerTrail::event($this->owner, IncidentRuns::VOIDED, $this->now->subMinutes(9), null, null, null, ['incident_run' => $run, 'window_start' => $this->window]);

    $result = $this->incident->run($this->window, OwnerTrail::operator());

    expect($result['restarted'])->toBeTrue()
        ->and($result['voided'])->toBe(1)
        ->and(voidedIds($run))->toBe([$this->owner->id, $this->admin->id])
        ->and(DB::table('security_events')->where('event_kind', 'CREDENTIALS_VOIDED')->where('user_id', $this->owner->id)->count())->toBe(1)
        ->and(DB::table('security_events')->where('event_kind', 'CREDENTIALS_VOIDED')->count())->toBe(2);
});

it('reports the halt after the run when no pre-window Owner is certain (AU-25; criterion 5)', function () {
    $result = $this->incident->run(IncidentRuns::format($this->now->subDays(40)), OwnerTrail::operator());

    expect($result['halt'])->toContain('HALT (AU-25)')->toContain('incident decision outside the application');
});

it('runs through the console command, refusing without the maintenance mode (AU-21)', function () {
    $this->artisan('iam:key-compromise', ['--window-start' => $this->window, '--operator' => OwnerTrail::OPERATOR])
        ->expectsOutputToContain('completed; window start '.$this->window)
        ->assertExitCode(0);

    app()->maintenanceMode()->deactivate();
    $this->artisan('iam:key-compromise', ['--window-start' => $this->window, '--operator' => OwnerTrail::OPERATOR])
        ->expectsOutputToContain('maintenance mode is not active')
        ->assertExitCode(1);
});
