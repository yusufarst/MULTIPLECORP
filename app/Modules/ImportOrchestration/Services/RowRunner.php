<?php

namespace App\Modules\ImportOrchestration\Services;

use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Kinds\Kinds;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandId;
use App\Support\Command\CommandLog;
use App\Support\Command\CommandResult;
use App\Support\Command\DrivenWork;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * AX-28 for one import row (WF-MIG-01; CI-01; CI-08): each of its kind's steps runs as the module's ordinary action
 * under the row's deterministic identity, and the last one's transaction re-reads, under lock, the batch committing
 * and the row VALID, then writes the row COMMITTED with its result — the fact and its row together or neither. A step
 * an earlier run committed is taken from the command log, never run again, whoever resumes (M-33); a refusal is
 * rolled back, never recorded under the identity, and returned for the row (CI-08). The dry run takes the same path
 * inside a transaction its caller rolls back (JB-05).
 */
final class RowRunner
{
    public const IN_PROGRESS = 'in_progress';

    /** A row stopped by a refusal: an error other than the mark of a row begun (SQL over `errors`). */
    public const REFUSED = "errors is not null and exists (select from jsonb_array_elements(errors) as e where e->>'reason' <> 'in_progress')";

    /** The row's begun mark, kept with a refusal and through a resume, so a batch with a begun row is never aborted (IMP-06). */
    public const KEEP_BEGUN = "case when errors @> '[{\"reason\": \"in_progress\"}]'::jsonb then '[{\"field\": \"\", \"reason\": \"in_progress\"}]'::jsonb else null end";

    /** The result of a seed row NM-03's period rule no longer admits: the row recorded SKIPPED, no counter written. */
    public const SKIPPED = 'skipped';

    public function __construct(
        private readonly Kinds $kinds,
        private readonly CommandLog $log,
    ) {}

    /**
     * @return array{state: 'COMMITTED'|'REFUSED', errors: list<array<string, mixed>>}
     */
    public function run(ImportRowData $row, int $actor, bool $dryRun): array
    {
        $kind = $this->kinds->of($row->kind);
        $done = [];

        try {
            $steps = $kind->steps($row);
            $last = array_key_last($steps);

            foreach ($steps as $index => $step) {
                $commandId = CommandId::driven('import', $row->name($index));
                $recorded = $index === $last ? null : $this->log->probe($commandId);

                if ($recorded !== null && $recorded->outcome()->kind === Outcome::COMMITTED) {
                    $done[] = $recorded->outcome();

                    continue;
                }

                $outcome = DrivenWork::within($commandId, $this->driver($row, $index === $last, $dryRun), fn (): Outcome => $step($commandId, $actor, $done));

                if ($outcome->kind !== Outcome::COMMITTED) {
                    return ['state' => 'REFUSED', 'errors' => [['field' => '', 'reason' => 'refused', 'code' => $outcome->code ?? $outcome->kind, 'detail' => $outcome->state]]];
                }

                $done[] = $outcome;
            }
        } catch (RowRefused $refused) {
            return ['state' => 'REFUSED', 'errors' => [['field' => $refused->field, 'reason' => $refused->reason]]];
        } catch (AuthorizationException|ModelNotFoundException) {
            return ['state' => 'REFUSED', 'errors' => [['field' => '', 'reason' => 'denied']]];
        } catch (ValidationException $invalid) {
            return ['state' => 'REFUSED', 'errors' => array_map(static fn (string $field): array => ['field' => $field, 'reason' => 'format'], array_keys($invalid->errors()))];
        }

        return ['state' => 'COMMITTED', 'errors' => []];
    }

    /**
     * The writes the row owes inside its step's transaction: the batch committing and the row VALID re-read under
     * lock — an aborted or finished batch, or a row another run committed, refuses the step —; a step before the last
     * marks the row begun, so the batch can no longer be aborted; the last writes it COMMITTED with its result (C-47).
     *
     * @return Closure(CommandContext, CommandResult): void
     */
    private function driver(ImportRowData $row, bool $last, bool $dryRun): Closure
    {
        return function (CommandContext $context, CommandResult $result) use ($row, $last, $dryRun): void {
            $connection = $context->connection;
            $batch = $connection->table('import_batches')->where('id', $row->batchId)->lock('for share')->first(['state', 'committed_at']);

            if (! $dryRun && ($batch === null || $batch->state !== 'SIGNED_OFF' || $batch->committed_at === null)) {
                throw Refused::conflict('ST-02', ['state' => is_string($batch?->state) ? $batch->state : null]);
            }

            $state = $connection->table('import_rows')->where('id', $row->id)->lock('for update')->value('validation_state');

            // The dry run tries again a row an earlier dry run refused (INVALID on the copy); a committed row is always done (BD-11).
            if ($state !== 'VALID' && ! ($dryRun && $state === 'INVALID')) {
                throw Refused::conflict('BD-11');
            }

            $connection->table('import_rows')->where('id', $row->id)->update($last
                ? ($result->type === self::SKIPPED
                    ? ['validation_state' => 'SKIPPED', 'errors' => null, 'updated_at' => $context->now()]
                    : ['validation_state' => 'COMMITTED', 'result_entity_type' => $result->type, 'result_entity_id' => $result->id, 'errors' => null, 'updated_at' => $context->now()])
                : ['errors' => json_encode([['field' => '', 'reason' => self::IN_PROGRESS]]), 'updated_at' => $context->now()]);
        };
    }
}
