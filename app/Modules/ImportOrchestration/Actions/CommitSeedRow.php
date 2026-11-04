<?php

namespace App\Modules\ImportOrchestration\Actions;

use App\Modules\ImportOrchestration\Services\RowRunner;
use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Services\NumberingSeeds;
use App\Modules\ORG\Services\NumberingState;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;

/**
 * `IMPORT CommitSeedRow` (AX-28; NM-03; HO-32; `DIR-079` CB-01): a batch's seed row for a company's numbered type and
 * period writes the period's counter after every legacy number of that period — its first number the one after the
 * last, or 1 where there is none —, the counter recording the row that seeded it; under NM-03's period rule a seed
 * row for a period already live is recorded SKIPPED, writing no counter. Driven only, by the Owner's commit.
 */
final class CommitSeedRow
{
    public const TYPE = 'IMPORT.CommitSeedRow';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly NumberingSeeds $seeds,
        private readonly NumberingState $state,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(string $commandId, int $actorUserId, int $companyId, int $importRow, SequenceType $type, string $periodKey, int $first, string $cutoff): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, null, ['row' => $importRow, 'type' => $type->value, 'period' => $periodKey, 'first' => $first]));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $importRow, $type, $periodKey, $first, $cutoff): CommandResult {
            $key = ['company_id' => $companyId, 'sequence_type' => $type->value, 'period_key' => $periodKey];
            $context->lock((new LockSet)->add('number_sequences', $key));
            $period = NumberingPeriod::fromKey($periodKey);
            $boundary = max($cutoff, $this->state->boundary($companyId, $type) ?? $cutoff);

            if (NumberingState::isLive($period, $boundary, $this->state->goLive())) {
                return new CommandResult(RowRunner::SKIPPED, null);
            }

            $seeded = $this->seeds->seed($context, $companyId, $type, $period, $first);
            $context->connection->table('number_sequences')->where($key)->update(['seeded_by_import_row_id' => $importRow]);
            $this->audit->record(
                action: 'IMPORT.NumberSeedCommitted',
                authorityClass: AuthorityClass::OwnerOnly,
                actorUserId: $context->request->actorUserId,
                companyId: $companyId,
                entityType: 'number_sequence',
                commandId: $context->request->commandId,
                change: AuditChange::created(['sequence_type' => $type->value, 'period_key' => $periodKey, 'first_number' => $first, 'created' => $seeded['created']]),
                metadata: ['import_row_id' => $importRow],
            );

            return new CommandResult('number_sequence', $importRow);
        });
    }
}
