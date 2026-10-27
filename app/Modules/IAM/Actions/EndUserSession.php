<?php

namespace App\Modules\IAM\Actions;

use App\Modules\IAM\Data\SessionHandle;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\AccountAccess;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\UserAgentSummary;
use Illuminate\Support\Facades\DB;

/**
 * `IAM EndUserSession` (ROUTE-130; INT-130; NX-02; AU-10; PJ-17): "Akhiri Sesi" — one of the account's sessions,
 * named by its handle (SessionHandle), resolved and deleted under the account's row (LK-05); its holder signs in again
 * (IP-11). REJECTED by NX-02's checks with the current state — a reading within the approved text: a session that no
 * longer exists, and the acting Owner's own current session, which the account menu's "Keluar" ends. Ending a session
 * never changes a Google link (AU-09). Its audit event is the account's (DIR-097 R1) and names no session identifier.
 */
final class EndUserSession
{
    public const TYPE = 'IAM.EndUserSession';

    public function __construct(
        private readonly AccountAccess $access,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, User $target, string $handle, string $actingSessionId): Outcome
    {
        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, ['session' => $handle],
            function (CommandContext $context) use ($target, $handle, $actingSessionId): CommandResult {
                $context->lock($this->access->locks($target->id, false));
                $row = User::query()->findOrFail($target->id);
                $session = DB::table('sessions')->where('user_id', $row->id)->get(['id', 'user_agent'])
                    ->first(fn (object $candidate): bool => is_string($candidate->id) && hash_equals(SessionHandle::of($candidate->id), $handle));

                if ($session === null) {
                    throw Refused::rejected('AU-10', ['reason' => 'gone']);
                }

                if (! is_string($session->id) || hash_equals($session->id, $actingSessionId)) {
                    throw Refused::rejected('AU-10', ['reason' => 'current']);
                }

                DB::table('sessions')->where('id', $session->id)->where('user_id', $row->id)->delete();

                $this->audit->record(
                    action: 'IAM.SessionEnded',
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $context->request->actorUserId,
                    companyId: null,
                    entityType: 'user',
                    entityId: $row->id,
                    entityPublicId: $row->public_id,
                    commandId: $context->request->commandId,
                    metadata: ['device' => UserAgentSummary::of(is_string($session->user_agent) ? $session->user_agent : null)],
                );
                $this->access->announce($context, SecurityEventKind::SessionInvalidated, $row->id, 'OWNER_ENDED');

                return new CommandResult('user', $row->id);
            });
    }
}
