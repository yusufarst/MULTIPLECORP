<?php

namespace App\Modules\ADM\Actions;

use App\Modules\ADM\Services\Requirements;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * `ADM LinkRequirementEvidence` (ROUTE-165; INT-165; WF-ADM-01; BR-ADM-01; NX-03): the document version or the evidence
 * that satisfies a requirement linked, the link locking what it links — the project (LK-01), the document's chain (LK-30)
 * or the evidence (LK-29). By the item's mode: a generated item takes a version of its type, an uploaded item an evidence
 * of its type, an either item one of them, a client-original item only an evidence (FS-12; the database's trigger the
 * backstop). Whether the linked record satisfies it — issued and rendered, an authentic upload — is derived on reading.
 * `requirements.manage` of the project's company.
 */
final class LinkRequirementEvidence
{
    public const TYPE = 'ADM.LinkRequirementEvidence';

    public const CAPABILITY = 'requirements.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly Requirements $requirements,
    ) {}

    /**
     * @param  ?int  $versionId  a document version of the project's company, or null
     * @param  ?int  $evidenceId  an evidence of the project's company, or null — exactly one of the two
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, int $requirementId, ?int $versionId, ?int $evidenceId, string $target): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->companyId), ['project' => $project->publicId, 'requirement' => $requirementId, 'target' => $target]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $requirementId, $versionId, $evidenceId): CommandResult {
            $document = $versionId === null ? null : DB::table('document_versions')->where('id', $versionId)->value('document_id');
            $locks = Requirements::locks($project->id);
            $locks = is_numeric($document) ? $locks->add('documents', ['id' => (int) $document]) : $locks;
            $context->lock($evidenceId === null ? $locks : $locks->add('evidence_documents', ['id' => $evidenceId]));
            Requirements::open($project->id);
            $current = Requirements::find($project->id, $requirementId) ?? throw Refused::conflict('ST-02', ['state' => 'REMOVED']);

            if ($current['default_invoice']) {
                // L-27: the invoice item is satisfied by the invoiced value, never by a link.
                throw Refused::rejected('WF-ADM-01', ['reason' => 'invoice']);
            }

            if ($versionId !== null) {
                // Read under LK-30: a void or a revision that committed first leaves no issued version to link (NX-03).
                $version = DB::table('document_versions')->where('id', $versionId)->where('company_id', $project->companyId)->first(['document_type_code', 'state']);

                if ($current['client_original'] || $current['mode'] === 'UPLOADED' || $version === null
                    || ($current['document_type'] !== null && $version->document_type_code !== $current['document_type'])) {
                    throw Refused::rejected('BR-ADM-01', ['reason' => 'mode']);
                }

                if ($version->state !== 'ISSUED') {
                    // A draft never satisfies an item (architecture challenge 15b); a voided or superseded version no longer can.
                    throw Refused::rejected('BR-ADM-01', ['reason' => 'not_issued']);
                }
            } else {
                $type = DB::table('evidence_documents')->where('id', $evidenceId)->where('company_id', $project->companyId)->value('evidence_type');

                if ($current['mode'] === 'GENERATED' || $type === null || ($current['evidence_type'] !== null && $type !== $current['evidence_type'])) {
                    throw Refused::rejected('BR-ADM-01', ['reason' => 'mode']);
                }
            }

            $linked = DB::table('admin_requirement_links')->where('admin_requirement_id', $requirementId)->whereNull('unlinked_at')
                ->where($versionId !== null ? 'document_version_id' : 'evidence_document_id', $versionId ?? $evidenceId)->exists();

            if ($linked) {
                throw Refused::rejected('NX-03', ['reason' => 'linked']);
            }

            return new CommandResult('admin_requirement_link', $this->requirements->link($context, $current, $versionId, $evidenceId));
        });
    }
}
