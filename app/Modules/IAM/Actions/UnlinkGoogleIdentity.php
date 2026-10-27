<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Authentication\GoogleIdentities;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\SecurityLog\SecurityEventKind;

/**
 * `IAM UnlinkGoogleIdentity` (ROUTE-128; INT-128; NX-02; AU-24 OWNER; RV-15; H8-20): another account's Google identity
 * unlinked by the Owner with the step-up and a mandatory reason, never on the acting Owner itself: the active row's
 * unlink columns set — reason OWNER, `unlinked_by` the acting Owner, the row kept (D-SEC-20) — and every session of the
 * account deleted. A target without an active link is REJECTED with that state. GOOGLE_UNLINKED after the commit, with
 * LG-07's alert for an OWNER-role account; the account is told at its next login (AU-13).
 */
final class UnlinkGoogleIdentity
{
    public const TYPE = 'IAM.UnlinkGoogleIdentity';

    public function __construct(
        private readonly AccountAccess $access,
        private readonly GoogleIdentities $identities,
    ) {}

    public function run(string $commandId, int $actorUserId, User $target, string $reason): Outcome
    {
        $reason = trim($reason);

        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, ['reason' => $reason],
            function (CommandContext $context) use ($target, $reason): CommandResult {
                $context->lock($this->access->locks($target->id, false));
                $row = User::query()->findOrFail($target->id);
                $actor = $context->request->actorUserId;

                if ($row->id === $actor) {
                    throw Refused::rejected('AU-24', ['reason' => 'self']);
                }

                if (! $this->identities->unlinkHeld($row, GoogleIdentities::BY_OWNER, $actor, $context->request->commandId, $reason)) {
                    throw Refused::rejected('AU-24', ['reason' => 'no_link']);
                }

                $this->access->endSessions($row->id);
                $this->access->announce($context, SecurityEventKind::GoogleUnlinked, $row->id, GoogleIdentities::BY_OWNER,
                    $this->access->holdsOwnerRole($row->id) ? 'An OWNER-role account\'s Google identity was unlinked by another Owner (AU-24; LG-07).' : null);

                return new CommandResult('user', $row->id);
            });
    }
}
