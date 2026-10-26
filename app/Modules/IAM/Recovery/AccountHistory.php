<?php

namespace App\Modules\IAM\Recovery;

use App\Modules\IAM\Enums\BuiltinRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The audit trail as AU-25 reads it (SECURITY AU-14, AU-25; RG-11; GAP-049): an account's OWNER role and active flag
 * at an instant, read from the business audit events of the account (`entity_type` user) and never stored. Every
 * command that assigns or changes an account's role or active flag — the installation step here, the OWNER
 * restoration, and TASK-024's account and role commands — carries the account's image under the keys below in its
 * event's before/after, the role row's `granted_by` and `granted_at` with it, so that the trail establishes every
 * pre-window Owner and the before-image of a role row's first change in the window (RV-18).
 */
final class AccountHistory
{
    /** The built-in OWNER role's code, read here only for the recovery path (AZ-02's operator use; RG-11). */
    public const OWNER = BuiltinRole::Owner->value;

    public const ROLE = 'role';

    public const ROLE_GRANTED_BY = 'role_granted_by';

    public const ROLE_GRANTED_AT = 'role_granted_at';

    public const ACTIVE = 'is_active';

    /**
     * The account's image as an audit before/after carries it.
     *
     * @return array<string, mixed>
     */
    public static function image(?string $role, ?int $grantedBy, mixed $grantedAt, bool $active): array
    {
        return [self::ROLE => $role, self::ROLE_GRANTED_BY => $grantedBy, self::ROLE_GRANTED_AT => $grantedAt, self::ACTIVE => $active];
    }

    /** The built-in OWNER role row's id; the catalogue sync writes it (TASK-011; DIR-094). */
    public function ownerRoleId(): int
    {
        $id = DB::table('roles')->where('code', self::OWNER)->where('is_builtin', true)->value('id');

        if (! is_numeric($id)) {
            throw new RuntimeException('The built-in OWNER role is missing: run authorization:sync-catalogue first (DIR-094).');
        }

        return (int) $id;
    }

    /** Locks the OWNER role row, the Owner set of LK-02, before any account row (RV-04, RV-18). */
    public function lockOwnerRole(): void
    {
        DB::table('roles')->where('id', $this->ownerRoleId())->lock('for no key update')->first(['id']);
    }

    /**
     * The account's role and its row now.
     *
     * @return array{role: ?string, granted_by: ?int, granted_at: ?string, active: bool}
     */
    public function current(int $userId): array
    {
        $role = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)->first(['roles.code', 'user_roles.granted_by', 'user_roles.granted_at']);

        return [
            'role' => is_string($role?->code) ? $role->code : null,
            'granted_by' => is_numeric($role?->granted_by) ? (int) $role->granted_by : null,
            'granted_at' => is_string($role?->granted_at) ? $role->granted_at : null,
            'active' => DB::table('users')->where('id', $userId)->value('is_active') === true,
        ];
    }

    /**
     * The account's audit events in their order.
     *
     * @return list<AuditEntry>
     */
    public function events(int $userId): array
    {
        return array_values(DB::table('audit_events')->where('entity_type', 'user')->where('entity_id', $userId)
            ->orderBy('occurred_at')->orderBy('id')
            ->get(['id', 'action', 'actor_user_id', 'occurred_at', 'before', 'after', 'metadata'])
            ->map(fn (object $row): AuditEntry => AuditEntry::fromRow($row))->all());
    }

    /**
     * The value an attribute held at an instant, from the last event at or before it that set the attribute; `known`
     * false when no such event exists.
     *
     * @return array{known: bool, value: mixed}
     */
    public function valueAt(int $userId, string $key, CarbonImmutable $at): array
    {
        $known = false;
        $value = null;

        foreach ($this->events($userId) as $event) {
            if ($event->occurredAt->greaterThan($at)) {
                break;
            }

            if (array_key_exists($key, $event->after)) {
                $known = true;
                $value = $event->after[$key];
            }
        }

        return ['known' => $known, 'value' => $value];
    }

    /** Whether the trail shows the account holding the OWNER role and active at the instant. */
    public function wasOwnerAt(int $userId, CarbonImmutable $at): bool
    {
        $role = $this->valueAt($userId, self::ROLE, $at);
        $active = $this->valueAt($userId, self::ACTIVE, $at);

        return $role['known'] && $role['value'] === self::OWNER && $active['known'] && $active['value'] === true;
    }

    /**
     * The pre-window Owners of a window start: the accounts the trail shows holding the OWNER role and active then.
     * Halts when no pre-window Owner can be established with certainty — an OWNER assignment without an audit event,
     * or the window reaching back before every OWNER assignment (AU-25; SM-21).
     *
     * @return list<int>
     *
     * @throws RecoveryHalted
     */
    public function preWindowOwners(CarbonImmutable $windowStart): array
    {
        $unaudited = [];
        $owners = [];

        foreach (self::ids(DB::table('users')->orderBy('id')->pluck('id')->all()) as $id) {
            if (! $this->ownerAssignmentsAudited($id)) {
                $unaudited[] = $id;
            } elseif ($this->wasOwnerAt($id, $windowStart)) {
                $owners[] = $id;
            }
        }

        if ($unaudited !== []) {
            $accounts = DB::table('users')->whereIn('id', $unaudited)->orderBy('id')->pluck('public_id')->implode(', ');

            throw RecoveryHalted::because("an OWNER assignment has no audit event, so no pre-window Owner is certain (accounts {$accounts})");
        }

        if ($owners === []) {
            throw RecoveryHalted::because('the window starts before every audited OWNER assignment, so no account is a pre-window Owner');
        }

        return $owners;
    }

    /**
     * The last event that set an attribute, with the before-image of the first change of it after an instant.
     *
     * @return array{last: ?AuditEntry, firstAfter: ?AuditEntry}
     */
    public function changes(int $userId, string $key, CarbonImmutable $after): array
    {
        $last = null;
        $firstAfter = null;

        foreach ($this->events($userId) as $event) {
            if (! array_key_exists($key, $event->after)) {
                continue;
            }

            $last = $event;

            if ($firstAfter === null && $event->occurredAt->greaterThan($after)) {
                $firstAfter = $event;
            }
        }

        return ['last' => $last, 'firstAfter' => $firstAfter];
    }

    /**
     * Every OWNER assignment of the account has its audit event: no event shows the role taken from OWNER while the
     * trail has not shown it given, and an account holding the OWNER role now has the trail showing it.
     */
    private function ownerAssignmentsAudited(int $userId): bool
    {
        $role = null;

        foreach ($this->events($userId) as $event) {
            if (($event->before[self::ROLE] ?? null) === self::OWNER && $role !== self::OWNER) {
                return false;
            }

            if (array_key_exists(self::ROLE, $event->after)) {
                $role = $event->after[self::ROLE];
            }
        }

        return $this->current($userId)['role'] !== self::OWNER || $role === self::OWNER;
    }

    /**
     * Identifiers read from a query, as integers.
     *
     * @param  array<mixed>  $values
     * @return list<int>
     */
    public static function ids(array $values): array
    {
        return array_values(array_map(fn (mixed $value): int => is_numeric($value) ? (int) $value : 0, $values));
    }
}
