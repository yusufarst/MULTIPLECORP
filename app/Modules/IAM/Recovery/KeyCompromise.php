<?php

namespace App\Modules\IAM\Recovery;

use App\Modules\IAM\Authentication\CredentialLinks;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AU-21's key-compromise incident command, protocol (a)–(d) (DIR-044 R-02; DIR-048 R2-03; RV-17): (a) an opening
 * sweep deleting every session and revoking every unused credential token; (b) the framework's maintenance mode,
 * without a bypass, required before the start and before every account, so the command refuses to start or to
 * continue outside it; (c) every account voided — the four TOTP fields cleared and the password made unusable in one
 * statement, its unused links revoked and its sessions deleted last, Google links kept —, one TX-07 transaction per
 * account under its row lock (LK-03), in id order, the accounts read again until none is left, one created after the
 * run started included; (d) a closing sweep. The window start, established with the Owner, is recorded once with the
 * run's identifier in every event of the run; a restart of an uncompleted run refuses any other value and skips the
 * accounts already voided, so each is voided exactly once. Each account's audit event carries the operator's
 * identity, no user row, authority class SYSTEM; CREDENTIALS_VOIDED follows each commit and alerts (LG-07).
 */
final class KeyCompromise
{
    public function __construct(
        private readonly AccountHistory $history,
        private readonly IncidentRuns $runs,
        private readonly AuditRecorder $audit,
        private readonly SecurityEventRecorder $events,
    ) {}

    /**
     * @param  Closure(string): void|null  $progress  told each step, for the operator's console
     * @return array{run: IncidentRun, restarted: bool, voided: int, halt: ?string}
     *
     * @throws RecoveryRefused
     */
    public function run(string $windowStart, OperatorIdentity $operator, ?Closure $progress = null): array
    {
        OperatorIdentity::ensureConsole();
        $given = IncidentRuns::parse($windowStart);
        $this->ensureMaintenance();
        $progress ??= fn (string $line) => null;

        $latest = $this->runs->latest();
        $restarted = $latest !== null && ! $latest->completed();

        if ($restarted) {
            if (! $given->equalTo($latest->windowStart)) {
                throw new RecoveryRefused("Incident run {$latest->id} stopped before its closing sweep with the window start {$latest->windowStartText()}: "
                    .'restart it with that value; any other is refused (RV-17; AU-25).');
            }

            $run = $latest;
            $progress("Restarting incident run {$run->id}, window start {$run->windowStartText()}.");
        } else {
            $run = $this->start($given, $operator);
            $progress("Started incident run {$run->id}, window start {$run->windowStartText()}.");
        }

        $progress('Opening sweep (a): '.$this->sweep($run, $operator, 'opening').' accounts swept.');

        $voided = 0;

        do {
            $pending = $this->unvoided($run);

            foreach ($pending as $userId) {
                $voided += $this->void($run, $operator, $userId) ? 1 : 0;
            }
        } while ($pending !== []);

        $progress("Accounts voided (c): {$voided}.");
        $progress('Closing sweep (d): '.$this->sweep($run, $operator, 'closing').' accounts swept.');

        DB::transaction(function () use ($run, $operator, $voided) {
            $this->ensureMaintenance();
            $this->audit->record(
                action: IncidentRuns::COMPLETED,
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                metadata: ['operator' => $operator->toArray(), ...$run->reference(), 'voided_by_this_invocation' => $voided],
            );
        });
        Log::alert('The key-compromise incident command completed (AU-21; LG-07).', [...$run->reference(), 'operator' => $operator->describe()]);

        $completed = $this->runs->latest() ?? $run;

        try {
            $this->history->preWindowOwners($completed->windowStart);
            $halt = null;
        } catch (RecoveryHalted $halted) {
            $halt = $halted->getMessage();
        }

        return ['run' => $completed, 'restarted' => $restarted, 'voided' => $voided, 'halt' => $halt];
    }

    /**
     * (b): the maintenance mode active without a bypass secret; refused otherwise (AU-21; INFRASTRUCTURE §7.2 step 5).
     *
     * @throws RecoveryRefused
     */
    public function ensureMaintenance(): void
    {
        $mode = app()->maintenanceMode();

        if (! $mode->active()) {
            throw new RecoveryRefused('The maintenance mode is not active: enable it on every web process, without a bypass, before the run, and keep it until the closing sweep (AU-21 b).');
        }

        if (($mode->data()['secret'] ?? null) !== null) {
            throw new RecoveryRefused('The maintenance mode has a bypass secret: enable it without one (AU-21 b).');
        }
    }

