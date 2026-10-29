<?php

namespace Tests\Support;

use App\Modules\ADM\Actions\LinkRequirementEvidence;
use App\Modules\DOC\Actions\VoidDocument;
use App\Modules\DOC\Models\Document;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Models\Project;
use App\Support\Command\Outcome;
use Illuminate\Support\Str;

/**
 * The checklist's link and DOC's void as the integration races run them in their own sessions (TASK-035; NX-03; NX-12;
 * LK-30), each named by identifiers and answering its outcome as a word.
 */
final class RequirementRaces
{
    public static function link(int $actorUserId, int $projectId, int $requirementId, int $versionId): string
    {
        $project = Project::query()->findOrFail($projectId);

        return self::word(app(LinkRequirementEvidence::class)->run((string) Str::uuid(), $actorUserId, new ProjectRef($project->id, $project->company_id, $project->public_id),
            $requirementId, $versionId, null, "versi:{$versionId}"));
    }

    public static function void(int $actorUserId, int $documentId): string
    {
        return self::word(app(VoidDocument::class)->run((string) Str::uuid(), $actorUserId, Document::query()->findOrFail($documentId), 'Salah pihak'));
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }
}
