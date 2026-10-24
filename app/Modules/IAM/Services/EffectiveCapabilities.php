<?php

namespace App\Modules\IAM\Services;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Data\CompanyScope;
use App\Modules\IAM\Enums\BuiltinRole;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The effective-capability and company-scope resolver of SECURITY EN-02 (PERMISSIONS_MATRIX RG-02, RG-03,
 * RG-06; AZ-02–AZ-04): every policy, gate, query class, job and action asks it. Effective capabilities are
 * the role's `role_capabilities` plus the active `user_capability_grants`, each kept only while its
 * prerequisites are effective and, for a command or a GRANT prerequisite, while a company is in scope; an
 * OWNER_ONLY capability counts only through the built-in OWNER role, and one found elsewhere is ignored and
 * security-logged as an authorization anomaly (OD-16). Read from the database on first use in a request or
 * job and memoized for it only — the container scopes this service —, never cached across requests (AZ-03).
 * The only reader of role identity besides the uses AZ-02 lists.
 */
final class EffectiveCapabilities
{
    /** @var array<int, Authority> */
    private array $memo = [];

    public function __construct(private readonly SecurityEventRecorder $events) {}

    /** The account's authority, memoized for this request or job. */
    public function for(Authenticatable|int $user): Authority
    {
        $id = is_int($user) ? $user : $user->getAuthIdentifier();

        if (! is_int($id)) {
            throw new LogicException('An account is identified by its integer id.');
        }

        return $this->memo[$id] ??= $this->read($id);
    }

    /**
     * The account's authority read again, as a command does after it locks the account's row (RV-01).
     */
    public function fresh(int $userId): Authority
    {
        return $this->memo[$userId] = $this->read($userId);
    }

    /** Forgets every memoized authority. */
    public function forget(): void
    {
        $this->memo = [];
    }

    private function read(int $userId): Authority
    {
        $active = DB::table('users')->where('id', $userId)->value('is_active');

        if ($active !== true) {
            return Authority::none($userId);
        }

        // One query for both sources: the role's capabilities, with whether the role is the built-in OWNER,
        // and the account's active individual grants.
        $rows = DB::query()
            ->fromSub(
                DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->join('role_capabilities', 'role_capabilities.role_id', '=', 'roles.id')
                    ->join('capabilities', 'capabilities.id', '=', 'role_capabilities.capability_id')
                    ->where('user_roles.user_id', $userId)
                    ->selectRaw("capabilities.code, capabilities.authority_class, 'role' as source, (roles.is_builtin and roles.code = ?) as owner_role", [BuiltinRole::Owner->value])
                    ->unionAll(
                        DB::table('user_capability_grants')
                            ->join('capabilities', 'capabilities.id', '=', 'user_capability_grants.capability_id')
                            ->where('user_capability_grants.user_id', $userId)
                            ->whereNull('user_capability_grants.revoked_at')
                            ->selectRaw("capabilities.code, capabilities.authority_class, 'grant' as source, false as owner_role"),
                    ),
                'held',
            )
            ->get();

        $granted = [];
        $anomalies = [];

        foreach ($rows as $row) {
            $code = $row->code;

            if (! is_string($code) || ! CapabilityCatalogue::has($code)) {
                continue;
            }

            if (CapabilityCatalogue::isOwnerOnly($code) && $row->owner_role !== true) {
                $anomalies[$code] = $row->source === 'grant' ? 'OWNER_ONLY_IN_USER_GRANT' : 'OWNER_ONLY_IN_ROLE';

                continue;
            }

            $granted[$code] = true;
        }

        foreach ($anomalies as $code => $reason) {
            $this->events->record(SecurityEventKind::AuthorizationAnomaly, userId: $userId, reasonCode: $reason, details: ['capability' => $code]);
        }

        $scope = isset($granted['scope.all_companies'])
            ? CompanyScope::everyCompany()
            : CompanyScope::of(DB::table('user_company_grants')->where('user_id', $userId)->whereNull('revoked_at')->pluck('company_id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : throw new LogicException('A company id is an integer.')));

        $effective = [];

        foreach (array_keys($granted) as $code) {
            if ($this->effective($code, $granted, $scope, [])) {
                $effective[] = $code;
            }
        }

        sort($effective);

        return new Authority($userId, true, $effective, $scope);
    }

    /**
     * Whether a held capability is effective: its prerequisites effective in turn, and a company in scope
     * where it needs one (RG-06; CS-05).
     *
     * @param  array<string, true>  $granted
     * @param  array<string, true>  $visiting
     */
    private function effective(string $code, array $granted, CompanyScope $scope, array $visiting): bool
    {
        if (! isset($granted[$code]) || isset($visiting[$code])) {
            return false;
        }

        if (CapabilityCatalogue::needsCompany($code) && $scope->isEmpty()) {
            return false;
        }

        foreach (CapabilityCatalogue::prerequisites($code) as $prerequisite) {
            if (! $this->effective($prerequisite, $granted, $scope, [...$visiting, $code => true])) {
                return false;
            }
        }

        return true;
    }
}
