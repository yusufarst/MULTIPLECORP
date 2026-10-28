<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\DOC\Actions\IssueDocument;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\DocumentIssuing;
use App\Modules\DOC\Services\DocumentSubjects;
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
 * `PRJ IssueQuotation` (ROUTE-155; INT-155; AX-15; SF-ISSUE; WF-QUO-01): "Terbitkan Penawaran" after its confirmation
 * (IP-08) — in one transaction DOC-01 issued from the draft revision through DOC's issue (TASK-029): the number drawn
 * last (LK-31), the payload frozen with the client as the project names it now (BR-SN-01; architecture challenge 1b),
 * the rendition owed; and the revision made *Terkirim* with its validity, its lines frozen by trigger. The page's
 * revision and version are checked (ST-01, ST-02); a second issue of one draft is CONFLICT with the issued number
 * (BD-06; M-19). REJECTED when the period's numbering may create no counter (NM-03; C-10, MSG-17). `quotation.manage`
 * with `finance.view` of the project's company (PJ-15).
 */
final class IssueQuotation
{
    public const TYPE = 'PRJ.IssueQuotation';

    public const CAPABILITY = 'quotation.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentIssuing $issuing,
        private readonly QuotationRecords $quotations,
    ) {}

    /** @param  ?string  $factsDigest  the digest of the facts the confirmed paper composed: a client changed since is a CONFLICT (architecture challenge 15a) */
    public function run(string $commandId, int $actorUserId, Project $project, string $revision, int $lockVersion, string $businessDate, bool $prefilled, ?string $factsDigest = null): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->company_id), ['project' => $project->public_id, 'revision' => strtolower($revision),
                'lock_version' => $lockVersion, 'business_date' => $businessDate, 'prefilled' => $prefilled, 'facts' => $factsDigest]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $revision, $lockVersion, $businessDate, $prefilled, $factsDigest): CommandResult {
            $subject = new DocumentSubject(QuotationSubjects::QUOTATION, 'STANDARD', $project->company_id, ['project_id' => $project->id]);
            $provider = $this->subjects->of($subject);
            // The chain and its draft readied with the revision (SaveQuotationDraft, ReviseQuotation): AX-15's locks on existing rows —
            // the project (LK-01), its company for a new chain (LK-06), the chain (LK-30) —, the counter last (LK-31).
            $document = $this->issuing->chainOf($subject) ?? throw Refused::rejected('WF-QUO-01', ['reason' => 'no_draft']);
            $context->lock(ProjectRecords::locks([$project->id])->union($this->issuing->locks($document, $subject, $provider->locks($subject))));

            if ($context->authority()?->allows('finance.view', $project->company_id) !== true) {
                throw new AuthorizationException;
            }

            $latest = QuotationRecords::latest($project->id);

            if ($latest === null || $latest['public_id'] !== strtolower($revision)) {
                throw Refused::conflict('ST-02', ['state' => $latest['state'] ?? 'DELETED']);
            }

            if ($latest['state'] !== 'DRAFT') {
                $number = DB::table('document_versions')->where('quotation_revision_id', $latest['id'])->value('formatted_number');

                throw Refused::conflict('BD-06', ['number' => is_string($number) ? $number : '']);
            }

            if ($latest['lock_version'] !== $lockVersion) {
                throw Refused::conflict('ST-01');
            }

            $version = $this->issuing->issue($context, $document, $subject, $provider, null, $businessDate, $prefilled, null, $factsDigest, authorized: true);
            IssueDocument::render($context, $version);
            $this->quotations->sent($context, $latest['id'], $project->company_id, (string) $latest['valid_until'], (string) $version->formatted_number);

            return new CommandResult('quotation_revision', $latest['id']);
        });
    }
}
