<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Data\ProjectInput;
use App\Modules\PRJ\Services\CommercialConfirmation;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `PRJ RecordOpeningProject` (AX-28; WF-MIG-01; DATABASE §24): an opening import row's open project — of origin
 * OPENING, keyed to its row, numbered at the cutoff after its period's seed (NM-03) — with its remaining demand
 * lines, priced, and its MIGRATION confirmation pinning them, so fulfilment and billing continue from the cutover;
 * its reservations are re-created afterwards through the ordinary commands (IMP-08). Driven only.
 */
final class RecordOpeningProject
{
    public const TYPE = 'PRJ.RecordOpeningProject';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ProjectRecords $records,
        private readonly CommercialConfirmation $confirmation,
    ) {}

    public function run(string $commandId, int $actorUserId, int $importRow, ProjectInput $input, string $businessDate): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $input->companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, null, [...$input->payload, 'import_row' => $importRow]));

        return $this->envelope->run($request, function (CommandContext $context) use ($importRow, $input, $businessDate): CommandResult {
            $context->lock(ProjectRecords::locks([], $input->companyId));
            $project = $this->records->create($context, $input, $businessDate, $importRow);

            // The MIGRATION confirmation needs the project ACTIVE — its unit and PIC named — and every line priced (WF-PRJ-02).
            if (DB::table('projects')->where('id', $project->id)->value('state') !== 'ACTIVE') {
                throw Refused::rejected('WF-PRJ-02', ['reason' => 'details']);
            }

            $this->confirmation->confirm($context, $project->id, $input->companyId, 'MIGRATION', null, $businessDate, 'Impor data awal');

            return new CommandResult('project', $project->id);
        });
    }
}
