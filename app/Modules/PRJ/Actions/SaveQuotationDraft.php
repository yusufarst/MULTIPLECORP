<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Modules\PRJ\Services\QuotationRecords;
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
 * `PRJ SaveQuotationDraft` (ROUTE-154; INT-154; NX-03; WF-QUO-01; J-QUO-01): the quotation's draft — the project's
 * first written from its lines (O-01), then its validity and lines saved at the version the page read, the revision's
 * `lock_version` covering its lines so two editors of one draft meet CONFLICT (ST-01; M-38; UXS-11). `quotation.manage`
 * with `finance.view` of the project's company (RG-06); the project at LK-01. A quotation moves no stock or money
 * (BR-QUO-03).
 */
final class SaveQuotationDraft
{
    public const TYPE = 'PRJ.SaveQuotationDraft';

    public const CAPABILITY = 'quotation.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly QuotationRecords $quotations,
    ) {}

    /**
     * @param  ?string  $revision  the draft revision the page showed, or null to write the project's first draft
     * @param  array<int, array{quantity: string, quantity_base: string, price: ?string, description: string}>  $lines  by line id
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, Project $project, ?string $revision, ?int $lockVersion, ?string $validUntil, array $lines, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, self::companyKey($project->company_id), [...$payload, 'project' => $project->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $revision, $lockVersion, $validUntil, $lines): CommandResult {
            $context->lock(ProjectRecords::locks([$project->id]));

            if ($context->authority()?->allows('finance.view', $project->company_id) !== true) {
                throw new AuthorizationException;
            }

            if (! in_array(DB::table('projects')->where('id', $project->id)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
                throw Refused::rejected('L-11', ['reason' => 'closed']);
            }

            $latest = QuotationRecords::latest($project->id);

            if ($revision === null) {
                if ($latest !== null) {
                    throw Refused::conflict('ST-02', ['state' => $latest['state']]);
                }

                return new CommandResult('quotation_revision', $this->quotations->draft($context, $project->id, $project->company_id, null));
            }

            if ($latest === null || $latest['public_id'] !== strtolower($revision)) {
                throw Refused::conflict('ST-02', ['state' => $latest['state'] ?? 'DELETED']);
            }

            $this->quotations->save($context, $latest['id'], $project->company_id, $lockVersion ?? -1, $validUntil, $lines, true);

            return new CommandResult('quotation_revision', $latest['id']);
        });
    }

    public static function companyKey(int $companyId): ?string
    {
        $id = DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($id) ? $id : null;
    }
}
