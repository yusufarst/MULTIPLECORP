<?php

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Recovery\IncidentRuns;
use App\Modules\IAM\Recovery\OwnerRecoveryLinks;
use App\Modules\IAM\Recovery\OwnerRestoration;
use App\Modules\IAM\Recovery\RecoveryHalted;
use App\Modules\IAM\Recovery\RecoveryRefused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\OwnerTrail;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-020; SECURITY AU-25; RV-18; M-46; SM-21; criteria 4 and 5): the OWNER-restoration command restores a
// pre-window Owner's OWNER role or active flag removed during the window, to its window-start values and nothing
// else, once per account per run; it refuses every other account, another window start, a run not completed or whose
// recovery has ended, and an account touched since the closing sweep, writing nothing; the end of recovery is
// recorded only when no unreviewed OWNER-role account remains; each halt names the Owner's incident decision.

uses(RefreshDatabase::class);

beforeEach(function () {
    OwnerTrail::catalogue();
    $this->now = CarbonImmutable::now();
    $this->windowStart = $this->now->subDays(10);
    $this->window = IncidentRuns::format($this->windowStart);
    $this->owner = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'ratna@contoh.test');
    $this->other = OwnerTrail::account('OWNER', $this->now->subDays(30), email: 'bayu@contoh.test');
    $this->restoration = app(OwnerRestoration::class);
});

function completedRun(object $test): string
{
    return OwnerTrail::run($test->windowStart, $test->now->subHour(), $test->now->subMinutes(30));
}

function restoreOwner(object $test, User $account, ?string $window = null): array
{
    return $test->restoration->restore($account->email, $window ?? $test->window, OwnerTrail::operator());
}

function roleOf(User $user): ?string
{
    $code = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_id', $user->id)->value('roles.code');

    return is_string($code) ? $code : null;
}

it('restores the OWNER role removed during the window to its window-start row, once per run (RV-18; M-46; criterion 4)', function () {
    $original = DB::table('user_roles')->where('user_id', $this->owner->id)->first();
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    $password = $this->owner->fresh()?->password;
    completedRun($this);

    $restored = restoreOwner($this, $this->owner);

    $row = DB::table('user_roles')->where('user_id', $this->owner->id)->first();
    expect(roleOf($this->owner))->toBe('OWNER')
        ->and($row?->granted_by)->toBe($original?->granted_by)
        ->and(CarbonImmutable::parse((string) $row?->granted_at)->getTimestamp())->toBe(CarbonImmutable::parse((string) $original?->granted_at)->getTimestamp())
        ->and($this->owner->fresh()?->password)->toBe($password)
        ->and($restored['reversed'][0]['attribute'])->toBe('OWNER role')
        ->and($restored['reversed'][0]['removed_by'])->toBe($this->other->public_id);

    $audit = DB::table('audit_events')->where('action', IncidentRuns::RESTORED)->first();
    $metadata = json_decode((string) $audit?->metadata, true);
    expect($audit?->actor_user_id)->toBeNull()
        ->and($audit?->authority_class)->toBe('SYSTEM')
        ->and($metadata['window_start'])->toBe($this->window)
        ->and($metadata['operator']['name'])->toBe(OwnerTrail::OPERATOR)
        ->and(DB::table('security_events')->where('event_kind', 'OWNER_RESTORED')->where('user_id', $this->owner->id)->count())->toBe(1)
        ->and(User::query()->count())->toBe(2)
        ->and(DB::table('credential_tokens')->count())->toBe(0);

    // A second restoration in the run, even after the account was removed once more, is refused (M-46).
    expect(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryRefused::class, 'restored already');
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', CarbonImmutable::now(), $this->other->id);
    expect(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryRefused::class)
        ->and(DB::table('audit_events')->where('action', IncidentRuns::RESTORED)->count())->toBe(1);
});

it('restores the role row\'s window-start granter and time from each column\'s first change, across partial images (RV-18)', function () {
    $granter = OwnerTrail::account('OWNER', $this->now->subDays(40));
    $owner = OwnerTrail::account('OWNER', $this->now->subDays(30), grantedBy: $granter->id, email: 'gita@contoh.test');
    $original = DB::table('user_roles')->where('user_id', $owner->id)->first();
    // The first change keeps the granter, so its image has the role alone; the second changes the granter alone.
    DB::table('user_roles')->where('user_id', $owner->id)->update(['role_id' => OwnerTrail::roleId('ADMIN_OPERASIONAL')]);
    OwnerTrail::event($owner, 'Test.RoleChanged', $this->now->subDays(5), ['role' => 'OWNER'], ['role' => 'ADMIN_OPERASIONAL'], $this->other->id);
    DB::table('user_roles')->where('user_id', $owner->id)->update(['granted_by' => $this->other->id]);
    OwnerTrail::event($owner, 'Test.RoleGranterChanged', $this->now->subDays(4), ['role_granted_by' => $granter->id], ['role_granted_by' => $this->other->id], $this->other->id);
    completedRun($this);

    restoreOwner($this, $owner);

    $row = DB::table('user_roles')->where('user_id', $owner->id)->first();
    expect(roleOf($owner))->toBe('OWNER')
        ->and($row?->granted_by)->toBe($granter->id)
        ->and(CarbonImmutable::parse((string) $row?->granted_at)->getTimestamp())->toBe(CarbonImmutable::parse((string) $original?->granted_at)->getTimestamp());
});

