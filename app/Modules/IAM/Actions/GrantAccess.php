<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Data\AccessPreview;
use App\Modules\IAM\Data\CapabilityCatalogue;
use App\Modules\IAM\Enums\BuiltinRole;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * `IAM GrantRole · GrantCompany · GrantCapability` (ROUTE-124; INT-124; NX-02; RG-02–RG-09; AU-18; RV-02–RV-04; RV-08;
 * RV-11; M-20; M-45), one command — "Simpan Akses" after "Pratinjau Akses" — over the account's role, company grants
 * and capability grants as the Owner leaves them (`permissions.manage`):
 * - never on the acting Owner's own account (OD-02), against the `lock_version` the editor was opened with (IP-03);
 * - a role change of an OWNER-role account, or to OWNER, under the Owner set (LK-02) first, the last active Owner's
 *   demotion refused (RG-09); a promotion to OWNER revokes the account's individual grants, which the role's
 *   capabilities cover — a reading within the approved text, so that a later demotion restores none silently;
 * - an OWNER_ONLY capability refused for any account (RG-02); a capability added without its prerequisites refused,
 *   while a held one whose prerequisite is removed stays granted and disabled (RG-06); a capability that needs a
 *   company refused without one; an ADM_PLUS capability added or removed only with a reason (RG-08);
 * - the account's export requests its grants no longer cover withheld (RV-08), and, when the change makes an active
 *   account high-risk, its sessions ended and — without confirmed TOTP — its password made unusable and one RESET
 *   link issued, shown once (AU-18; RV-11). A change on an inactive account changes nothing until its activation.
 */
final class GrantAccess
{
    public const TYPE = 'IAM.GrantAccess';

    /** @var array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null */
    private ?array $issued = null;

