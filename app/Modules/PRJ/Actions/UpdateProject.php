<?php

namespace App\Modules\PRJ\Actions;

use App\Modules\PRJ\Data\LineInput;
use App\Modules\PRJ\Data\ProjectInput;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\ProjectRecords;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `PRJ UpdateProject` (ROUTE-152; INT-152; NX-03; WF-PRJ-01; J-PRJ-01): "Ubah proyek" at the version the form read —
 * the version covers the draft lines, so two Admins editing different lines of one draft conflict and the later one
 * gets CONFLICT with its input kept (ST-01; PX-07). Lines change freely before confirmation and never after (L-20);
 * the channel only before the first channel-dependent commit (BR-PRJ-05; C-51). The company changes only while the
 * project is a DRAFT without committed records (L-21): the draft is discarded, its number retired, and a new project of
 * the other company draws that company's number, both companies in the actor's scope (DIR-090 (6); L-37; CS-03).
 */
final class UpdateProject
{
    public const TYPE = 'PRJ.UpdateProject';

    public const CAPABILITY = 'projects.manage';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ProjectRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, Project $project, int $lockVersion, ProjectInput $input, string $businessDate, bool $prefilled): Outcome
    {
        $moving = $input->companyId !== $project->company_id;
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $project->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, self::companyKey($project->company_id), [...$input->payload, 'project' => $project->public_id, 'lock_version' => $lockVersion, 'business_date' => $businessDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $lockVersion, $input, $businessDate, $prefilled, $moving): CommandResult {
            $context->lock(ProjectRecords::locks(array_values(array_unique(array_filter([$project->id, $input->linkedProjectId]))), $moving ? $input->companyId : null));

            if (! $moving) {
                $this->records->amend($context, $project, $lockVersion, $input);

                return new CommandResult('project', $project->id);
            }

            $current = DB::table('projects')->where('id', $project->id)->first(['state', 'lock_version']);

            if ($current === null || Row::text($current, 'lock_version') !== (string) $lockVersion) {
                throw Refused::conflict('ST-01');
            }

            if (Row::text($current, 'state') !== 'DRAFT') {
                throw Refused::rejected('L-21');
            }

            if ($context->authority()?->allows(self::CAPABILITY, $input->companyId) !== true) {
                throw new AuthorizationException;
            }

            BusinessDateRule::check($businessDate, $prefilled);
            $created = $this->records->create($context, self::carried($input, $project->id, $context->authority()->allows('finance.view', $project->company_id)), $businessDate);
            $this->records->discard($context, $project, 'Company changed while a DRAFT (L-21); replaced by '.$created->project_number.'.', $created->public_id);

            return new CommandResult('project', $created->id);
        });
    }

    /**
     * The input for the new project of the other company: every line new, and what the actor did not both see in the draft
     * and send for the target — a line's price, a SIPLAH amount — carried from the draft as stored, so nothing is lost with
     * the move whichever company's `finance.view` the actor holds (PJ-12).
     */
    private static function carried(ProjectInput $input, int $projectId, bool $sourceMoney): ProjectInput
    {
        $snapshot = ProjectRecords::snapshot($projectId);
        $prices = [];

        foreach ($snapshot['lines'] as $line) {
            $prices[$line['id']] = $line['unit_price'];
        }

        $entered = $sourceMoney && $input->money;
        $lines = array_map(fn (LineInput $line): LineInput => new LineInput(null, $line->productId, $line->kind, $line->quantityScale, $line->description, $line->quantity,
            $line->unitId, $line->ratio, $line->quantityBase, $line->id === null ? ($input->money ? $line->price : null) : ($entered ? $line->price : $prices[$line->id] ?? null), $line->mode), $input->lines);
        $siplah = $input->siplah;

        if ($siplah !== null && ! $entered) {
            foreach (ProjectInput::SIPLAH_AMOUNTS as $amount) {
                $stored = $snapshot['siplah'][$amount] ?? null;
                $siplah[$amount] = is_numeric($stored) ? (string) $stored : null;
            }
        }

        return new ProjectInput($input->companyId, $input->clientId, $input->unitId, $input->addressId, $input->contactId, $input->channel, $input->title, $input->deadline,
            $input->linkedProjectId, $input->linkReason, $lines, $siplah, true, $input->payload, $input->linkKept);
    }

    private static function companyKey(int $companyId): ?string
    {
        $id = DB::table('companies')->where('id', $companyId)->value('public_id');

        return is_string($id) ? $id : null;
    }
}