it('lets a later run supersede an earlier one, so no earlier run reopens once the later run\'s recovery ends (INFRASTRUCTURE §7.3 step 7)', function () {
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    OwnerTrail::run($this->windowStart, $this->now->subHours(3), $this->now->subHours(2));
    $corrected = OwnerTrail::run($this->now->subDays(9), $this->now->subHour(), $this->now->subMinutes(30), '00000000-0000-4000-8000-000000000002');
    DB::table('audit_events')->insert(['occurred_at' => $this->now->subMinutes(5), 'action' => IncidentRuns::ENDED, 'authority_class' => 'SYSTEM', 'metadata' => json_encode(['incident_run' => $corrected])]);

    expect(app(IncidentRuns::class)->latestOpen())->toBeNull()
        ->and(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryRefused::class, 'No incident run is open');
});

it('restores an active flag cleared during the window, clearing the deactivation\'s columns (RV-18)', function () {
    OwnerTrail::setActive($this->owner, false, $this->now->subDays(4), $this->other->id);
    completedRun($this);

    restoreOwner($this, $this->owner);

    $row = DB::table('users')->where('id', $this->owner->id)->first(['is_active', 'deactivated_at', 'deactivated_by']);
    expect($row?->is_active)->toBeTrue()->and($row?->deactivated_at)->toBeNull()->and($row?->deactivated_by)->toBeNull()
        ->and(roleOf($this->owner))->toBe('OWNER');
});

