<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\SecurityLog\SecurityEventKind;
use Illuminate\Support\Facades\DB;

/**
 * `IAM DeactivateUser` (ROUTE-123; INT-123; NX-02; RG-09; RG-10; AU-09; AU-24; RV-04; RV-05; M-21; M-22; M-42): one
 * transaction — the Owner set (LK-02) first when the account holds the OWNER role, the last active Owner refused
 * (RG-09) —, the account's grants revoked, its Google identity unlinked with reason DEACTIVATION and the acting Owner
 * as `unlinked_by`, the account inactive, its export requests withheld (RV-08), and last its unused credential links
 * revoked and every session deleted by `user_id`, a pending challenge's and an enrolment-only session's included.
 * Historical attribution stays (RG-10). The acting Owner's own account may be deactivated while another active Owner
 * remains — M-22's "the same Owner twice" —, RG-09 refusing the last.
 */
final class DeactivateUser
{
    public const TYPE = 'IAM.DeactivateUser';

    public function __construct(
        private readonly AccountAccess $access,
        private readonly GoogleIdentities $identities,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, User $target): Outcome
    {
        $ownerSet = $this->access->holdsOwnerRole($target->id);

        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, ['active' => false],
            function (CommandContext $context) use ($target, $ownerSet): CommandResult {
                $context->lock($this->access->locks($target->id, $ownerSet));
                $row = User::query()->findOrFail($target->id);
                $actor = $context->request->actorUserId;
                $now = $context->now();

                if ($row->getAttribute('is_active') !== true) {
                    throw Refused::rejected('RG-10', ['reason' => 'inactive']);
                }

                $owner = $this->access->holdsOwnerRole($row->id);

                if ($owner) {
                    // RV-04: the Owner set held before any account row; found only now, the action restarts with it.
                    $context->require($this->access->ownerSet());

                    if ($this->access->activeOwnersBesides($row->id) === 0) {
                        throw Refused::rejected('RG-09');
                    }
                }

                $before = $this->access->image($row->id);
                $revoked = $this->access->revokeGrants($row->id, $actor, $now);
                $unlinked = $this->identities->unlinkHeld($row, 'DEACTIVATION', $actor, $context->request->commandId, at: $now);
                DB::table('users')->where('id', $row->id)->update([
                    'is_active' => false, 'deactivated_at' => $now, 'deactivated_by' => $actor,
                    'lock_version' => AccountAccess::version($row) + 1, 'updated_at' => $now,
                ]);

                $this->audit->record(
                    action: 'IAM.UserDeactivated',
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $actor,
                    companyId: null,
                    entityType: 'user',
                    entityId: $row->id,
                    entityPublicId: $row->public_id,
                    commandId: $context->request->commandId,
                    change: AuditChange::updated($before, $this->access->image($row->id)),
                    metadata: ['revoked_capabilities' => $revoked['capabilities'], 'revoked_companies' => $revoked['companies']],
                );
                $this->access->withholdExports($row->id, $now);
                // LR-02: the token and session statements last.
                $this->access->revokeLinks($row->id, $now);
                $this->access->endSessions($row->id);

                $this->access->announce($context, SecurityEventKind::SessionInvalidated, $row->id, 'DEACTIVATION');

                if ($unlinked) {
                    $this->access->announce($context, SecurityEventKind::GoogleUnlinked, $row->id, 'DEACTIVATION',
                        $owner ? 'An OWNER-role account\'s Google identity was unlinked by its deactivation (AU-24; LG-07).' : null);
                }

                return new CommandResult('user', $row->id);
            });
    }
}
