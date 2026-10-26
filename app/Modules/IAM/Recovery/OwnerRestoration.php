<?php

namespace App\Modules\IAM\Recovery;

use App\Modules\IAM\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AU-25's OWNER-restoration command and the end of a run's recovery (RV-18; M-46; TT-F30). A restoration runs only
 * after the incident command has completed and before the run's recovery ends, with the run's recorded window start
 * and no other; one TX-07 transaction under the OWNER role row (LK-02) and then the account's row (LK-03), reading
 * the trail under them: only a pre-window Owner, untouched since the closing sweep but for the run's own writes, not
 * yet restored in the run; it sets back only an attribute — the OWNER role, the active flag or both — whose last
 * change was a removal during the window, to its window-start value — the role row with the before-image of the
 * window's first change of it, `granted_by` and `granted_at` included, and a restored activation with
 * `deactivated_at` and `deactivated_by` cleared — and nothing else; it writes no password, secret, token, session or
 * user row for the operator, and issues no link. Refusals write nothing. OWNER_RESTORED follows the commit and alerts.
 * The end of recovery is recorded atomically under the OWNER role row, only when AU-14's guard holds for every
 * active account holding the OWNER role, refused otherwise with the accounts that block it.
 */
final class OwnerRestoration
{
    public function __construct(
        private readonly AccountHistory $history,
        private readonly IncidentRuns $runs,
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
    ) {}

    /**
     * @return array{account: User, run: IncidentRun, reversed: list<array{attribute: string, removed_at: string, removed_by: ?string}>}
     *
     * @throws RecoveryRefused
     */
    public function restore(string $email, string $windowStart, OperatorIdentity $operator): array
    {
        OperatorIdentity::ensureConsole();

        $restored = DB::transaction(function () use ($email, $windowStart, $operator): array {
            $this->history->lockOwnerRole();
            $run = $this->runs->forRecovery($windowStart);
            $found = User::query()->whereRaw('lower(email) = lower(?)', [trim($email)])->first();
            $account = $found instanceof User ? User::query()->lock('for no key update')->find($found->id) : null;

            if (! $account instanceof User) {
                throw new RecoveryRefused('No account has that e-mail address (AU-25).');
            }

            $owners = $this->history->preWindowOwners($run->windowStart);
            $now = $this->history->current($account->id);
            $state = sprintf('current role %s, %s', $now['role'] ?? 'none', $now['active'] ? 'active' : 'inactive');

            if (! in_array($account->id, $owners, true)) {
                throw new RecoveryRefused("The account did not hold the OWNER role while active at the window start; it is never restored ({$state}; AU-25).");
            }

            if ($this->runs->hasEvent($run, IncidentRuns::RESTORED, $account->id)) {
                throw new RecoveryRefused("The account was restored already in incident run {$run->id}; once per account per run ({$state}; M-46).");
            }

            if (($touch = $this->touchedSince($account->id, $run)) !== null) {
                throw new RecoveryRefused("The account was touched since the closing sweep — {$touch} —, so no decision taken after it is reversed ({$state}; RV-18).");
            }

            $role = $this->removal($account->id, AccountHistory::ROLE, $run, fn ($value) => $value !== AccountHistory::OWNER) && $now['role'] !== AccountHistory::OWNER;
            $active = $this->removal($account->id, AccountHistory::ACTIVE, $run, fn ($value) => $value === false) && ! $now['active'];

            if (! $role && ! $active) {
                throw new RecoveryRefused("Nothing to restore: the account's OWNER role and active flag hold their window-start values, or their last change was not a removal during the window ({$state}); "
                    .'a pre-window Owner holding them goes straight to its recovery (AU-25).');
            }

            $reversed = [];
            $before = AccountHistory::image($now['role'], $now['granted_by'], $now['granted_at'], $now['active']);
            $after = $before;

            if ($role) {
                $first = $this->history->changes($account->id, AccountHistory::ROLE, $run->windowStart)['firstAfter'];
                // A key the change left as it was is absent from its image: the row still holds its window-start value.
                $image = $first === null ? [] : $first->before;
                $grantedBy = array_key_exists(AccountHistory::ROLE_GRANTED_BY, $image) ? $image[AccountHistory::ROLE_GRANTED_BY] : $now['granted_by'];
                $grantedAt = array_key_exists(AccountHistory::ROLE_GRANTED_AT, $image) ? $image[AccountHistory::ROLE_GRANTED_AT] : $now['granted_at'];
                $values = ['role_id' => $this->history->ownerRoleId(), 'granted_by' => is_numeric($grantedBy) ? (int) $grantedBy : null, 'granted_at' => $grantedAt ?? now()];

                // The role given in the window's place replaced by the statement a role change uses (RV-04).
                DB::table('user_roles')->where('user_id', $account->id)->exists()
                    ? DB::table('user_roles')->where('user_id', $account->id)->update($values)
                    : DB::table('user_roles')->insert(['user_id' => $account->id, ...$values]);
                $after = [...$after, AccountHistory::ROLE => AccountHistory::OWNER, AccountHistory::ROLE_GRANTED_BY => $values['granted_by'], AccountHistory::ROLE_GRANTED_AT => $values['granted_at']];
                $reversed[] = $this->reversal('OWNER role', $account->id, AccountHistory::ROLE);
            }

            if ($active) {
                DB::table('users')->where('id', $account->id)->update(['is_active' => true, 'deactivated_at' => null, 'deactivated_by' => null, 'updated_at' => now()]);
                $after = [...$after, AccountHistory::ACTIVE => true];
                $reversed[] = $this->reversal('active flag', $account->id, AccountHistory::ACTIVE);
            }

            $this->audit->record(
                action: IncidentRuns::RESTORED,
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                entityType: 'user',
                entityId: $account->id,
                entityPublicId: $account->public_id,
                change: AuditChange::updated($before, $after),
                metadata: ['operator' => $operator->toArray(), ...$run->reference(), 'reversed' => $reversed],
            );

            return ['account' => $account, 'run' => $run, 'reversed' => $reversed];
        });

        $this->events->record(SecurityEventKind::OwnerRestored, userId: $restored['account']->id, reasonCode: 'KEY_COMPROMISE',
            targetPublicId: $restored['account']->public_id, details: $restored['run']->reference());
        Log::alert('A pre-window Owner was restored by the OWNER-restoration command (AU-25; LG-07).', ['user_id' => $restored['account']->id, ...$restored['run']->reference(), 'operator' => $operator->describe()]);

        return $restored;
    }

