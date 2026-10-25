<?php

namespace App\Support\Command;

use App\Modules\IAM\Services\CommandAuthorization;
use App\Support\CorrelationId;
use App\Support\GuardMissing;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * The command envelope of SF-CMD (WORKFLOWS §2; ARCHITECTURE §5; CONCURRENCY_IDEMPOTENCY §6), run once per
 * attempt: A — the replay probe; B — BEGIN, the class timeouts, the identity row at LK-00, a savepoint, the
 * locks in canonical order and the re-authorization once LK-03 is held, which every action reaches through its
 * context's first `lock()` before it commits or refuses; C and D — the action's decision and writes; E — the
 * terminal row and COMMIT; F — work after the commit. A refusal rolls back to the savepoint and commits only its
 * terminal row, with the writes its rule requires (D-CC-05; ST-03; SQ-23); a denial, a defect, a timeout or a
 * lost session commits nothing (SQ-09, SQ-11–SQ-22). A deadlock, a serialization failure or a session lost
 * before or at COMMIT reruns the whole action from phase A, whose probe tells a commit that happened from one
 * that did not (SQ-17), at most three executions (RY-01, RY-02); a lock set that grew restarts it, at most three
 * times more (RY-03); past about 25 s an interactive command answers FAILED (RY-08).
 */
final class CommandEnvelope
{
    public const EXECUTIONS = 3;

    public const RESTARTS = 3;

    public const DEADLINE_SECONDS = 25;

    private int $baseLevel = 0;

    public function __construct(
        private readonly CommandLog $log,
        private readonly CommandAuthorization $authorization,
    ) {}

    /** @var (Closure(Outcome): (array<string, mixed>|null))|null */
    private ?Closure $replayState = null;

    /**
     * @param  Closure(CommandContext): CommandResult  $action  phases C and D: locks, decides, writes
     * @param  Closure(string, array<string, int|string>): void|null  $observer  told of each lock statement (HO-13)
     * @param  (Closure(Outcome): (array<string, mixed>|null))|null  $replayState  a replayed outcome's current in-scope state, null when it no longer resolves (CI-05)
     *
     * The replay state is the action's: a result's current projection, the current state its rule concerns, a
     * duplicate warning's current matches.
     */
    public function run(CommandRequest $request, Closure $action, ?Closure $observer = null, ?Closure $replayState = null): Outcome
    {
        $this->replayState = $replayState;
        $started = hrtime(true);
        $executions = 0;
        $restarts = 0;
        $derived = new LockSet;
        // RY-08: the deadline is the interactive request's; a driven command spends only its budgets.
        $late = fn (): bool => $request->transactionClass === TransactionClass::Interactive && (hrtime(true) - $started) / 1e9 > self::DEADLINE_SECONDS;

        while (true) {
            // Phase A: a terminal row replays before anything else runs (CI-05).
            $recorded = $this->log->probe($request->commandId);

            if ($recorded !== null) {
                return $this->replay($request, $recorded);
            }

            $executions++;
            $attempt = $this->attempt($request, $action, $derived, $observer);

            if ($attempt instanceof Outcome) {
                return $attempt;
            }

            if ($attempt instanceof Restart) {
                $derived = $attempt->union;
                $executions--;

                if (++$restarts > self::RESTARTS) {
                    return Outcome::failed('SQ-20 restart budget spent');
                }

                if ($late()) {
                    return Outcome::failed('RY-08 deadline passed');
                }

                continue;
            }

            if ($executions >= self::EXECUTIONS || $late()) {
                return Outcome::failed('RY-06 retries spent');
            }

            // RY-02: full-jitter backoff of 50–150 ms, then 150–450 ms.
            usleep(random_int(50_000, 150_000) * ($executions === 1 ? 1 : 3));
        }
    }

    /**
     * One execution of phases B–F: an Outcome, a Restart, or FailureMapper::RETRY.
     */
    private function attempt(CommandRequest $request, Closure $action, LockSet $derived, ?Closure $observer): Outcome|Restart|string
    {
        $connection = self::connection();
        $this->baseLevel = $connection->transactionLevel();
        $connection->beginTransaction();

        try {
            $this->setTimeouts($connection, $request->transactionClass);

            if (! $this->log->claim($connection, $request)) {
                // Another transaction held the identity and committed it while this one waited (CI-04).
                $connection->rollBack();
                $recorded = $this->log->probe($request->commandId);

                return $recorded === null ? FailureMapper::RETRY : $this->replay($request, $recorded);
            }

            $context = new CommandContext($request, $connection, $derived, $this->authorization, $observer);
            $connection->beginTransaction();

            try {
                $result = $action($context);

                if (! $result instanceof CommandResult) {
                    throw new LogicException('An action answers its CommandResult (ARCHITECTURE §5).');
                }

                // RV-01: no action commits without the re-authorization its first lock makes (§6 phase B).
                if ($context->authority() === null) {
                    throw new LogicException('An action locks through its context, re-authorizing the actor, before it commits (RV-01; §6 phase B).');
                }

                $connection->statement('set constraints all immediate');
                $connection->commit();
            } catch (Refused $refusal) {
                return $this->refuse($connection, $context, $request, $refusal->outcome, $refusal->then);
            } catch (QueryException $error) {
                $decision = FailureMapper::map($error);

                if ($decision instanceof Outcome && in_array($decision->kind, [Outcome::REJECTED, Outcome::CONFLICT], true)) {
                    return $this->refuse($connection, $context, $request, $decision);
                }

                throw $error;
            }

            $outcome = Outcome::committed($result->type, $result->id);
            $this->log->finish($connection, $request->commandId, $outcome);
            $connection->commit();
        } catch (Restart $restart) {
            $this->rollBackAll($connection);

            return $restart;
        } catch (GuardMissing $missing) {
            $this->rollBackAll($connection);
            Log::critical('A guard row is missing; the command failed (GU-02; SQ-18).', ['table' => $missing->table, 'command_type' => $request->commandType, 'correlation_id' => CorrelationId::current()]);

            return Outcome::failed('SQ-18 guard missing');
        } catch (QueryException $error) {
            $this->rollBackAll($connection);
            $decision = FailureMapper::map($error);

            if ($decision === FailureMapper::NOT_FOUND) {
                throw new ModelNotFoundException;
            }

            $this->report($request, $error, $decision);

            return $decision;
        } catch (Throwable $error) {
            $this->rollBackAll($connection);

            // A session lost at COMMIT leaves the outcome unknown; phase A's probe of the next execution learns
            // it from the identity row the commit would have written (SQ-17).
            if (FailureMapper::lostSession($error)) {
                $this->report($request, $error, FailureMapper::RETRY);

                return FailureMapper::RETRY;
            }

            throw $error;
        }

        foreach ($context->afterCommitWork() as $work) {
            try {
                $work();
            } catch (Throwable $error) {
                Log::error('Work after a command\'s commit failed; its outcome stands (SQ-21).', ['command_type' => $request->commandType, 'error' => $error::class, 'correlation_id' => CorrelationId::current()]);
            }
        }

        return $outcome;
    }

