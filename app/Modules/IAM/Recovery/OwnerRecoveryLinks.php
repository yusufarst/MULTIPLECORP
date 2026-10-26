<?php

namespace App\Modules\IAM\Recovery;

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Modules\IAM\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AU-14's Owner-recovery command (AU-20 e; WF-ACC-01; H9-07): an OWNER_RECOVERY link issued for an active account
 * holding the OWNER role and for no other account, changing nothing else; the operator's statement of why recorded
 * (K3). Until an incident run's recovery ends, its guard: a link without a named confirmer only for a pre-window Owner
 * of the run, and for every other account holding the OWNER role only with a named confirmer who held the OWNER role
 * and was active at the run's window start, whose own OWNER_RECOVERY link was used after the window and who is an
 * active Owner now — checked under the row locks of the account and the confirmer, in id order (LK-03). The issue's
 * audit event (in its transaction) and OWNER_RECOVERY_LINK_ISSUED (after the commit) name the link by its store
 * identifier, the run, its window start and the confirmer, with the operator's identity and no user row; the
 * operator, the Owner and the confirmer are alerted (LG-07). It never sets, reads or reveals a password or a secret.
 */
final class OwnerRecoveryLinks
{
    public function __construct(
        private readonly CredentialLinks $links,
        private readonly AccountHistory $history,
        private readonly IncidentRuns $runs,
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
    ) {}

    /**
     * @return array{token: string, account: User, run: ?IncidentRun, confirmer: ?User}
     *
     * @throws RecoveryRefused
     */
    public function issue(string $email, string $reason, OperatorIdentity $operator, ?string $confirmerEmail = null): array
    {
        OperatorIdentity::ensureConsole();
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new RecoveryRefused('State with --reason why the Owner\'s own recovery codes and another active Owner cannot serve (K3; AU-14): at most 500 characters.');
        }

        $issued = DB::transaction(function () use ($email, $reason, $operator, $confirmerEmail): array {
            $account = $this->account($email);
            $confirmer = $confirmerEmail === null ? null : $this->account($confirmerEmail);

            if ($confirmer !== null && $confirmer->id === $account->id) {
                throw new RecoveryRefused('An account never confirms itself: name another recovered pre-window Owner (AU-14).');
            }

            // LK-03: the account's and the confirmer's rows, in id order.
            $locked = User::query()->whereIn('id', array_filter([$account->id, $confirmer?->id]))->orderBy('id')->lock('for no key update')->get()->keyBy('id');
            $account = $locked->get($account->id);
            $confirmer = $confirmer === null ? null : $locked->get($confirmer->id);

            if (! $account instanceof User || ! $account->is_active || $this->history->current($account->id)['role'] !== AccountHistory::OWNER) {
                throw new RecoveryRefused('The account is not an active account holding the OWNER role; no link is issued (AU-14).');
            }

            $run = $this->runs->latestOpen();

            if ($run !== null) {
                $this->guard($run, $account, $confirmer);
            }

            $link = $this->links->issueLink($account, 'OWNER_RECOVERY', null);
            $this->audit->record(
                action: IncidentRuns::LINK_ISSUED,
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                entityType: 'user',
                entityId: $account->id,
                entityPublicId: $account->public_id,
                reason: $reason,
                change: AuditChange::none(),
                metadata: [
                    'operator' => $operator->toArray(),
                    'credential_link' => $link['id'],
                    ...($run?->reference() ?? []),
                    'confirmer_public_id' => $confirmer?->public_id,
                ],
            );

            return ['token' => $link['token'], 'id' => $link['id'], 'account' => $account, 'run' => $run, 'confirmer' => $confirmer];
        });

        $details = [...($issued['run']?->reference() ?? []), 'confirmer_public_id' => $issued['confirmer']?->public_id];
        $this->events->record(SecurityEventKind::OwnerRecoveryLinkIssued, userId: $issued['account']->id, reasonCode: 'OWNER_RECOVERY',
            targetPublicId: $issued['account']->public_id, details: array_filter($details, fn ($value) => $value !== null));
        Log::alert('An OWNER_RECOVERY link was issued by the operator (AU-14; LG-07).', array_filter([
            'user_id' => $issued['account']->id,
            'credential_link' => $issued['id'],
            'operator' => $operator->describe(),
            'confirmer_user_id' => $issued['confirmer']?->id,
        ], fn ($value) => $value !== null));

        return ['token' => $issued['token'], 'account' => $issued['account'], 'run' => $issued['run'], 'confirmer' => $issued['confirmer']];
    }

    /**
     * AU-14's guard while the run's recovery lasts.
     *
     * @throws RecoveryRefused
     */
    private function guard(IncidentRun $run, User $account, ?User $confirmer): void
    {
        if (! $run->completed() || $run->completedAt === null) {
            throw new RecoveryRefused("Incident run {$run->id} has not completed: recovery starts only after its closing sweep (AU-25).");
        }

        $owners = $this->history->preWindowOwners($run->windowStart);

        if ($confirmer === null) {
            if (! in_array($account->id, $owners, true)) {
                throw new RecoveryRefused("The account did not hold the OWNER role while active at incident run {$run->id}'s window start, {$run->windowStartText()}: "
                    .'name with --confirmer the recovered pre-window Owner who confirmed it in the review (AU-14; AU-25).');
            }

            return;
        }

        $standing = match (true) {
            ! in_array($confirmer->id, $owners, true) => 'did not hold the OWNER role while active at the window start',
            ! $confirmer->is_active || $this->history->current($confirmer->id)['role'] !== AccountHistory::OWNER => 'is not an active Owner now',
            ! $this->recoveredAfter($confirmer->id, $run) => 'has no OWNER_RECOVERY link used after the window',
            default => null,
        };

        if ($standing !== null) {
            throw new RecoveryRefused("The named confirmer {$standing}; no link is issued (AU-14).");
        }
    }

    /** The trail shows the confirmer's own OWNER_RECOVERY link used after the window, which ends with the run (AU-14). */
    private function recoveredAfter(int $userId, IncidentRun $run): bool
    {
        return DB::table('audit_events')->where('action', 'IAM.CredentialLinkConsumed')->where('entity_type', 'user')->where('entity_id', $userId)
            ->whereRaw("metadata->>'purpose' = 'OWNER_RECOVERY'")->where('occurred_at', '>', IncidentRuns::format($run->completedAt ?? $run->startedAt))->exists();
    }

    /** @throws RecoveryRefused */
    private function account(string $email): User
    {
        $account = User::query()->whereRaw('lower(email) = lower(?)', [trim($email)])->first();

        if (! $account instanceof User) {
            throw new RecoveryRefused('No account has that e-mail address; no link is issued (AU-14).');
        }

        return $account;
    }
}
