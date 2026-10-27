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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * `IAM ResetTotp` (ROUTE-127; INT-127; NX-02; AU-20 c, d; AU-24; RV-15; DIR-044 R-05; H8-18; H8-19): another account's
 * TOTP reset — for an account without TOTP its credentials reissued — with the step-up and a mandatory reason, never on
 * the acting Owner itself. One transaction: the acting Owner's and the target's rows at LK-03 in id order; the four
 * TOTP fields cleared and the password made unusable in one statement — the hash computed before —; the active Google
 * identity unlinked with reason CREDENTIAL_REISSUE and the acting Owner as `unlinked_by`; every unused link revoked
 * and one RESET link issued, shown once; every session deleted, last (LR-02). After the commit: TOTP_RESET for active
 * TOTP — never for the reissue of an account without it —, PASSWORD_RESET_REQUESTED for the RESET link — reason
 * CREDENTIAL_REISSUE for that reissue —, GOOGLE_UNLINKED for an unlink, and LG-07's alert for every Owner reset.
 */
final class ResetTotp
{
    public const TYPE = 'IAM.ResetTotp';

    /** @var array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null */
    private ?array $issued = null;

    public function __construct(
        private readonly AccountAccess $access,
        private readonly GoogleIdentities $identities,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, User $target, string $reason): Outcome
    {
        $reason = trim($reason);
        $unusable = Hash::make(Str::random(64));

        return $this->access->run($commandId, self::TYPE, $actorUserId, 'users.manage', $target->public_id, ['reason' => $reason],
            function (CommandContext $context) use ($target, $reason, $unusable): CommandResult {
                $this->issued = null;
                $context->lock($this->access->locks($target->id, false));
                $row = User::query()->findOrFail($target->id);
                $actor = $context->request->actorUserId;
                $now = $context->now();

                if ($row->id === $actor) {
                    throw Refused::rejected('AU-20', ['reason' => 'self']);
                }

                $hadTotp = $row->getAttribute('two_factor_confirmed_at') !== null;
                $owner = $this->access->holdsOwnerRole($row->id);
                $this->access->makeUnusable($row, $unusable, $now, clearTotp: true);

                $this->audit->record(
                    action: $hadTotp ? 'IAM.TotpReset' : 'IAM.CredentialsReissued',
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $actor,
                    companyId: null,
                    entityType: 'user',
                    entityId: $row->id,
                    entityPublicId: $row->public_id,
                    reason: $reason,
                    commandId: $context->request->commandId,
                    change: AuditChange::updated(['second_factor_active' => $hadTotp, 'password_usable' => true], ['second_factor_active' => false, 'password_usable' => false]),
                );
                $unlinked = $this->identities->unlinkHeld($row, 'CREDENTIAL_REISSUE', $actor, $context->request->commandId, $reason, $now);
                // LR-02: the token statements, then the sessions, last.
                $link = $this->access->issueLink($row->id, 'RESET', $actor, $now, revokeAll: true);
                $this->access->recordLink($context, $row, $link, 'RESET', $hadTotp ? 'TOTP_RESET' : 'CREDENTIAL_REISSUE', $reason);
                $this->access->endSessions($row->id);
                $this->issued = [...$link, 'purpose' => 'RESET'];

                $alert = $owner
                    ? 'An Owner reset another OWNER-role account\'s credentials; the affected Owner is told at its next login (AU-20 d; LG-07).'
                    : 'An Owner reset an account\'s credentials (AU-20 c; LG-07).';

                if ($hadTotp) {
                    $this->access->announce($context, SecurityEventKind::TotpReset, $row->id, 'OWNER_RESET', $alert);
                } else {
                    $context->afterCommit(fn () => Log::alert($alert, ['user_id' => $row->id, 'actor_user_id' => $actor]));
                }

                if ($unlinked) {
                    $this->access->announce($context, SecurityEventKind::GoogleUnlinked, $row->id, 'CREDENTIAL_REISSUE');
                }

                return new CommandResult('user', $row->id);
            });
    }

    /**
     * The RESET link the reset issued, for the Owner's one view; none on a replay (CI-05).
     *
     * @return array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null
     */
    public function issued(): ?array
    {
        return $this->issued;
    }
}
