<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `IAM RevokeCredentialLink` (ROUTE-126; INT-126; NX-02; AU-11; AU-12): the account's open link of the purpose given
 * revoked — at most one is open per account and purpose, so the purpose names it, a reading within the approved text
 * (ROUTE-126 names no link, and the store has no public identifier) —; none open is REJECTED with that state. The link
 * then answers MSG-28 like a used or expired one (AU-12). Its audit event names the link's identifier (LG-01).
 */
final class RevokeCredentialLink
{
    public const TYPE = 'IAM.RevokeCredentialLink';

    public function __construct(
        private readonly AccountAccess $access,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, User $target, string $purpose): Outcome
    {
        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, ['purpose' => $purpose],
            function (CommandContext $context) use ($target, $purpose): CommandResult {
                $context->lock($this->access->locks($target->id, false));
                $row = User::query()->findOrFail($target->id);
                $now = $context->now();
                $link = DB::table('credential_tokens')->where('user_id', $row->id)->where('purpose', $purpose)->whereNull('used_at')->whereNull('revoked_at')->value('id');

                // LK-04: the link locked by the conditional update that revokes it.
                if (! is_numeric($link) || DB::table('credential_tokens')->where('id', $link)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]) !== 1) {
                    throw Refused::rejected('AU-11', ['reason' => 'no_link', 'purpose' => $purpose]);
                }

                $this->audit->record(
                    action: 'IAM.CredentialLinkRevoked',
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $context->request->actorUserId,
                    companyId: null,
                    entityType: 'user',
                    entityId: $row->id,
                    entityPublicId: $row->public_id,
                    commandId: $context->request->commandId,
                    metadata: ['credential_link' => (int) $link, 'purpose' => $purpose],
                );

                return new CommandResult('credential_link', (int) $link);
            });
    }
}
