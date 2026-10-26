<?php

namespace Tests\Support;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Recovery\AccountHistory;
use App\Modules\IAM\Recovery\IncidentRuns;
use App\Modules\IAM\Recovery\OperatorIdentity;
use App\Modules\IAM\Recovery\RecoveryRefused;
use App\Modules\IAM\Services\CatalogueSync;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;

/**
 * The audit trail of AU-25 for the recovery tests (TASK-020): synthetic accounts whose role and active flag change at
 * given instants, each change written as the account commands write it — the account's image under AccountHistory's
 * keys in the event's before/after, the role row's `granted_by` and `granted_at` with it —, and the incident run's
 * steps the commands read. Synthetic data only; no production key, account or value.
 */
final class OwnerTrail
{
    public const OPERATOR = 'Operator Uji';

    public static function catalogue(): void
    {
        app(CatalogueSync::class)->run();
    }

    public static function operator(): OperatorIdentity
    {
        return OperatorIdentity::stated(self::OPERATOR);
    }

    /** An account created at an instant with a role, active, its creation audited — or not, to model a gap. */
    public static function account(string $role, CarbonImmutable $at, bool $audited = true, ?int $grantedBy = null, string $email = ''): User
    {
        $user = User::factory()->create($email === '' ? [] : ['email' => $email]);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => self::roleId($role), 'granted_by' => $grantedBy, 'granted_at' => $at]);

        if ($audited) {
            self::event($user, 'Test.AccountCreated', $at, null, AccountHistory::image($role, $grantedBy, $at->toIso8601ZuluString('microsecond'), true), $grantedBy);
        }

        return $user;
    }

    /** A role change at an instant, by an actor, as RV-04's command writes it. */
    public static function changeRole(User $user, string $role, CarbonImmutable $at, ?int $by = null): void
    {
        $before = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_id', $user->id)->first(['roles.code', 'user_roles.granted_by', 'user_roles.granted_at']);
        DB::table('user_roles')->where('user_id', $user->id)->update(['role_id' => self::roleId($role), 'granted_by' => $by, 'granted_at' => $at]);
        $grantedAt = is_string($before?->granted_at) ? CarbonImmutable::parse($before->granted_at)->toIso8601ZuluString('microsecond') : null;
        self::event($user, 'Test.RoleChanged', $at,
            [AccountHistory::ROLE => $before?->code, AccountHistory::ROLE_GRANTED_BY => $before?->granted_by, AccountHistory::ROLE_GRANTED_AT => $grantedAt],
            [AccountHistory::ROLE => $role, AccountHistory::ROLE_GRANTED_BY => $by, AccountHistory::ROLE_GRANTED_AT => $at->toIso8601ZuluString('microsecond')], $by);
    }

    /** A deactivation, or a reactivation, at an instant. */
    public static function setActive(User $user, bool $active, CarbonImmutable $at, ?int $by = null): void
    {
        DB::table('users')->where('id', $user->id)->update(['is_active' => $active, 'deactivated_at' => $active ? null : $at, 'deactivated_by' => $active ? null : $by]);
        self::event($user, $active ? 'Test.Activated' : 'Test.Deactivated', $at, [AccountHistory::ACTIVE => ! $active], [AccountHistory::ACTIVE => $active], $by);
    }

    /** The trail's record of the account's OWNER_RECOVERY link used at an instant (AU-14). */
    public static function recovered(User $user, CarbonImmutable $at): void
    {
        self::event($user, 'IAM.CredentialLinkConsumed', $at, null, null, null, ['purpose' => 'OWNER_RECOVERY', 'credential_link' => 1]);
    }

    /**
     * An audit event of the account at an instant.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $metadata
     */
    public static function event(User $user, string $action, CarbonImmutable $at, ?array $before, ?array $after, ?int $actor = null, array $metadata = []): void
    {
        DB::table('audit_events')->insert([
            'occurred_at' => $at,
            'recorded_at' => $at,
            'actor_user_id' => $actor,
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'entity_public_id' => $user->public_id,
            'authority_class' => $actor === null ? 'SYSTEM' : 'OWNER_ONLY',
            'before' => $before === null ? null : json_encode($before),
            'after' => $after === null ? null : json_encode($after),
            'metadata' => $metadata === [] ? null : json_encode($metadata),
        ]);
    }

    /** The incident run's steps as the command records them: its start, and its completion when given. */
    public static function run(CarbonImmutable $windowStart, CarbonImmutable $startedAt, ?CarbonImmutable $completedAt, string $id = '00000000-0000-4000-8000-000000000001'): string
    {
        $metadata = ['incident_run' => $id, 'window_start' => IncidentRuns::format($windowStart), 'operator' => ['host_account' => 'uji', 'name' => self::OPERATOR]];

        foreach (array_filter([IncidentRuns::STARTED => $startedAt, IncidentRuns::COMPLETED => $completedAt]) as $action => $at) {
            DB::table('audit_events')->insert(['occurred_at' => $at, 'recorded_at' => $at, 'action' => $action, 'authority_class' => 'SYSTEM', 'metadata' => json_encode($metadata)]);
        }

        return $id;
    }

    /** A step's outcome for a race: done, or its refusal with the message. */
    public static function attempt(Closure $step): string
    {
        try {
            $step();

            return 'done';
        } catch (RecoveryRefused $refused) {
            return 'refused: '.$refused->getMessage();
        }
    }

    /** A role change as RV-04's command makes it: LK-02 first, held across a barrier when given, then the account's row. */
    public static function racingRoleChange(int $userId, string $role, int $by, ?string $holdAt = null): string
    {
        return DB::transaction(function () use ($userId, $role, $by, $holdAt): string {
            app(AccountHistory::class)->lockOwnerRole();

            if ($holdAt !== null) {
                Barrier::await($holdAt);
                usleep(400_000);
            }

            $user = User::query()->lock('for no key update')->findOrFail($userId);
            self::changeRole($user, $role, CarbonImmutable::now(), $by);

            return 'changed';
        });
    }

    /** An activation as RV-05's command makes it: LK-02 first, held across a barrier when given, then the account's row. */
    public static function racingActivation(int $userId, int $by, ?string $holdAt = null): string
    {
        return DB::transaction(function () use ($userId, $by, $holdAt): string {
            app(AccountHistory::class)->lockOwnerRole();

            if ($holdAt !== null) {
                Barrier::await($holdAt);
                usleep(400_000);
            }

            $user = User::query()->lock('for no key update')->findOrFail($userId);
            self::setActive($user, true, CarbonImmutable::now(), $by);

            return 'activated';
        });
    }

    public static function roleId(string $role): int
    {
        $id = DB::table('roles')->where('code', $role)->value('id');

        return is_numeric($id) ? (int) $id : throw new \RuntimeException("Sync the catalogue before using the role {$role}.");
    }
}