    /**
     * A refusal: back to the savepoint, the actor re-authorized if the action refused before its first lock —
     * a denial answers instead —, the writes its rule commits with it, then its terminal row (D-CC-05).
     *
     * @param  (Closure(ConnectionInterface): void)|null  $then
     */
    private function refuse(Connection $connection, CommandContext $context, CommandRequest $request, Outcome $outcome, ?Closure $then = null): Outcome
    {
        $connection->rollBack();

        if ($context->authority() === null) {
            $context->lock(new LockSet);
        }

        // A driven identity records only COMMITTED; its refusal is written on its driver row (CI-08). A fact that
        // already carries its identity is "already done" (SQ-07).
        if (CommandId::isDriven($request->commandId)) {
            $connection->rollBack();

            return $outcome->code === 'SQ-07' ? Outcome::committed(null, null, replayed: true) : $outcome;
        }

        $then?->__invoke($connection);
        $this->log->finish($connection, $request->commandId, $outcome);
        $connection->commit();

        return $outcome;
    }

    /**
     * The replay table of CI-05, in a transaction of the command's class that holds the actor's row while it
     * re-authorizes; it commits nothing new. Another actor's identity is a CONFLICT that discloses nothing
     * (DP-16); a denial is the one a fresh command would get; other content is a CONFLICT; the same intent
     * returns its recorded outcome with the current in-scope state, resolved only after those checks and left
     * out when it no longer resolves. A command type names its capability, so the request's is the recorded
     * one's whenever the types agree; when they differ the answer is the CONFLICT.
     */
    private function replay(CommandRequest $request, RecordedCommand $recorded): Outcome
    {
        if ($recorded->actorUserId !== $request->actorUserId) {
            return Outcome::conflict('CI-05', replayed: true);
        }

        return DB::transaction(function () use ($request, $recorded): Outcome {
            $this->setTimeouts(self::connection(), $request->transactionClass);
            $this->authorization->reauthorize($request->actorUserId, $request->capability, $recorded->companyId);

            if ($recorded->commandType !== $request->commandType || $recorded->fingerprint !== $request->fingerprint || $recorded->companyId !== $request->companyId) {
                return Outcome::conflict('CI-05', replayed: true);
            }

            $outcome = $recorded->outcome();
            $state = $this->replayState === null ? null : ($this->replayState)($outcome);

            return $state === null ? $outcome : $outcome->withState($state);
        });
    }

    private static function connection(): Connection
    {
        return DB::connection();
    }

    private function setTimeouts(Connection $connection, TransactionClass $class): void
    {
        $connection->statement("set local lock_timeout = '{$class->lockTimeout()}'");
        $connection->statement("set local statement_timeout = '{$class->statementTimeout()}'");
    }

    /** Every FAILED answer and every rerun is logged; a deadlock is alerted each time (SQ-13; HO-25). */
    private function report(CommandRequest $request, Throwable $error, Outcome|string $decision): void
    {
        $context = ['command_type' => $request->commandType, 'state' => (string) $error->getCode(), 'correlation_id' => CorrelationId::current()];

        match (true) {
            $decision instanceof Outcome => Log::error('A command failed (CONCURRENCY_IDEMPOTENCY §12).', [...$context, 'reason' => $decision->code]),
            (string) $error->getCode() === '40P01' => Log::error('A command met a deadlock and runs again (SQ-13; RY-01).', $context),
            default => Log::warning('A command runs again (RY-01).', $context),
        };
    }

    /**
     * Rolls back every level this envelope opened. A lost session has ended its transaction on the server, and
     * its rollback fails: the connection is dropped, its level reset, and the next statement reconnects (SQ-16).
     */
    private function rollBackAll(Connection $connection): void
    {
        while ($this->ownsLevel($connection)) {
            try {
                $connection->rollBack();
            } catch (Throwable $error) {
                if ($this->baseLevel !== 0) {
                    throw $error;
                }

                $connection->disconnect();

                return;
            }
        }
    }

    /** Whether the open level is this envelope's, not one the caller — a test's wrapping transaction — opened. */
    private function ownsLevel(Connection $connection): bool
    {
        return $connection->transactionLevel() > $this->baseLevel;
    }
}