    /**
     * Records the end of the run's recovery, atomically under the OWNER role row (TT-F30; GAP-050): only when every
     * active account holding the OWNER role is a pre-window Owner of the run or has an Owner-recovery issue in the run
     * naming a confirmer (AU-14), refused otherwise with the accounts that block it.
     *
     * @throws RecoveryRefused
     */
    public function endRecovery(string $windowStart, OperatorIdentity $operator): IncidentRun
    {
        OperatorIdentity::ensureConsole();

        $run = DB::transaction(function () use ($windowStart, $operator): IncidentRun {
            $this->history->lockOwnerRole();
            $run = $this->runs->forRecovery($windowStart);
            $owners = $this->history->preWindowOwners($run->windowStart);

            $blocking = DB::table('users')->join('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->where('user_roles.role_id', $this->history->ownerRoleId())->where('users.is_active', true)
                ->whereNotIn('users.id', $owners === [] ? [0] : $owners)
                ->whereNotExists(fn (Builder $query) => $query->from('audit_events')->where('audit_events.action', IncidentRuns::LINK_ISSUED)
                    ->where('audit_events.entity_type', 'user')->whereColumn('audit_events.entity_id', 'users.id')
                    ->whereRaw("audit_events.metadata->>'incident_run' = ?", [$run->id])
                    ->whereRaw("audit_events.metadata->>'confirmer_public_id' is not null"))
                ->orderBy('users.id')->get(['users.public_id', 'users.email']);

            if ($blocking->isNotEmpty()) {
                $list = $blocking->map(fn (object $row): string => sprintf('%s (%s)', self::text($row->email ?? null), self::text($row->public_id ?? null)))->implode(', ');

                throw new RecoveryRefused("The end of recovery is refused: these active accounts hold the OWNER role without being a pre-window Owner or having an Owner-recovery issue naming a confirmer — {$list} (AU-14; AU-25).");
            }

            $this->audit->record(
                action: IncidentRuns::ENDED,
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                metadata: ['operator' => $operator->toArray(), ...$run->reference()],
            );

            return $run;
        });

        Log::alert('The end of an incident run\'s recovery was recorded (AU-25; LG-07).', [...$run->reference(), 'operator' => $operator->describe()]);

        return $run;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** The last change of an attribute was a removal during the window — after its start, up to the closing sweep. */
    private function removal(int $userId, string $key, IncidentRun $run, callable $removed): bool
    {
        $last = $this->history->changes($userId, $key, $run->windowStart)['last'];

        return $last !== null && $run->completedAt !== null
            && $last->occurredAt->greaterThan($run->windowStart) && $last->occurredAt->lessThanOrEqualTo($run->completedAt)
            && $removed($last->after[$key]);
    }

    /** @return array{attribute: string, removed_at: string, removed_by: ?string} the removal reversed, for the runbook's display (GAP-048) */
    private function reversal(string $attribute, int $userId, string $key): array
    {
        $last = $this->history->changes($userId, $key, CarbonImmutable::createFromTimestamp(0))['last'];
        $actor = $last?->actorUserId === null ? null : DB::table('users')->where('id', $last->actorUserId)->value('public_id');

        return ['attribute' => $attribute, 'removed_at' => $last === null ? '' : IncidentRuns::format($last->occurredAt), 'removed_by' => is_string($actor) ? $actor : null];
    }

    /**
     * What touched the account after the closing sweep, the run's own restoration excepted: a change of its role,
     * active flag or grants, a credential link issued or used, a password set, a TOTP enrolled — every such step
     * writes an audit event of the account — or a session established; null when untouched (RV-18).
     */
    private function touchedSince(int $userId, IncidentRun $run): ?string
    {
        $since = IncidentRuns::format($run->completedAt ?? $run->startedAt);
        $event = DB::table('audit_events')->where('entity_type', 'user')->where('entity_id', $userId)->where('occurred_at', '>', $since)
            ->whereNot(fn (Builder $query) => $query->where('action', IncidentRuns::RESTORED)->whereRaw("metadata->>'incident_run' = ?", [$run->id]))
            ->orderBy('id')->value('action');

        return match (true) {
            is_string($event) => "the audit event {$event}",
            DB::table('credential_tokens')->where('user_id', $userId)->where(fn (Builder $query) => $query->where('issued_at', '>', $since)->orWhere('used_at', '>', $since))->exists() => 'a credential link issued or used',
            DB::table(is_string($table = config('session.table')) ? $table : 'sessions')->where('user_id', $userId)->exists() => 'a session established',
            DB::table('security_events')->where('user_id', $userId)->where('event_kind', 'LOGIN_SUCCEEDED')->where('occurred_at', '>', $since)->exists() => 'a sign-in',
            default => null,
        };
    }
}
