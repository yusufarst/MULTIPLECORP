<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\CommercialConfirmation;
use App\Modules\PRJ\Services\QuotationRecords;
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
 * `PRJ ConfirmWithoutQuotation` (ROUTE-158; INT-158; WF-PRJ-02 path B; J-PRJ-02): "Konfirmasi Proyek" after its
 * confirmation (IP-08; MSG-39) — the project confirmed without a quotation on the client's order evidence, a client's SPK,
 * Surat Pesanan or SIPLAH order registered first as project-family evidence (IP-16; SF-EVIDENCE) and linked here (LK-29) —
 * through AX-13's one step (BR-PRJ-06): its lines pinned with their price and tax, the guards, the SIPLAH lock. One
 * confirmation per project on this path, a further one CONFLICT (BD-05); a quotation in flight answers through its
 * revision instead. `projects.confirm` of the project's company (W4-11).
 */
final class ConfirmWithoutQuotation
{
    public const TYPE = 'PRJ.ConfirmWithoutQuotation';

    public const CAPABILITY = 'projects.confirm';

    /** The client's order evidence this path takes (WF-PRJ-02; DOC-14 uploaded, never generated). */
    public const EVIDENCE = ['CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER'];

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly CommercialConfirmation $confirmation,
    ) {}

    public function run(string $commandId, int $actorUserId, Project $project, int $evidenceId, string $evidencePublicId, ?string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($project->company_id), ['project' => $project->public_id, 'evidence' => strtolower($evidencePublicId),
                'reason' => $reason, 'business_date' => $businessDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $evidenceId, $reason, $businessDate, $prefilled): CommandResult {
            $context->lock(CommercialConfirmation::locks($project->id, $evidenceId));

            if (! in_array(DB::table('projects')->where('id', $project->id)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
                throw Refused::rejected('L-11', ['reason' => 'closed']);
            }

            // One root per project on this path, re-read under LK-01: a confirmed project is re-confirmed only through its quotation (BD-05).
            if (CommercialConfirmation::confirmed($project->id)) {
                throw Refused::conflict('BD-05', ['state' => 'CONFIRMED']);
            }

            $quotation = QuotationRecords::latest($project->id);

            if ($quotation !== null && in_array($quotation['state'], ['SENT', 'APPROVED'], true)) {
                // A sent quotation is answered through its revision (ROUTE-157), the only confirmation path while it stands.
                throw Refused::rejected('WF-PRJ-02', ['reason' => 'quotation']);
            }

            if (! DB::table('project_lines')->where('project_id', $project->id)->where('qty_base', '>', 0)->exists()) {
                throw Refused::rejected('WF-PRJ-02', ['reason' => 'no_lines']);
            }

            BusinessDateRule::check($businessDate, $prefilled);
            $confirmation = $this->confirmation->confirm($context, $project->id, $project->company_id, 'NO_QUOTATION', null, $businessDate, $reason, $evidenceId);

            return new CommandResult('commercial_confirmation', $confirmation);
        });
    }
}
