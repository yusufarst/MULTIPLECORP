<?php

namespace Tests\Support;

use App\Modules\PRJ\Actions\AnswerQuotation;
use App\Modules\PRJ\Actions\IssueQuotation;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Support\Str;

/**
 * The quotation commands as the integration races run them in their own sessions (TASK-033), each named by identifiers
 * and answering its outcome as a word: ROUTE-154's save of the draft at a version, ROUTE-155's issue and ROUTE-157's
 * approval, each with its own `command_id`.
 */
final class QuotationRaces
{
    /** ROUTE-154 at the version the caller read, the one line at the quantity given. */
    public static function save(int $actorId, int $projectId, string $revision, int $lockVersion, int $lineId, string $quantity): string
    {
        return self::word(app(SaveQuotationDraft::class)->run((string) Str::uuid(), $actorId, Project::query()->findOrFail($projectId), $revision, $lockVersion, null,
            [$lineId => ['quantity' => $quantity, 'quantity_base' => $quantity, 'price' => '20000', 'description' => 'Buku paket']], ['jumlah' => $quantity]));
    }

    public static function issue(int $actorId, int $projectId, string $revision, int $lockVersion): string
    {
        return self::word(app(IssueQuotation::class)->run((string) Str::uuid(), $actorId, Project::query()->findOrFail($projectId), $revision, $lockVersion, BusinessDate::today(), true));
    }

    public static function approve(int $actorId, int $projectId, string $revision): string
    {
        return self::word(app(AnswerQuotation::class)->run((string) Str::uuid(), $actorId, Project::query()->findOrFail($projectId), $revision, true, null, BusinessDate::today(), true));
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }
}
