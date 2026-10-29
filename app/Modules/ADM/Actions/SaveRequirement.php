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
 * `ADM AddRequirement · TightenRequirement` (ROUTE-164; INT-164; WF-ADM-01; NX-03): in "Kesiapan dokumen" a requirement
 * added — a catalog document, an external document type or a free description; *Wajib* or *Opsional*; generated,
 * uploaded or either; "Harus asli dari klien" only with the uploaded mode — or made stricter at the version the page read
 * while not yet satisfied (ST-01). Never the Owner's fields: waiver eligibility stays as it is, and loosening a required
 * non-waivable item is REJECTED (OD-05; OD-06). `requirements.manage` of the project's company; the project at LK-01.
 */
final class SaveRequirement
{
    public const ADD = 'ADM.AddRequirement';

    public const TIGHTEN = 'ADM.TightenRequirement';

    public const CAPABILITY = 'requirements.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly Requirements $requirements,
    ) {}

    /**
     * @param  ?int  $requirementId  the item to tighten, null to add one
     * @param  array{kind: string, document_type: ?string, evidence_type: ?string, description: string, required: bool, mode: string, client_original: bool}  $input
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, ProjectRef $project, ?int $requirementId, ?int $lockVersion, array $input, array $payload): Outcome
    {
        $type = $requirementId === null ? self::ADD : self::TIGHTEN;
        $request = new CommandRequest($commandId, $type, $actorUserId, $project->companyId, self::CAPABILITY,
            Fingerprint::of($type, SaveQuotationDraft::companyKey($project->companyId), [...$payload, 'project' => $project->publicId]));
        $eligibility = array_key_exists('kelayakan', $payload);

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $requirementId, $lockVersion, $input, $eligibility): CommandResult {
            $context->lock(Requirements::locks($project->id));
            Requirements::open($project->id);

            if ($eligibility) {
                // OD-05: waiver eligibility is the Owner's own command (ROUTE-167), never part of an Admin's edit.
                throw Refused::rejected('OD-05', ['reason' => 'owner']);
            }

            if ($input['client_original'] && $input['mode'] !== 'UPLOADED') {
                throw Refused::rejected('WF-ADM-01', ['reason' => 'client_original']);
            }

            if ($requirementId === null) {
                return new CommandResult('admin_requirement', $this->requirements->add($context, $project->id, $project->companyId, $input));
            }

            $current = Requirements::find($project->id, $requirementId) ?? throw Refused::conflict('ST-02', ['state' => 'REMOVED']);

            if ($current['default_invoice']) {
                // L-27: the invoice item is required and satisfied by the invoiced value; there is nothing to tighten.
                throw Refused::rejected('WF-ADM-01', ['reason' => 'invoice']);
            }

            $this->requirements->change($context, $current, $lockVersion ?? -1, ['required' => $input['required'], 'mode' => $input['mode'], 'client_original' => $input['client_original']], false, null);

            return new CommandResult('admin_requirement', $requirementId);
        });
    }

    /** Whether a document type exists for a catalog requirement. */
    public static function knownType(string $typeCode): bool
    {
        return DB::table('document_types')->where('code', $typeCode)->exists();
    }
}