it('refuses, writing nothing, every account that was not a pre-window Owner or has nothing removed in the window (M-46)', function () {
    $windowOwner = OwnerTrail::account('ADMIN_OPERASIONAL', $this->now->subDays(30));
    OwnerTrail::changeRole($windowOwner, 'OWNER', $this->now->subDays(6), $this->other->id);
    OwnerTrail::changeRole($windowOwner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    $inactiveAtStart = OwnerTrail::account('OWNER', $this->now->subDays(30));
    OwnerTrail::setActive($inactiveAtStart, false, $this->now->subDays(20), $this->other->id);
    $givenBack = OwnerTrail::account('OWNER', $this->now->subDays(30));
    OwnerTrail::changeRole($givenBack, 'ADMIN_OPERASIONAL', $this->now->subDays(6), $this->other->id);
    OwnerTrail::changeRole($givenBack, 'OWNER', $this->now->subDays(5), $this->other->id);
    $removedBefore = OwnerTrail::account('OWNER', $this->now->subDays(30));
    OwnerTrail::changeRole($removedBefore, 'ADMIN_OPERASIONAL', $this->now->subDays(20), $this->other->id);
    completedRun($this);
    $events = DB::table('audit_events')->count();

    expect(fn () => restoreOwner($this, $windowOwner))->toThrow(RecoveryRefused::class, 'did not hold the OWNER role')
        ->and(fn () => restoreOwner($this, $inactiveAtStart))->toThrow(RecoveryRefused::class, 'did not hold the OWNER role')
        ->and(fn () => restoreOwner($this, $removedBefore))->toThrow(RecoveryRefused::class, 'did not hold the OWNER role')
        ->and(fn () => restoreOwner($this, $givenBack))->toThrow(RecoveryRefused::class, 'Nothing to restore')
        ->and(fn () => restoreOwner($this, $this->other))->toThrow(RecoveryRefused::class, 'Nothing to restore')
        ->and(DB::table('audit_events')->count())->toBe($events)
        ->and(DB::table('security_events')->where('event_kind', 'OWNER_RESTORED')->count())->toBe(0)
        ->and(roleOf($windowOwner))->toBe('ADMIN_OPERASIONAL');
});

it('refuses another window start, a run not completed and a run whose recovery has ended (AU-25; M-46)', function () {
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    $run = OwnerTrail::run($this->windowStart, $this->now->subHour(), null);

    expect(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryRefused::class, 'has not completed');

    DB::table('audit_events')->insert(['occurred_at' => $this->now->subMinutes(30), 'action' => IncidentRuns::COMPLETED, 'authority_class' => 'SYSTEM', 'metadata' => json_encode(['incident_run' => $run])]);

    expect(fn () => restoreOwner($this, $this->owner, IncidentRuns::format($this->windowStart->subDay())))->toThrow(RecoveryRefused::class, 'not the one incident run')
        ->and(fn () => restoreOwner($this, $this->owner, 'kemarin'))->toThrow(RecoveryRefused::class, 'ISO 8601');

    DB::table('audit_events')->insert(['occurred_at' => $this->now->subMinutes(5), 'action' => IncidentRuns::ENDED, 'authority_class' => 'SYSTEM', 'metadata' => json_encode(['incident_run' => $run])]);

    expect(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryRefused::class, 'No incident run is open')
        ->and(roleOf($this->owner))->toBe('ADMIN_OPERASIONAL');
});

it('refuses an account touched since the closing sweep — an audited change, a link, a session (RV-18; M-46)', function (string $touch, string $refusal) {
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    completedRun($this);

    match ($touch) {
        'audited change' => OwnerTrail::event($this->owner, 'Test.GrantChanged', $this->now->subMinutes(10), null, ['grant' => 'x'], $this->other->id),
        'link' => DB::table('credential_tokens')->insert(['user_id' => $this->owner->id, 'purpose' => 'RESET', 'issued_by' => $this->other->id, 'token_hash' => 'h1', 'issued_at' => $this->now->subMinutes(10), 'expires_at' => $this->now->addHour()]),
        'session' => DB::table('sessions')->insert(['id' => 'sesi-baru', 'user_id' => $this->owner->id, 'payload' => '', 'last_activity' => time()]),
    };

    expect(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryRefused::class, $refusal)
        ->and(roleOf($this->owner))->toBe('ADMIN_OPERASIONAL')
        ->and(DB::table('audit_events')->where('action', IncidentRuns::RESTORED)->count())->toBe(0);
})->with([
    'an audited change' => ['audited change', 'Test.GrantChanged'],
    'a credential link' => ['link', 'credential link'],
    'a session' => ['session', 'session'],
]);

it('halts, naming the Owner\'s incident decision, when the window starts before every OWNER assignment (AU-25; SM-21; criterion 5)', function () {
    $early = $this->now->subDays(40);
    OwnerTrail::run($early, $this->now->subHour(), $this->now->subMinutes(30));
    $window = IncidentRuns::format($early);

    expect(fn () => restoreOwner($this, $this->owner, $window))->toThrow(RecoveryHalted::class, 'incident decision outside the application')
        ->and(fn () => app(OwnerRecoveryLinks::class)->issue($this->owner->email, 'Uji', OwnerTrail::operator()))->toThrow(RecoveryHalted::class)
        ->and(fn () => $this->restoration->endRecovery($window, OwnerTrail::operator()))->toThrow(RecoveryHalted::class)
        ->and(DB::table('credential_tokens')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', IncidentRuns::ENDED)->count())->toBe(0);
});

it('halts when an OWNER assignment has no audit event, so no pre-window Owner is certain (AU-25; SM-21; criterion 5)', function () {
    OwnerTrail::account('OWNER', $this->now->subDays(30), audited: false);
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    completedRun($this);

    expect(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryHalted::class, 'has no audit event')
        ->and(fn () => app(OwnerRecoveryLinks::class)->issue($this->other->email, 'Uji', OwnerTrail::operator()))->toThrow(RecoveryHalted::class, 'first to the Owner\'s incident decision')
        ->and(roleOf($this->owner))->toBe('ADMIN_OPERASIONAL');
});

it('records the end of recovery only when every active Owner is a pre-window Owner or confirmed by a recovered one (AU-14; AU-25; criterion 4)', function () {
    $windowOwner = OwnerTrail::account('ADMIN_OPERASIONAL', $this->now->subDays(30), email: 'citra@contoh.test');
    OwnerTrail::changeRole($windowOwner, 'OWNER', $this->now->subDays(5), $this->other->id);
    completedRun($this);

    expect(fn () => $this->restoration->endRecovery($this->window, OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'citra@contoh.test');

    OwnerTrail::recovered($this->other, $this->now->subMinutes(10));
    app(OwnerRecoveryLinks::class)->issue('citra@contoh.test', 'Dikonfirmasi dalam tinjauan', OwnerTrail::operator(), confirmerEmail: 'bayu@contoh.test');
    $run = $this->restoration->endRecovery($this->window, OwnerTrail::operator());

    expect($run->id)->not->toBe('')
        ->and(DB::table('audit_events')->where('action', IncidentRuns::ENDED)->whereNull('actor_user_id')->count())->toBe(1)
        ->and(fn () => restoreOwner($this, $this->owner))->toThrow(RecoveryRefused::class, 'No incident run is open')
        ->and(fn () => $this->restoration->endRecovery($this->window, OwnerTrail::operator()))->toThrow(RecoveryRefused::class, 'No incident run is open');
});

it('runs through the console command, printing the removal it reversed (AU-25; GAP-048)', function () {
    OwnerTrail::changeRole($this->owner, 'ADMIN_OPERASIONAL', $this->now->subDays(5), $this->other->id);
    completedRun($this);

    $this->artisan('iam:restore-owner', ['email' => $this->owner->email, '--window-start' => $this->window, '--operator' => OwnerTrail::OPERATOR])
        ->expectsOutputToContain("by {$this->other->public_id}")
        ->assertExitCode(0);
    $this->artisan('iam:restore-owner', ['email' => $this->owner->email, '--window-start' => $this->window])
        ->expectsOutputToContain('--operator')
        ->assertExitCode(1);
});
