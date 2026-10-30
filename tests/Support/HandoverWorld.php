<?php

namespace Tests\Support;

use App\Modules\FUL\Actions\RecordHandover;
use App\Modules\FUL\Actions\ReverseHandover;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-047's world (TEST_STRATEGY §5): TASK-038's company, client and service with an actor holding the handover family;
 * service projects confirmed through PRJ's own commands, and the handover's own actions answering their outcome as a word,
 * by identifiers only, so a session in another process can run them. A project may mix in goods lines to prove a handover
 * refuses them; the BAST's numbering is started on demand.
 */
final class HandoverWorld
{
    public const CAPABILITIES = ['projects.view', 'projects.confirm', 'projects.cancel_scope', 'handover.record', 'fulfillment.correct', 'documents.issue', 'evidence.view', 'evidence.upload'];

    public ReservationWorld $base;

    public int $actorId;

    public Company $company;

    private function __construct() {}

    public static function make(): self
    {
        $world = new self;
        $world->base = ReservationWorld::make();
        $world->company = $world->base->company;
        $world->actorId = Authorization::admin([$world->company->id], self::CAPABILITIES)->id;

        return $world;
    }

    /** @param  list<string>  $modes */
    public function project(array $modes = ['SERVICE'], string $qty = '10', bool $confirm = true): Project
    {
        return $this->base->project($modes, $qty, $confirm);
    }

    public static function line(Project $project, int $number = 1): int
    {
        return ReservationWorld::line($project, $number);
    }

    /** The BAST's numbering for the current year, go-live before it, as DOC's engine asks (NM-03). */
    public function numbering(): void
    {
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::goLive("{$year}-01-01");
        Numbering::scheme($this->company, SequenceType::Doc08, "{$year}-01-01", 'YEAR', 'BAST/{tahun}/{nomor:4}');
        Numbering::started($this->company, SequenceType::Doc08, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    }

    /** ROUTE-208's action as its own command, answered as a word. */
    public static function record(int $actorUserId, int $projectId, int $lineId, string $quantity, ?string $date = null): string
    {
        $project = Project::query()->findOrFail($projectId);
        $day = $date ?? BusinessDate::today();

        return self::word(app(RecordHandover::class)->run((string) Str::uuid(), $actorUserId, new ProjectRef($project->id, $project->company_id, $project->public_id), $day, false, 'Bu Sari',
            [['line' => $lineId, 'quantity' => $quantity, 'scope' => 'Instalasi selesai']], null, [], null, ['line' => $lineId, 'quantity' => $quantity, 'date' => $day]));
    }

    /** ROUTE-209's action as its own command, answered as a word. */
    public static function reverse(int $actorUserId, int $projectId, int $handoverId, int $lineId, string $reason = 'Salah catat'): string
    {
        $project = Project::query()->findOrFail($projectId);
        $publicId = (string) DB::table('service_handovers')->where('id', $handoverId)->value('public_id');
        $projectLine = (int) DB::table('service_handover_lines')->where('id', $lineId)->value('project_line_id');

        return self::word(app(ReverseHandover::class)->run((string) Str::uuid(), $actorUserId, new ProjectRef($project->id, $project->company_id, $project->public_id), $handoverId, $publicId,
            $lineId, $projectLine, RowHandle::of('service_handover_lines', $lineId), $reason));
    }

    /** One side of the race of a handover with a remaining-scope cancellation of its line, named so a session in another process can run it. */
    public static function handoverOrCancel(string $side, int $actorUserId, int $projectId, int $lineId, string $quantity): string
    {
        return $side === 'handover' ? self::record($actorUserId, $projectId, $lineId, $quantity) : ConfirmationRaces::cancel($actorUserId, $projectId, $lineId, $quantity);
    }

    /** The project line's handed-over quantity on its guard. */
    public static function handedOver(int $lineId): string
    {
        return (string) DB::table('project_line_balances')->where('project_line_id', $lineId)->value('handed_over_qty_base');
    }

    private static function word(Outcome $outcome): string
    {
        return trim("{$outcome->kind} ".($outcome->kind === Outcome::COMMITTED ? '' : (string) $outcome->code));
    }
}