    public function __construct(
        private readonly AccountAccess $access,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  list<int>  $companies  company ids
     * @param  list<string>  $capabilities  capability codes
     */
    public function run(string $commandId, int $actorUserId, User $target, string $role, array $companies, array $capabilities, int $lockVersion, ?string $reason): Outcome
    {
        $companies = array_values(array_unique($companies));
        sort($companies);
        $capabilities = array_values(array_unique($capabilities));
        sort($capabilities);
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        $ownerSet = $this->access->holdsOwnerRole($target->id) || AccountAccess::isOwnerRole($role);
        $unusable = $target->getAttribute('two_factor_confirmed_at') === null ? Hash::make(Str::random(64)) : null;
        $payload = ['role' => $role, 'companies' => $companies, 'capabilities' => $capabilities, 'lock_version' => $lockVersion, 'reason' => $reason];

        return $this->access->run($commandId, self::TYPE, $actorUserId, 'permissions.manage', $target->public_id, $payload,
            function (CommandContext $context) use ($target, $role, $companies, $capabilities, $lockVersion, $reason, $ownerSet, $unusable): CommandResult {
                $this->issued = null;
                $context->lock($this->access->locks($target->id, $ownerSet));
                $row = User::query()->findOrFail($target->id);
                $actor = $context->request->actorUserId;
                $now = $context->now();

                if ($row->id === $actor) {
                    throw Refused::rejected('OD-02', ['reason' => 'self']);
                }

                if (AccountAccess::version($row) !== $lockVersion) {
                    throw Refused::conflict('ST-01', ['lock_version' => AccountAccess::version($row)]);
                }

                $roleBefore = $this->access->roleOf($row->id);
                $roleChanges = $roleBefore !== $role;

                if ($roleChanges && (AccountAccess::isOwnerRole($roleBefore) || AccountAccess::isOwnerRole($role))) {
                    $context->require($this->access->ownerSet());

                    if (AccountAccess::isOwnerRole($roleBefore) && $row->getAttribute('is_active') === true && $this->access->activeOwnersBesides($row->id) === 0) {
                        throw Refused::rejected('RG-09');
                    }
                }

                $owner = AccountAccess::isOwnerRole($role);
                $held = $this->access->grants($row->id);
                [$capabilitiesAfter, $companiesAfter] = $owner ? [[], []] : [$capabilities, $companies];
                $this->refuse($held, $capabilitiesAfter, $companiesAfter, $reason);
                $wasHighRisk = $this->access->highRisk($row->id);
                $before = $this->access->image($row->id);
                $line = ['user_company_grants' => 0, 'user_capability_grants' => 0];

                if ($roleChanges) {
                    $roleId = DB::table('roles')->where('code', $role)->where('is_builtin', true)->value('id');

                    // One role per account (RG-01): replaced in place, or written for an account that holds none.
                    if ($roleBefore === null) {
                        DB::table('user_roles')->insert(['user_id' => $row->id, 'role_id' => $roleId, 'granted_by' => $actor, 'granted_at' => $now,
                            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now]);
                    } else {
                        DB::table('user_roles')->where('user_id', $row->id)->update(['role_id' => $roleId, 'granted_by' => $actor, 'granted_at' => $now]);
                    }
                }

                foreach (array_diff($companiesAfter, $held['companies']) as $company) {
                    DB::table('user_company_grants')->insert(['user_id' => $row->id, 'company_id' => $company, 'granted_by' => $actor, 'granted_at' => $now,
                        'command_id' => $context->request->commandId, 'command_line_no' => ++$line['user_company_grants'], 'recorded_at' => $now]);
                }

                DB::table('user_company_grants')->where('user_id', $row->id)->whereNull('revoked_at')->whereIn('company_id', array_values(array_diff($held['companies'], $companiesAfter)))
                    ->update(['revoked_at' => $now, 'revoked_by' => $actor]);

                foreach (array_diff($capabilitiesAfter, $held['capabilities']) as $code) {
                    DB::table('user_capability_grants')->insert(['user_id' => $row->id, 'capability_id' => DB::table('capabilities')->where('code', $code)->value('id'),
                        'granted_by' => $actor, 'granted_at' => $now, 'reason' => $reason,
                        'command_id' => $context->request->commandId, 'command_line_no' => ++$line['user_capability_grants'], 'recorded_at' => $now]);
                }

                $removed = array_values(array_diff($held['capabilities'], $capabilitiesAfter));
                DB::table('user_capability_grants')->where('user_id', $row->id)->whereNull('revoked_at')
                    ->whereIn('capability_id', DB::table('capabilities')->whereIn('code', $removed)->select('id'))->update(['revoked_at' => $now, 'revoked_by' => $actor]);

                $grantsChanged = $capabilitiesAfter !== $held['capabilities'] || $companiesAfter !== $held['companies'];

                if (! $roleChanges && ! $grantsChanged) {
                    throw Refused::rejected('RG-05', ['reason' => 'unchanged']);
                }

                DB::table('users')->where('id', $row->id)->update(['lock_version' => $lockVersion + 1, 'updated_at' => $now]);
                $this->record($context, $row, $roleChanges ? $before : null, $held, $capabilitiesAfter, $companiesAfter, $reason);

                // RV-08: what the change no longer covers, withheld in its own transaction.
                if ($roleChanges || $removed !== [] || array_diff($held['companies'], $companiesAfter) !== []) {
                    $this->access->withholdExports($row->id, $now);
                }

                // RV-11: AU-18's test as the change leaves the account; an inactive account waits for its activation.
                if ($row->getAttribute('is_active') === true && ! $wasHighRisk && $this->access->highRisk($row->id)) {
                    if ($row->getAttribute('two_factor_confirmed_at') === null) {
                        if ($unusable === null) {
                            throw Refused::rejected('RV-11', ['reason' => 'retry']);
                        }

                        $link = $this->access->reissue($row, $actor, $unusable, $now, clearTotp: false);
                        $this->access->recordLink($context, $row, $link, 'RESET', 'HIGH_RISK_GRANT');
                        $this->issued = [...$link, 'purpose' => 'RESET'];
                    }

                    $this->access->endSessions($row->id);
                }

                return new CommandResult('user', $row->id);
            });
    }

    /**
     * The RESET link a high-risk change issued, for the Owner's one view; none on a replay (CI-05).
     *
     * @return array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null
     */
    public function issued(): ?array
    {
        return $this->issued;
    }

    /**
     * RG-02, RG-06 and RG-08 at grant time.
     *
     * @param  array{capabilities: list<string>, companies: list<int>}  $held
     * @param  list<string>  $capabilities
     * @param  list<int>  $companies
     */
    private function refuse(array $held, array $capabilities, array $companies, ?string $reason): void
    {
        foreach ($capabilities as $code) {
            if (! CapabilityCatalogue::has($code) || CapabilityCatalogue::isOwnerOnly($code)) {
                throw Refused::rejected('RG-02', ['capability' => $code]);
            }
        }

        $preview = new AccessPreview($held['capabilities'], array_map('strval', $held['companies']), $capabilities, array_map('strval', $companies));
        $missing = $preview->addedWithoutPrerequisite();

        if ($missing !== []) {
            throw Refused::rejected('RG-06', ['capabilities' => $missing]);
        }

        $added = array_diff($capabilities, $held['capabilities']);

        if ($companies === [] && array_filter($added, fn (string $code): bool => CapabilityCatalogue::needsCompany($code)) !== []) {
            throw Refused::rejected('RG-06', ['reason' => 'company']);
        }

        $special = array_filter([...$added, ...array_diff($held['capabilities'], $capabilities)], fn (string $code): bool => CapabilityCatalogue::classOf($code) === AuthorityClass::AdmPlus);

        if ($special !== [] && $reason === null) {
            throw Refused::rejected('RG-08', ['field' => 'reason']);
        }
    }

    /**
     * The change's audit events, of the account (`entity_type` `user`): the role with the account's image under
     * TASK-020's keys (AU-25; DIR-097 R1), the grants with their before and after and the reason (RG-11).
     *
     * @param  array<string, mixed>|null  $imageBefore
     * @param  array{capabilities: list<string>, companies: list<int>}  $held
     * @param  list<string>  $capabilities
     * @param  list<int>  $companies
     */
    private function record(CommandContext $context, User $row, ?array $imageBefore, array $held, array $capabilities, array $companies, ?string $reason): void
    {
        $write = fn (string $action, AuditChange $change, ?string $because = null) => $this->audit->record(
            action: $action,
            authorityClass: AuthorityClass::OwnerOnly,
            actorUserId: $context->request->actorUserId,
            companyId: null,
            entityType: 'user',
            entityId: $row->id,
            entityPublicId: $row->public_id,
            reason: $because,
            commandId: $context->request->commandId,
            change: $change,
        );

        if ($imageBefore !== null) {
            $write('IAM.RoleChanged', AuditChange::updated($imageBefore, $this->access->image($row->id)));
        }

        if ($capabilities !== $held['capabilities'] || $companies !== $held['companies']) {
            $write('IAM.GrantsChanged', AuditChange::updated(['capabilities' => $held['capabilities'], 'companies' => $held['companies']], ['capabilities' => $capabilities, 'companies' => $companies]), $reason);
        }
    }
}
