<?php

namespace App\Modules\PRJ\Services;

use App\Modules\CAT\Data\Quantity;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Services\CompanyGuard;
use App\Modules\ORG\Services\NumberAllocator;
use App\Modules\PRJ\Data\LineInput;
use App\Modules\PRJ\Data\ProjectInput;
use App\Modules\PRJ\Models\Project;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The project record's writes (WF-PRJ-01; NX-03; DATABASE §4.5, §9), inside the command that holds the project at LK-01
 * and, for new business, its company at LK-06: a creation drawing its number from the company's PROJECT counter under
 * LK-31, the last lock (NM-08; C-10); an amendment at the version the form read (ST-01), which covers the draft lines
 * (PX-07); a DRAFT's discard with its number's tombstone in the same transaction (L-37); the activation once company,
 * client, unit, PIC and channel are set (SM:Project). Each is a business audit event with before and after and the
 * individual actor (BR-XC-01; LG-01).
 */
final class ProjectRecords
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly NumberAllocator $numbers,
        private readonly CompanyGuard $companies,
    ) {}

    /**
     * LK-01 for the projects a command names — its own and a linked one (NX-03) —, LK-06 for the company a new project starts business in.
     *
     * @param  list<int>  $projectIds
     */
    public static function locks(array $projectIds, ?int $newCompanyId = null): LockSet
    {
        $set = new LockSet;

        foreach ($projectIds as $id) {
            $set->add('projects', ['id' => $id]);
        }

        return $newCompanyId === null ? $set : $set->union(CompanyGuard::locks($newCompanyId));
    }

    /** Whether a commercial confirmation exists: from then on commercial changes need a revision or a re-confirmation (L-20). */
    public static function confirmed(int $projectId): bool
    {
        return DB::table('commercial_confirmations')->where('project_id', $projectId)->exists();
    }

    /**
     * Whether the channel is locked (BR-PRJ-05; C-51): its SIPLAH details locked by the first channel-dependent commit,
     * the commercial confirmation, which writes that lock (DATABASE §25; TASK-034), or that confirmation itself.
     */
    public static function channelLocked(int $projectId): bool
    {
        return DB::table('project_siplah_details')->where('project_id', $projectId)->whereNotNull('locked_at')->exists() || self::confirmed($projectId);
    }

    /**
     * Creates a DRAFT project of the input's company, its number drawn last (NM-08), and activates it when its parties are set.
     *
     * @throws Refused C-54 for an inactive company; C-10 when the period may create no counter (NM-03)
     */
    public function create(CommandContext $context, ProjectInput $input, string $businessDate): Project
    {
        $this->companies->ensureOpenForNewBusiness($context, $input->companyId);
        $this->ensureLink($context, $input);
        $drawn = $this->numbers->draw($context, $input->companyId, SequenceType::Project, $businessDate);
        $now = $context->now();

        $project = new Project;
        $project->forceFill([
            ...self::header($input),
            'company_id' => $input->companyId,
            'project_number' => $drawn->formatted,
            'period_key' => $drawn->periodKey,
            'sequence_no' => $drawn->sequenceNo,
            'state' => 'DRAFT',
            'command_id' => $context->request->commandId,
            'command_line_no' => 1,
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ])->save();

        $this->writeLines($context, $project->id, $input->companyId, $input->lines, $input->money);

        if ($input->channel === 'SIPLAH') {
            $this->writeSiplah($context, $project->id, $input);
        }

        $this->record($context, 'PRJ.ProjectCreated', $project->id, $project->public_id, $input->companyId, AuditChange::created(self::snapshot($project->id)), $businessDate);
        $this->activate($context, $project->id);

        return $project;
    }

    /**
     * Amends the project at the version the form read (ST-01; PX-07): its lines freely before confirmation and never
     * after (L-20), its channel only before the first channel-dependent commit (BR-PRJ-05), its SIPLAH values until
     * their lock (C-51). A DRAFT's company change is not an amendment — {@see self::discard()} and a new project.
     */
    public function amend(CommandContext $context, Project $project, int $lockVersion, ProjectInput $input): void
    {
        $current = DB::table('projects')->where('id', $project->id)->first(['state', 'lock_version', 'channel', 'client_organization_id']);

        if ($current === null || (int) Row::text($current, 'lock_version') !== $lockVersion) {
            throw Refused::conflict('ST-01');
        }

        if (! in_array(Row::text($current, 'state'), ['DRAFT', 'ACTIVE'], true)) {
            throw Refused::rejected('L-11');
        }

        $before = self::snapshot($project->id);
        $confirmed = self::confirmed($project->id);

        if ($confirmed && ($input->clientId !== (int) Row::text($current, 'client_organization_id') || self::linesOf($input, $before) !== self::commercial($before['lines']))) {
            throw Refused::rejected('L-20');
        }

        $locked = self::channelLocked($project->id);

        $siplahFields = [...ProjectInput::SIPLAH_TEXTS, ...($input->money ? ProjectInput::SIPLAH_AMOUNTS : [])];

        if ($locked && ($input->channel !== Row::text($current, 'channel') || ($input->siplah !== null && self::siplahChanged($before['siplah'], $input->siplah, $siplahFields)))) {
            throw Refused::rejected('BR-PRJ-05');
        }

        $this->ensureLink($context, $input);
        $changed = DB::table('projects')->where('id', $project->id)->where('lock_version', $lockVersion)
            ->update([...self::header($input), 'lock_version' => $lockVersion + 1, 'updated_at' => $context->now()]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-01');
        }

        if (! $confirmed) {
            $this->writeLines($context, $project->id, $project->company_id, $input->lines, $input->money);
        }

        if (! $locked) {
            $this->writeSiplah($context, $project->id, $input);
        }

        $change = AuditChange::updated($before, self::snapshot($project->id));

        if (! $change->isEmpty()) {
            $this->record($context, 'PRJ.ProjectAmended', $project->id, $project->public_id, $project->company_id, $change);
        }

        $this->activate($context, $project->id);
    }

    /**
     * Discards a DRAFT with no committed downstream record (L-37): its number's tombstone and its last content in the
     * audit, then the row with its lines and SIPLAH details (§19). What a committed record references stays — the
     * database refuses the delete, and the command answers REJECTED.
     *
     * @throws Refused L-37 when the project is no longer a DRAFT or a committed record references it
     */
    public function discard(CommandContext $context, Project $project, string $reason, ?string $replacedBy = null): void
    {
        $row = DB::table('projects')->where('id', $project->id)->first(['state', 'company_id', 'period_key', 'sequence_no', 'project_number', 'public_id']);

        if ($row === null || Row::text($row, 'state') !== 'DRAFT') {
            throw Refused::rejected('L-37', ['reason' => 'state']);
        }

        $snapshot = self::snapshot($project->id);
        DB::table('retired_numbers')->insert([
            'company_id' => $project->company_id,
            'sequence_type' => SequenceType::Project->value,
            'period_key' => Row::text($row, 'period_key'),
            'sequence_no' => (int) Row::text($row, 'sequence_no'),
            'formatted_number' => Row::text($row, 'project_number'),
            'retired_kind' => 'DISCARDED_DRAFT_PROJECT',
            'source_public_id' => Row::text($row, 'public_id'),
            'reason' => $reason,
            'retired_by' => $context->request->actorUserId,
            'command_id' => $context->request->commandId,
            'command_line_no' => 1,
            'recorded_at' => $context->now(),
        ]);

        try {
            // A savepoint: a committed record still referencing the draft refuses the delete and leaves the command whole.
            $context->connection->transaction(fn () => DB::table('projects')->where('id', $project->id)->where('state', 'DRAFT')->delete());
        } catch (QueryException $error) {
            // A restricting key answers 23001, a plain one 23503: either way a committed record still names the draft.
            if (in_array((string) $error->getCode(), ['23001', '23503'], true)) {
                throw Refused::rejected('L-37', ['reason' => 'downstream']);
            }

            throw $error;
        }

        $this->record($context, 'PRJ.DraftProjectDiscarded', $project->id, $project->public_id, $project->company_id, AuditChange::updated($snapshot, ['state' => 'DISCARDED']), null,
            array_filter(['retired_number' => Row::text($row, 'project_number'), 'replaced_by' => $replacedBy]));
    }

    /**
     * The project's content as its audit keeps it: its header, lines and SIPLAH details.
     *
     * @return array{project: array<string, mixed>, lines: list<array{id: int, line_no: int, kind: string, product_id: int, description: string, entered_qty: string, entered_unit_id: int, qty_base: string, unit_price: ?string, fulfillment_mode: string}>, siplah: ?array<string, ?string>}
     */
    public static function snapshot(int $projectId): array
    {
        $columns = ['client_organization_id', 'client_unit_id', 'client_address_id', 'client_contact_id', 'channel', 'state', 'title', 'deadline', 'linked_project_id', 'link_reason'];
        $project = DB::table('projects')->where('id', $projectId)->first($columns);
        $header = [];

        if ($project !== null) {
            foreach ($columns as $column) {
                $header[$column] = Row::nullable($project, $column);
            }
        }
        $lines = DB::table('project_lines')->where('project_id', $projectId)->orderBy('line_no')
            ->get(['id', 'line_no', 'kind', 'product_id', 'description', 'entered_qty', 'entered_unit_id', 'qty_base', 'unit_price', 'fulfillment_mode'])->all();
        $siplah = DB::table('project_siplah_details')->where('project_id', $projectId)->first([...ProjectInput::SIPLAH_TEXTS, ...ProjectInput::SIPLAH_AMOUNTS]);
        $details = null;

        if ($siplah !== null) {
            $details = [];

            foreach ([...ProjectInput::SIPLAH_TEXTS, ...ProjectInput::SIPLAH_AMOUNTS] as $field) {
                $details[$field] = Row::nullable($siplah, $field);
            }
        }

        return [
            'project' => $header,
            'lines' => array_values(array_map(fn (object $line): array => [
                'id' => (int) Row::text($line, 'id'),
                'line_no' => (int) Row::text($line, 'line_no'),
                'kind' => Row::text($line, 'kind'),
                'product_id' => (int) Row::text($line, 'product_id'),
                'description' => Row::text($line, 'description'),
                'entered_qty' => Row::text($line, 'entered_qty'),
                'entered_unit_id' => (int) Row::text($line, 'entered_unit_id'),
                'qty_base' => Row::text($line, 'qty_base'),
                'unit_price' => Row::nullable($line, 'unit_price'),
                'fulfillment_mode' => Row::text($line, 'fulfillment_mode'),
            ], $lines)),
            'siplah' => $details,
        ];
    }

    /** @return array<string, mixed> */
    private static function header(ProjectInput $input): array
    {
        return [
            'client_organization_id' => $input->clientId,
            'client_unit_id' => $input->unitId,
            'client_address_id' => $input->addressId,
            'client_contact_id' => $input->contactId,
            'channel' => $input->channel,
            'title' => $input->title,
            'deadline' => $input->deadline,
            'linked_project_id' => $input->linkedProjectId,
            'link_reason' => $input->linkedProjectId === null ? null : $input->linkReason,
        ];
    }

    /**
     * XL-4: a linked project is another company's, both companies in the actor's `projects.manage` scope.
     *
     * @throws AuthorizationException when the linked project's company left the actor's scope
     */
    private function ensureLink(CommandContext $context, ProjectInput $input): void
    {
        if ($input->linkedProjectId === null) {
            return;
        }

        $company = DB::table('projects')->where('id', $input->linkedProjectId)->value('company_id');

        if (! is_numeric($company) || (int) $company === $input->companyId) {
            throw Refused::rejected('XL-4');
        }

        if ($context->authority()?->allows('projects.manage', (int) $company) !== true) {
            throw new AuthorizationException;
        }
    }

    /**
     * The draft lines as the form sent them: a removed one deleted, a kept one updated in place under its number, a new
     * one added after the highest number the project has used — a line's number is never changed or reused, so what
     * cites it keeps its place (DATABASE §4.5; `line_no` is not updatable).
     *
     * @param  list<LineInput>  $lines
     */
    private function writeLines(CommandContext $context, int $projectId, int $companyId, array $lines, bool $money): void
    {
        $existing = DB::table('project_lines')->where('project_id', $projectId)->pluck('id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();
        $highest = DB::table('project_lines')->where('project_id', $projectId)->max('line_no');
        $number = is_numeric($highest) ? (int) $highest : 0;
        $kept = array_values(array_filter(array_map(fn (LineInput $line): ?int => $line->id, $lines)));
        $removed = array_values(array_diff($existing, $kept));
        $now = $context->now();

        if ($removed !== []) {
            DB::table('project_lines')->whereIn('id', $removed)->delete();
        }

        foreach ($lines as $index => $line) {
            $values = [
                'kind' => $line->kind,
                'product_id' => $line->productId,
                'quantity_scale' => $line->quantityScale,
                'description' => $line->description,
                'entered_qty' => $line->quantity,
                'entered_unit_id' => $line->unitId,
                'ratio_to_base' => $line->ratio,
                'qty_base' => $line->quantityBase,
                'fulfillment_mode' => $line->mode,
                ...($money ? ['unit_price' => $line->price] : []),
            ];

            if ($line->id !== null) {
                DB::table('project_lines')->where('id', $line->id)->where('project_id', $projectId)
                    ->update([...$values, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now]);
            } else {
                DB::table('project_lines')->insert([...$values, 'line_no' => ++$number, 'unit_price' => $money ? $line->price : null, 'project_id' => $projectId, 'company_id' => $companyId,
                    'command_id' => $context->request->commandId, 'command_line_no' => $index + 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId]);
            }
        }
    }

    /**
     * A SIPLAH project's manual values as entered (CAP-12; BR-FIN-10) — its amounts only from an actor with
     * `finance.view` —; a project changed to REGULAR before the lock keeps its row with every value cleared, the row
     * leaving only with its DRAFT (§19).
     */
    private function writeSiplah(CommandContext $context, int $projectId, ProjectInput $input): void
    {
        $exists = DB::table('project_siplah_details')->where('project_id', $projectId)->exists();
        $fields = [...ProjectInput::SIPLAH_TEXTS, ...($input->money ? ProjectInput::SIPLAH_AMOUNTS : [])];
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $input->channel === 'SIPLAH' ? ($input->siplah[$field] ?? null) : null;
        }

        if (! $exists) {
            if ($input->channel === 'SIPLAH') {
                DB::table('project_siplah_details')->insert([...$values, 'project_id' => $projectId, 'command_id' => $context->request->commandId, 'command_line_no' => 1,
                    'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
            }

            return;
        }

        if (! self::siplahChanged(self::snapshot($projectId)['siplah'], $values, $fields)) {
            return;
        }

        DB::table('project_siplah_details')->where('project_id', $projectId)
            ->update([...$values, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $context->now()]);
    }

    /** DRAFT → ACTIVE once company, client, unit, PIC and channel are set (SM:Project), with its ACTIVATE transition. */
    private function activate(CommandContext $context, int $projectId): void
    {
        $row = DB::table('projects')->where('id', $projectId)->first(['state', 'client_unit_id', 'client_contact_id', 'company_id', 'public_id']);

        if ($row === null || Row::text($row, 'state') !== 'DRAFT' || Row::nullable($row, 'client_unit_id') === null || Row::nullable($row, 'client_contact_id') === null) {
            return;
        }

        $now = $context->now();
        DB::table('projects')->where('id', $projectId)->where('state', 'DRAFT')
            ->update(['state' => 'ACTIVE', 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now]);
        DB::table('project_state_transitions')->insert([
            'project_id' => $projectId,
            'company_id' => (int) Row::text($row, 'company_id'),
            'from_state' => 'DRAFT',
            'to_state' => 'ACTIVE',
            'transition_kind' => 'ACTIVATE',
            'decided_by' => $context->request->actorUserId,
            'command_id' => $context->request->commandId,
            'command_line_no' => 1,
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);
        $this->record($context, 'PRJ.ProjectActivated', $projectId, Row::text($row, 'public_id'), (int) Row::text($row, 'company_id'), AuditChange::updated(['state' => 'DRAFT'], ['state' => 'ACTIVE']));
    }

    /**
     * The lines' commercial content as the input states it, an unchanged price kept where the actor may not see it.
     *
     * @param  array{lines: list<array{id: int, product_id: int, entered_qty: string, entered_unit_id: int, fulfillment_mode: string, description: string, unit_price: ?string}>}  $before
     * @return list<array<string, string>>
     */
    private static function linesOf(ProjectInput $input, array $before): array
    {
        $prices = [];

        foreach ($before['lines'] as $line) {
            $prices[$line['id']] = $line['unit_price'];
        }

        return array_map(fn (LineInput $line): array => self::commercialLine($line->id, $line->productId, $line->quantity, $line->unitId, $line->mode, $line->description,
            $input->money ? $line->price : ($line->id === null ? null : $prices[$line->id] ?? null)), $input->lines);
    }

    /**
     * @param  list<array{id: int, product_id: int, entered_qty: string, entered_unit_id: int, fulfillment_mode: string, description: string, unit_price: ?string}>  $lines
     * @return list<array<string, string>>
     */
    private static function commercial(array $lines): array
    {
        return array_map(fn (array $line): array => self::commercialLine($line['id'], $line['product_id'], $line['entered_qty'], $line['entered_unit_id'],
            $line['fulfillment_mode'], $line['description'], $line['unit_price']), $lines);
    }

    /** @return array<string, string> */
    private static function commercialLine(?int $id, int $product, string $quantity, int $unit, string $mode, string $description, ?string $price): array
    {
        return [
            'id' => (string) $id,
            'product' => (string) $product,
            'quantity' => Quantity::toBase($quantity, 1),
            'unit' => (string) $unit,
            'mode' => $mode,
            'description' => $description,
            'price' => $price === null ? '' : self::money($price),
        ];
    }

    /** A non-negative amount with two decimals, exactly as text, so "1500000" and "1500000.00" compare equal. */
    private static function money(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * Whether the entered SIPLAH values differ from the stored ones, each amount compared as an amount.
     *
     * @param  array<string, ?string>|null  $current
     * @param  array<string, ?string>|null  $entered
     * @param  list<string>  $fields
     */
    private static function siplahChanged(?array $current, ?array $entered, array $fields): bool
    {
        foreach ($fields as $field) {
            $stored = $current[$field] ?? null;
            $given = $entered[$field] ?? null;

            if (in_array($field, ProjectInput::SIPLAH_AMOUNTS, true)) {
                $stored = $stored === null ? null : self::money($stored);
                $given = $given === null ? null : self::money($given);
            }

            if ($stored !== $given) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $metadata */
    private function record(CommandContext $context, string $action, int $projectId, string $publicId, int $companyId, AuditChange $change, ?string $businessDate = null, array $metadata = []): void
    {
        $this->audit->record(
            action: $action,
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'project',
            entityId: $projectId,
            entityPublicId: $publicId,
            commandId: $context->request->commandId,
            change: $change,
            businessDate: $businessDate,
            metadata: $metadata,
        );
    }
}
