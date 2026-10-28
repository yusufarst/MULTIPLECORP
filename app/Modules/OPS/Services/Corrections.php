<?php

namespace App\Modules\OPS\Services;

use App\Modules\OPS\Data\CorrectionLegality;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use InvalidArgumentException;

/**
 * The shared correction service of every module's correcting commands (BR-CR-01–BR-CR-06; ADMIN_FLOW §11): the
 * primitive checked against BR-CR-06's legality matrix before anything is written, and the correction recorded with
 * its reason, its individual actor, its time and its link to the original — a revision, void, reversal, return or
 * superseding decision —, both records staying visible, the original read as "Dikoreksi oleh", the correction as
 * "Mengoreksi" (BR-CR-02; FS-02); a correction gathered by its case when it belongs to one (SF-CASE). Nothing here
 * deletes a committed fact: only a draft is deleted, by its own command, with audit.
 */
final class Corrections
{
    /** The relations through which a record corrects another (AuditRelation). */
    public const CORRECTING = [AuditRelation::Revises, AuditRelation::Voids, AuditRelation::Reverses, AuditRelation::Returns, AuditRelation::Supersedes];

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * BR-CR-06: refuses a primitive the original's lifecycle prohibits, or one allowed only with its coupled companion
     * when the command brings none (BR-CR-03).
     *
     * @throws Refused BR-CR-06
     */
    public static function ensureLegal(string $lifecycle, string $primitive, bool $withCompanion = false): void
    {
        if (! CorrectionLegality::allows($lifecycle, $primitive, $withCompanion)) {
            throw Refused::rejected('BR-CR-06', ['lifecycle' => $lifecycle, 'primitive' => $primitive]);
        }
    }

    /**
     * Records a correction in the correcting command's transaction (BR-CR-01): its reason required, its actor and time
     * the command's, its link to the original by the relation it corrects through, and its case when it has one —
     * with the open step of that case it completes (CM-10, CM-12).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        CommandContext $context,
        string $action,
        AuthorityClass $authority,
        ?int $companyId,
        string $entityType,
        int $entityId,
        string $entityPublicId,
        AuditRelation $relation,
        string $originalType,
        string $originalPublicId,
        string $reason,
        AuditChange $change,
        ?string $casePublicId = null,
        array $metadata = [],
        ?string $step = null,
    ): void {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A correction carries its reason (BR-CR-01).');
        }

        if (! in_array($relation, self::CORRECTING, true)) {
            throw new InvalidArgumentException("{$relation->value} is no correcting relation (BR-CR-02).");
        }

        $linkage = AuditLinkage::make()->link($relation, $originalType, $originalPublicId);

        if ($casePublicId !== null) {
            $linkage->correctionCase($casePublicId);
        }

        if ($step !== null) {
            if ($casePublicId === null || ! in_array($step, CorrectionCases::STEPS, true)) {
                throw new InvalidArgumentException('A correction closes a step only of its case (CM-10, CM-12).');
            }

            $metadata['step'] = $step;
        }

        $this->audit->record(
            action: $action,
            authorityClass: $authority,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: $entityType,
            entityId: $entityId,
            entityPublicId: $entityPublicId,
            reason: trim($reason),
            commandId: $context->request->commandId,
            change: $change,
            linkage: $linkage,
            metadata: $metadata,
        );
    }
}
