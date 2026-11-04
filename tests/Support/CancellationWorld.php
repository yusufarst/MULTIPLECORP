<?php

namespace Tests\Support;

use App\Modules\PRJ\Actions\CancelProject;
use App\Modules\PRJ\Models\Project;
use Illuminate\Support\Str;

/**
 * TASK-056's world (TL-05): AX-27 as its own command, by identifiers only, so a session in another process can run it (Sessions::run).
 */
final class CancellationWorld
{
    /** "Batalkan Proyek" with the project's version as the page would show it; its word. */
    public static function cancel(int $actorUserId, int $projectId, string $reason = 'Klien membatalkan kontrak'): string
    {
        $project = Project::query()->findOrFail($projectId);

        return InvoiceWorld::word(app(CancelProject::class)->run((string) Str::uuid(), $actorUserId, $project->id, $project->company_id, $project->public_id, $project->lock_version, $reason));
    }
}
