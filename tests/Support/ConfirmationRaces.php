<?php

namespace Tests\Support;

use App\Modules\PRJ\Actions\CancelRemainingScope;
use App\Modules\PRJ\Actions\ConfirmWithoutQuotation;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The confirmation's and the scope's commands as the integration races run them in their own sessions (TASK-034), each
 * named by identifiers and answering its outcome as a word: ROUTE-158's "Konfirmasi Proyek" and ROUTE-159's "Batalkan
 * Sisa", each with its own `command_id`.
 */
final class ConfirmationRaces
{
    public static function confirm(int $actorUserId, int $projectId, int $evidenceId): string
    {
        $evidence = (string) DB::table('evidence_documents')->where('id', $evidenceId)->value('public_id');

        return self::word(app(ConfirmWithoutQuotation::class)->run((string) Str::uuid(), $actorUserId, $project = Project::query()->findOrFail($projectId), $project->lock_version, $evidenceId, $evidence, null, BusinessDate::today(), true));
    }

    public static function cancel(int $actorUserId, int $projectId, int $lineId, string $quantity): string
    {
        return self::word(app(CancelRemainingScope::class)->run((string) Str::uuid(), $actorUserId, Project::query()->findOrFail($projectId), [$lineId => $quantity], 'Klien membatalkan',
            BusinessDate::today(), true, null, ['line' => $lineId, 'quantity' => $quantity]));
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }
}
