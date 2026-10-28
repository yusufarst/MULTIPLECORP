<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\DocumentIssuing;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Modules\PRJ\Services\QuotationSubjects;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `PRJ ReviseQuotation` (ROUTE-156; INT-156; NX-03; WF-QUO-01): "Buat Revisi" — a new DRAFT from the latest non-draft
 * revision with its lines, whatever it answered: *Terkirim*, *Kedaluwarsa*, *Ditolak*, or *Disetujui*, whose revision
 * needs re-approval (BR-QUO-02) —, the earlier revisions and their DOC-01 versions kept retrievable; the accepted subset
 * of a partial acceptance saved on it (L-20). `quotation.manage` with `finance.view`; the project at LK-01.
 */
final class ReviseQuotation
{
    public const TYPE = 'PRJ.ReviseQuotation';

    public const CAPABILITY = 'quotation.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly QuotationRecords $quotations,
        private readonly DocumentIssuing $issuing,
    ) {}

    public function run(string $commandId, int $actorUserId, Project $project, string $revision): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->company_id), ['project' => $project->public_id, 'revision' => strtolower($revision)]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $revision): CommandResult {
            $context->lock(ProjectRecords::locks([$project->id]));

            if ($context->authority()?->allows('finance.view', $project->company_id) !== true) {
                throw new AuthorizationException;
            }

            if (! in_array(DB::table('projects')->where('id', $project->id)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
                throw Refused::rejected('L-11', ['reason' => 'closed']);
            }

            $latest = QuotationRecords::latest($project->id);

            if ($latest === null || $latest['public_id'] !== strtolower($revision) || $latest['state'] === 'DRAFT') {
                throw Refused::conflict('ST-02', ['state' => $latest['state'] ?? 'DELETED']);
            }

            // The DOC-01 draft revising the issued version, readied before the clock so the next issue supersedes it (C-11).
            $this->issuing->draftFor($context, new DocumentSubject(QuotationSubjects::QUOTATION, 'STANDARD', $project->company_id, ['project_id' => $project->id]));

            return new CommandResult('quotation_revision', $this->quotations->draft($context, $project->id, $project->company_id, $latest['id']));
        });
    }
}
