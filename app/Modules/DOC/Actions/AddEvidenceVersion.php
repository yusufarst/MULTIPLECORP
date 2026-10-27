<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Data\EvidenceIdentity;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Queries\EvidenceMatches;
use App\Modules\DOC\Services\EvidenceRegistry;
use App\Modules\DOC\Services\EvidenceUse;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandResult;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * `DOC AddEvidenceVersion` (ROUTE-230; INT-230; NX-07; L-41; IP-16 step 4): a new version of an evidence — "Tambahkan
 * sebagai Versi Baru" —, its file the actor's own upload of the evidence's company (DIR-089 (5)). Before its
 * transaction it holds FU-10's content key and runs FL-10's content lookups (BD-15); in it, the actor's row and the
 * evidence at LK-29. A version of evidence that is linked or cited is registered but becomes current only through
 * "Jadikan Versi Berlaku" (ReplaceEvidence; NX-14), so what depended on it is re-evaluated there; any other becomes
 * current at once. A version never overwrites a file (DATABASE §22).
 */
final class AddEvidenceVersion
{
    public const TYPE = 'DOC.AddEvidenceVersion';

    public function __construct(
        private readonly EvidenceRegistry $registry,
        private readonly EvidenceMatches $matches,
        private readonly EvidenceUse $use,
        private readonly EffectiveCapabilities $capabilities,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{file: string, note: ?string, acknowledged: list<string>, reason: ?string}  $input
     */
    public function run(string $commandId, int $actorUserId, EvidenceDocument $evidence, ?string $companyPublicId, array $input): Outcome
    {
        $actor = $this->capabilities->for($actorUserId);
        $this->use->ensureVisible($actor, $evidence);
        $request = $this->registry->request($commandId, self::TYPE, $actorUserId, $evidence->company_id, $companyPublicId ?? 'POOL', ['evidence' => $evidence->public_id, ...$input]);
        $date = $evidence->document_date->format('Y-m-d');
        // CI-05: a replayed warning answers with the matches its actor may view now.
        $replayState = fn (Outcome $outcome): ?array => $outcome->code === 'FL-10' ? $this->currentWarning($actorUserId, $evidence, $input['file'], $date) : null;
        $replayed = $this->registry->replay($request, $replayState);

        if ($replayed !== null) {
            return $replayed;
        }

        try {
            $file = $this->registry->ownUpload($actorUserId, $input['file'], $evidence->company_id);
        } catch (ModelNotFoundException $unknown) {
            // The same command committed meanwhile, its upload now a version: its recorded outcome answers (CI-05).
            return $this->registry->replay($request, $replayState) ?? throw $unknown;
        }

        return $this->registry->withKeys([EvidenceIdentity::contentKey($file->sha256)], function () use ($request, $replayState, $file, $evidence, $input, $date): Outcome {
            $lookups = $this->matches->of($file->sha256, $file->id, $evidence->declared_issuer, $evidence->evidence_type, $evidence->issuer_reference, $date, $evidence->proven_amount, $evidence->company_id, $evidence->id);

            return $this->registry->run($request, function (CommandContext $context) use ($lookups, $file, $evidence, $input): CommandResult {
                $context->lock((new LockSet)->add('evidence_documents', ['id' => $evidence->id]));
                $this->registry->confirmSession();
                $authority = $this->registry->ensureHandles($context, $evidence->evidence_type, $evidence->company_id);
                // DP-13 under the authority re-read at LK-03.
                $found = $this->registry->classify($authority, $lookups);
                $row = EvidenceDocument::query()->findOrFail($evidence->id);
                $override = $this->registry->warn($authority, $row->company_id, $found['visible'], $input['acknowledged'], $input['reason']);
                $now = $context->now();
                $version = $this->registry->addVersion($context, $row, $file, $input['note'], $now);
                $inUse = $this->use->inUse($row->id);
                $before = $row->current_version_id;

                if (! $inUse) {
                    $row->forceFill(['current_version_id' => $version->id, 'lock_version' => $row->lock_version + 1])->save();
                }

                $this->audit->record(
                    action: 'DOC.EvidenceVersionAdded',
                    authorityClass: AuthorityClass::Adm,
                    actorUserId: $context->request->actorUserId,
                    companyId: $row->company_id,
                    entityType: 'evidence_document',
                    entityId: $row->id,
                    entityPublicId: $row->public_id,
                    reason: $version->reason,
                    commandId: $context->request->commandId,
                    change: AuditChange::updated(['current_version_id' => $before], ['current_version_id' => $inUse ? $before : $version->id]),
                    metadata: ['file' => $file->public_id, 'version_no' => $version->version_no, 'current' => ! $inUse, 'reproduction' => $found['reproduction'], 'pool' => $row->company_id === null],
                );

                if ($override) {
                    $this->registry->recordOverride($context, $row, $found['visible'], (string) $input['reason']);
                }

                $this->registry->announceHidden($context, $row, $found['hidden']);

                return new CommandResult('evidence_document', $row->id);
            }, $replayState);
        });
    }

    /**
     * A refused warning's current matches for its replay, under the actor's authority read again.
     *
     * @return array<string, mixed>|null
     */
    private function currentWarning(int $actorUserId, EvidenceDocument $evidence, string $filePublicId, string $date): ?array
    {
        try {
            $file = $this->registry->ownUpload($actorUserId, $filePublicId, $evidence->company_id);
        } catch (ModelNotFoundException) {
            return null;
        }

        $actor = $this->capabilities->fresh($actorUserId);
        $found = $this->registry->classify($actor, $this->matches->of($file->sha256, $file->id, $evidence->declared_issuer, $evidence->evidence_type, $evidence->issuer_reference, $date, $evidence->proven_amount, $evidence->company_id, $evidence->id));

        return $this->registry->warningState($actor, $evidence->company_id, $found['visible']);
    }
}