    private function start(CarbonImmutable $windowStart, OperatorIdentity $operator): IncidentRun
    {
        $id = (string) Str::uuid();
        $previous = config('app.previous_keys');

        DB::transaction(fn () => $this->audit->record(
            action: IncidentRuns::STARTED,
            authorityClass: AuthorityClass::System,
            actorUserId: null,
            companyId: null,
            metadata: [
                'operator' => $operator->toArray(),
                'incident_run' => $id,
                'window_start' => IncidentRuns::format($windowStart),
                // The previous-keys list's state, never a key: the leaked key is listed only when ciphertext must survive (AU-21).
                'previous_keys_listed' => is_array($previous) ? count(array_filter($previous)) : 0,
            ],
        ));

        $run = $this->runs->latest();

        if ($run === null || $run->id !== $id) {
            throw new RecoveryRefused('Another incident run started at the same time; run the command once (RV-17).');
        }

        return $run;
    }

    /** @return list<int> the accounts the run has not voided, in id order */
    private function unvoided(IncidentRun $run): array
    {
        return AccountHistory::ids(DB::table('users')->whereNotExists(fn (Builder $query) => $query->from('audit_events')
            ->where('audit_events.action', IncidentRuns::VOIDED)->where('audit_events.entity_type', 'user')
            ->whereColumn('audit_events.entity_id', 'users.id')
            ->whereRaw("audit_events.metadata->>'incident_run' = ?", [$run->id]))
            ->orderBy('id')->pluck('id')->all());
    }

    /** (c) for one account: its TX-07 transaction under its row; answers whether this invocation voided it. */
    private function void(IncidentRun $run, OperatorIdentity $operator, int $userId): bool
    {
        // The unusable hash computed before the transaction (section 3 of CONCURRENCY_IDEMPOTENCY).
        $unusable = Hash::make(Str::random(64));

        $voided = DB::transaction(function () use ($run, $operator, $userId, $unusable): bool {
            $this->ensureMaintenance();
            $account = DB::table('users')->where('id', $userId)->lock('for no key update')->first(['id', 'public_id', 'password', 'two_factor_confirmed_at']);

            if ($account === null || $this->runs->hasEvent($run, IncidentRuns::VOIDED, $userId)) {
                return false;
            }

            DB::table('users')->where('id', $userId)->update(['password' => $unusable, ...CredentialLinks::CLEARED_TOTP, 'updated_at' => now()]);
            $this->audit->record(
                action: IncidentRuns::VOIDED,
                authorityClass: AuthorityClass::System,
                actorUserId: null,
                companyId: null,
                entityType: 'user',
                entityId: $userId,
                entityPublicId: is_string($account->public_id) ? $account->public_id : null,
                change: AuditChange::updated(
                    ['password' => $account->password, 'second_factor_active' => $account->two_factor_confirmed_at !== null],
                    ['password' => $unusable, 'second_factor_active' => false],
                ),
                metadata: ['operator' => $operator->toArray(), ...$run->reference()],
            );
            // The token and session statements last (LK-04, LK-05; LR-02).
            $this->revokeAndEnd($userId);

            return true;
        });

        if ($voided) {
            $this->events->record(SecurityEventKind::CredentialsVoided, userId: $userId, reasonCode: 'KEY_COMPROMISE', details: $run->reference());
            Log::alert('An account\'s credentials were voided by the key-compromise incident command (AU-21; LG-07).', ['user_id' => $userId, ...$run->reference(), 'operator' => $operator->describe()]);
        }

        return $voided;
    }

    /** (a) and (d): one transaction per account whose sessions or unused links it removes, then the orphan sessions. */
    private function sweep(IncidentRun $run, OperatorIdentity $operator, string $phase): int
    {
        $swept = 0;

        foreach (AccountHistory::ids(DB::table('users')->orderBy('id')->pluck('id')->all()) as $userId) {
            $swept += DB::transaction(function () use ($run, $operator, $phase, $userId): int {
                $this->ensureMaintenance();
                $account = DB::table('users')->where('id', $userId)->lock('for no key update')->first(['public_id']);
                $sessions = DB::table(self::sessionTable())->where('user_id', $userId)->count();
                $links = DB::table('credential_tokens')->where('user_id', $userId)->whereNull('used_at')->whereNull('revoked_at')->count();

                if ($account === null || ($sessions === 0 && $links === 0)) {
                    return 0;
                }

                $this->audit->record(
                    action: IncidentRuns::SWEPT,
                    authorityClass: AuthorityClass::System,
                    actorUserId: null,
                    companyId: null,
                    entityType: 'user',
                    entityId: $userId,
                    entityPublicId: is_string($account->public_id) ? $account->public_id : null,
                    metadata: ['operator' => $operator->toArray(), ...$run->reference(), 'phase' => $phase, 'sessions_deleted' => $sessions, 'links_revoked' => $links],
                );
                $this->revokeAndEnd($userId);

                return 1;
            });
        }

        // Session rows without an account last, without an audit event (AU-21).
        DB::table(self::sessionTable())->whereNull('user_id')->delete();

        return $swept;
    }

    private function revokeAndEnd(int $userId): void
    {
        DB::table('credential_tokens')->where('user_id', $userId)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        DB::table(self::sessionTable())->where('user_id', $userId)->delete();
    }

    private static function sessionTable(): string
    {
        $table = config('session.table');

        return is_string($table) ? $table : 'sessions';
    }
}
