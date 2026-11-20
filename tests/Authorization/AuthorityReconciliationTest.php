<?php

use App\Modules\IAM\Models\Role;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\CommandLog;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthorityReconciliation;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-069; SECURITY_TEST_MATRIX SM-05; H8-05; PERMISSIONS_MATRIX OD-16, AZ-10, RG-02; HO-15; criterion 3): the
// OD-16 reconciliation that runs after every authorization and integration test (tests/Pest.php) finds a seeded
// anomaly of each kind — an OWNER_ONLY capability in a user grant and in another role's capabilities, an OWNER_ONLY
// event by an account without the OWNER role, a committed command's event recorded below the class its capability
// carries —, the first two also security-logged as AUTHORIZATION_ANOMALY by the resolver (EffectiveCapabilitiesTest
// holds the resolver's side); a refused command's SYSTEM record with a human actor is no anomaly (ST-03; SQ-23). Each
// seed is rolled back to its savepoint, so the reconciliation after the test is clean.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view']);
    $this->seeded = function (Closure $seed): array {
        DB::beginTransaction();

        try {
            $seed();

            return AuthorityReconciliation::anomalies();
        } finally {
            DB::rollBack();
        }
    };
});

it('returns no row for the suite\'s own accounts and records', function () {
    expect(AuthorityReconciliation::anomalies())->toBe([]);
});

it('finds an OWNER_ONLY capability in a user grant, which the resolver ignores and security-logs (RG-02; OD-16)', function () {
    $anomalies = ($this->seeded)(function (): void {
        Authorization::grant($this->admin, 'projects.force_complete');
        expect(app(EffectiveCapabilities::class)->for($this->admin->id)->has('projects.force_complete'))->toBeFalse();
    });

    expect($anomalies)->toBe(["OWNER_ONLY_IN_USER_GRANT: {$this->admin->id} projects.force_complete"])
        ->and(DB::table('security_events')->where('event_kind', 'AUTHORIZATION_ANOMALY')->where('user_id', $this->admin->id)->where('reason_code', 'OWNER_ONLY_IN_USER_GRANT')->exists())->toBeTrue();
});

it('finds an OWNER_ONLY capability in the role_capabilities of a role other than the built-in OWNER (RG-02; OD-16)', function () {
    $anomalies = ($this->seeded)(function () use (&$role): void {
        $role = Role::factory()->create();
        DB::table('role_capabilities')->insert(['role_id' => $role->id, 'capability_id' => DB::table('capabilities')->where('code', 'users.manage')->value('id'), 'recorded_at' => now()]);
    });

    expect($anomalies)->toBe(["OWNER_ONLY_IN_ROLE: {$role->id} users.manage"]);
});

it('finds an OWNER_ONLY event by an account without the OWNER role (OD-16)', function () {
    $anomalies = ($this->seeded)(fn () => AuditEvent::factory()->create(['actor_user_id' => $this->admin->id, 'company_id' => $this->company->id, 'action' => 'PRJ.ProjectForceCompleted', 'authority_class' => 'OWNER_ONLY']));

    expect($anomalies)->toBe(["OWNER_ONLY_EVENT_WITHOUT_OWNER: {$this->admin->id} PRJ.ProjectForceCompleted"]);
});

it('finds a committed command\'s event recorded below the class of the capability it exercises, and an Owner\'s own OWNER_ONLY event as none (AZ-10)', function () {
    $owner = Authorization::owner();
    $anomalies = ($this->seeded)(function () use ($owner, &$event): void {
        $command = CommandLog::factory()->create(['command_type' => 'PRJ.CancelProject', 'actor_user_id' => $this->admin->id, 'company_id' => $this->company->id]);
        $event = AuditEvent::factory()->create(['actor_user_id' => $this->admin->id, 'company_id' => $this->company->id, 'action' => 'PRJ.ProjectCancelled', 'authority_class' => 'ADM', 'command_id' => $command->command_id]);
        AuditEvent::factory()->create(['actor_user_id' => $owner->id, 'company_id' => $this->company->id, 'action' => 'PRJ.ProjectForceCompleted', 'authority_class' => 'OWNER_ONLY']);
    });

    expect(AuthorityReconciliation::capabilities())->toHaveKey('PRJ.CancelProject', 'projects.cancel')
        ->and($anomalies)->toBe(["AUTHORITY_CLASS_BELOW_EXERCISED: audit event {$event->id} PRJ.ProjectCancelled recorded ADM, its command PRJ.CancelProject exercising projects.cancel (ADM_PLUS)"]);
});

it('reads a refused command\'s SYSTEM record with a human actor as a refusal, not an anomaly (HO-15; ST-03; SQ-23)', function () {
    $anomalies = ($this->seeded)(function (): void {
        $command = CommandLog::factory()->create(['command_type' => 'INV.ApplyCount', 'actor_user_id' => $this->admin->id, 'company_id' => $this->company->id, 'state' => 'CONFLICT', 'outcome_code' => 'ST-03']);
        AuditEvent::factory()->create(['actor_user_id' => $this->admin->id, 'company_id' => $this->company->id, 'action' => 'INV.CountStale', 'authority_class' => 'SYSTEM', 'command_id' => $command->command_id]);
    });

    expect($anomalies)->toBe([]);
});
