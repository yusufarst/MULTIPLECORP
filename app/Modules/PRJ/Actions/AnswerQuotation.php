<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\CommercialConfirmation;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;

/**
 * "Catat Jawaban Klien" (ROUTE-157; INT-157; WF-QUO-01; WF-PRJ-02 path A; J-QUO-01; J-PRJ-02) after its confirmation
 * (IP-08): `PRJ ApproveQuotation` — *Disetujui* on the latest SENT revision within its validity (L-25; BR-QUO-01) by
 * `projects.confirm`, committing as the project's commercial confirmation AX-13: the QUOTATION_APPROVAL confirmation,
 * one root per approved revision, a duplicate or concurrent approval CONFLICT (BD-05; C-49) — its pins, guards, SIPLAH
 * lock and reservation adjustments written by {@see CommercialConfirmation} (TASK-034), a later revision's approval the
 * re-confirmation —; `PRJ RecordQuotationRejection` — *Ditolak* by `quotation.manage`. Each decision final,
 * with its account, time and reason (SM:Quotation). A quotation moves no stock or money by itself (BR-QUO-03).
 */
final class AnswerQuotation
{
    public const APPROVE = 'PRJ.ApproveQuotation';

    public const REJECT = 'PRJ.RecordQuotationRejection';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly QuotationRecords $quotations,
        private readonly CommercialConfirmation $confirmation,
    ) {}

    public function run(string $commandId, int $actorUserId, Project $project, string $revision, bool $approve, ?string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $type = $approve ? self::APPROVE : self::REJECT;
        $request = new CommandRequest($commandId, $type, $actorUserId, $project->company_id, $approve ? 'projects.confirm' : 'quotation.manage',
            Fingerprint::of($type, SaveQuotationDraft::companyKey($project->company_id), ['project' => $project->public_id, 'revision' => strtolower($revision),
                'reason' => $reason, 'business_date' => $businessDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $revision, $approve, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(CommercialConfirmation::locks($project->id));

            if (! in_array(DB::table('projects')->where('id', $project->id)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
                throw Refused::rejected('L-11', ['reason' => 'closed']);
            }

            $latest = QuotationRecords::latest($project->id);

            if ($latest === null || $latest['public_id'] !== strtolower($revision)) {
                // BR-QUO-01: only the latest sent revision is answered; an earlier one is superseded by its revision.
                throw Refused::conflict('ST-02', ['state' => $latest['state'] ?? 'DELETED']);
            }

            if (in_array($latest['state'], ['APPROVED', 'REJECTED'], true)) {
                throw Refused::conflict('BD-05', ['state' => $latest['state']]);
            }

            if ($latest['state'] !== 'SENT') {
                throw Refused::rejected('WF-QUO-01', ['reason' => 'not_sent']);
            }

            if (! $approve) {
                $this->quotations->decide($context, $latest['id'], $project->company_id, 'REJECTED', $reason);

                return new CommandResult('quotation_revision', $latest['id']);
            }

            BusinessDateRule::check($businessDate, $prefilled);

            // Expiry is read on today's WIB date at the command's clock, never on the confirmation's chosen date (L-25; BR-DT-04).
            if (QuotationRecords::expired($latest['state'], $latest['valid_until'], BusinessDate::of($context->now()))) {
                throw Refused::rejected('L-25', ['reason' => 'expired']);
            }

            $this->quotations->decide($context, $latest['id'], $project->company_id, 'APPROVED', $reason);
            $this->confirmation->confirm($context, $project->id, $project->company_id, 'QUOTATION_APPROVAL', $latest['id'], $businessDate, $reason);

            return new CommandResult('quotation_revision', $latest['id']);
        });
    }
}
