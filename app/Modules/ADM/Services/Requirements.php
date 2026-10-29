<?php

namespace App\Modules\ADM\Services;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The administrative checklist's writes (WF-ADM-01; DATABASE §4.11; NX-03), each under the project's lock (LK-01) with
 * its business audit: a requirement added or tightened by an Admin (ROUTE-164), its satisfying document or evidence
 * linked (ROUTE-165), *Tidak Berlaku* on a waiver-eligible item (ROUTE-166; BR-ADM-02), the Owner's waiver eligibility
 * (ROUTE-167; OD-05) and relaxation or removal (ROUTE-168; OD-06), a waiver superseded (ROUTE-169; CM-27) or reopened by
 * a correction (BR-ADM-03). Every item is non-waivable by default (BR-PRJ-03); an Admin never loosens a required
 * non-waivable item nor touches waiver eligibility.
 *
 * @phpstan-import-type Item from Readiness
 */
final class Requirements
{
    public const KINDS = ['CATALOG_DOCUMENT', 'EXTERNAL_DOCUMENT', 'FREE_TEXT'];

    public const MODES = ['GENERATED', 'UPLOADED', 'EITHER'];

    /** The reopening row's reason when the Owner withdraws waiver eligibility (OD-05). */
    public const WITHDRAWN = 'Waiver eligibility withdrawn by the Owner (OD-05).';

    public function __construct(private readonly AuditRecorder $audit) {}

    /** NX-03's locks: the project's row (LK-01), under which its checklist changes. */
    public static function locks(int $projectId): LockSet
    {
        return (new LockSet)->add('projects', ['id' => $projectId]);
    }

    /** L-11: a checklist changes on a DRAFT or ACTIVE project; after completion or cancellation a correction applies instead. */
    public static function open(int $projectId): void
    {
        if (! in_array(DB::table('projects')->where('id', $projectId)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
            throw Refused::rejected('L-11', ['reason' => 'closed']);
        }
    }

    /**
     * A requirement of the project, read under its lock; null when it is not the project's or was removed.
     *
     * @return ?Item
     */
    public static function find(int $projectId, int $requirementId): ?array
    {
        $rows = Readiness::of([$projectId])[$projectId] ?? [];

        foreach ($rows as $row) {
            if ($row['id'] === $requirementId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * ROUTE-164's new requirement: an Admin's, never waiver-eligible (OD-05).
     *
     * @param  array{kind: string, document_type: ?string, evidence_type: ?string, description: string, required: bool, mode: string, client_original: bool}  $input
     */
    public function add(CommandContext $context, int $projectId, int $companyId, array $input): int
    {
        $now = $context->now();
        $id = (int) DB::table('admin_requirements')->insertGetId([
            'project_id' => $projectId,
            'company_id' => $companyId,
            'requirement_kind' => $input['kind'],
            'document_type_code' => $input['kind'] === 'CATALOG_DOCUMENT' ? $input['document_type'] : null,
            'evidence_type' => $input['kind'] === 'EXTERNAL_DOCUMENT' ? $input['evidence_type'] : null,
            'description' => $input['description'],
            'is_required' => $input['required'],
            'satisfaction_mode' => $input['mode'],
            'requires_client_original' => $input['client_original'],
            'command_id' => $context->request->commandId,
            'command_line_no' => self::line('admin_requirements', $context),
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);
        $this->record($context, 'ADM.RequirementAdded', AuthorityClass::Adm, $projectId, $companyId, $id, null, AuditChange::created(array_map(
            fn (mixed $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, array_filter($input, fn (mixed $value): bool => $value !== null),
        )));

        return $id;
    }

    /**
     * ROUTE-164's tightening, or ROUTE-168's relaxation by the Owner: the item's required flag, mode and client-original
     * flag at the version the page read, while it is not yet satisfied (DATABASE §13; ST-01). An Admin only tightens: an
     * edit that loosens any item is REJECTED (OD-06; DIR-024 D-5) — a required waiver-eligible item is handled by *Tidak
     * Berlaku* with its reason, never by loosening (WF-ADM-01). Each change is audited with the authority that made it.
     *
     * @param  Item  $current
     * @param  array{required: bool, mode: string, client_original: bool}  $change
     */
    public function change(CommandContext $context, array $current, int $lockVersion, array $change, bool $owner, ?string $reason): void
    {
        if ($current['lock_version'] !== $lockVersion) {
            throw Refused::conflict('ST-01');
        }

        if ($current['status'] === Readiness::SATISFIED && ! $owner) {
            throw Refused::rejected('WF-ADM-01', ['reason' => 'satisfied']);
        }

        if ($change['client_original'] && $change['mode'] !== 'UPLOADED') {
            throw Refused::rejected('WF-ADM-01', ['reason' => 'client_original']);
        }

        // FS-12: an item a document version satisfies becomes a client original only once that link is unlinked.
        if ($change['client_original'] && ! $current['client_original'] && DB::table('admin_requirement_links')->where('admin_requirement_id', $current['id'])
            ->whereNull('unlinked_at')->whereNotNull('document_version_id')->exists()) {
            throw Refused::rejected('WF-ADM-01', ['reason' => 'linked_document']);
        }

        $looser = ($current['required'] && ! $change['required'])
            || ($current['client_original'] && ! $change['client_original'])
            || ($current['mode'] !== $change['mode'] && $current['mode'] !== 'EITHER');

        if ($looser && ! $owner) {
            throw Refused::rejected('OD-06', ['reason' => 'owner']);
        }

        $before = ['required' => $current['required'], 'mode' => $current['mode'], 'client_original' => $current['client_original']];
        $changed = DB::table('admin_requirements')->where('id', $current['id'])->where('lock_version', $lockVersion)->update([
            'is_required' => $change['required'],
            'satisfaction_mode' => $change['mode'],
            'requires_client_original' => $change['client_original'],
            'lock_version' => $lockVersion + 1,
            'updated_at' => $context->now(),
        ]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-01');
        }

        $this->record($context, $looser ? 'ADM.RequirementRelaxed' : 'ADM.RequirementTightened', $owner ? AuthorityClass::OwnerOnly : AuthorityClass::Adm,
            $current['project'], $current['company'], $current['id'], $reason, AuditChange::updated(self::texts($before), self::texts($change)));
    }

    /**
     * ROUTE-168's removal of a required item by the Owner, with its reason (OD-06; DIR-024 D-5).
     *
     * @param  Item  $current
     */
    public function remove(CommandContext $context, array $current, int $lockVersion, string $reason): void
    {
        $now = $context->now();
        $changed = DB::table('admin_requirements')->where('id', $current['id'])->where('lock_version', $lockVersion)->where('state', 'ACTIVE')->update([
            'state' => 'REMOVED', 'removed_by' => $context->request->actorUserId, 'removed_at' => $now, 'removed_reason' => $reason,
            'lock_version' => $lockVersion + 1, 'updated_at' => $now,
        ]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-01');
        }

        $this->record($context, 'ADM.RequirementRemoved', AuthorityClass::OwnerOnly, $current['project'], $current['company'], $current['id'], $reason,
            AuditChange::updated(['state' => 'ACTIVE'], ['state' => 'REMOVED']));
    }

    /**
     * ROUTE-167: the Owner's waiver eligibility at the version read (OD-05). Withdrawn, it reopens a standing waiver in
     * the same transaction — a row marked as reopened —, so a later grant never revives that waiver without a new
     * decision and its reason (BR-ADM-02).
     *
     * @param  Item  $current
     */
    public function eligibility(CommandContext $context, array $current, int $lockVersion, bool $eligible): void
    {
        $changed = DB::table('admin_requirements')->where('id', $current['id'])->where('lock_version', $lockVersion)
            ->update(['waiver_eligible' => $eligible, 'lock_version' => $lockVersion + 1, 'updated_at' => $context->now()]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-01');
        }

        $this->record($context, 'ADM.WaiverEligibilitySet', AuthorityClass::OwnerOnly, $current['project'], $current['company'], $current['id'], null,
            AuditChange::updated(['waiver_eligible' => $current['waiver_eligible'] ? 'true' : 'false'], ['waiver_eligible' => $eligible ? 'true' : 'false']));
        $head = self::head($current['id']);

        if (! $eligible && $head !== null && ! $head['reopened']) {
            $this->waive($context, $current, self::WITHDRAWN, true, 'ADM.WaiverReopened', AuthorityClass::OwnerOnly);
        }
    }

    /**
     * ROUTE-165: the satisfying document version or evidence linked, its target locked by the caller (NX-03).
     *
     * @param  Item  $current
     */
    public function link(CommandContext $context, array $current, ?int $versionId, ?int $evidenceId): int
    {
        $id = (int) DB::table('admin_requirement_links')->insertGetId([
            'admin_requirement_id' => $current['id'],
            'company_id' => $current['company'],
            'document_version_id' => $versionId,
            'evidence_document_id' => $evidenceId,
            'linked_by' => $context->request->actorUserId,
            'command_id' => $context->request->commandId,
            'command_line_no' => self::line('admin_requirement_links', $context),
            'recorded_at' => $context->now(),
            'recorded_by' => $context->request->actorUserId,
        ]);

        // The evidence's own link to the item (DATABASE §4.10), so its page shows it and DOC's unlink reaches this one (L-41).
        if ($evidenceId !== null && ! DB::table('evidence_links')->where('evidence_document_id', $evidenceId)->where('admin_requirement_id', $current['id'])->whereNull('unlinked_at')->exists()) {
            DB::table('evidence_links')->insert([
                'evidence_document_id' => $evidenceId,
                'evidence_scope' => 'COMPANY',
                'company_id' => $current['company'],
                'role' => $current['client_original'] ? 'CLIENT_ORIGINAL' : 'PRIMARY_PROOF',
                'admin_requirement_id' => $current['id'],
                'command_id' => $context->request->commandId,
                'command_line_no' => self::line('evidence_links', $context),
                'recorded_at' => $context->now(),
                'recorded_by' => $context->request->actorUserId,
            ]);
        }

        $this->record($context, 'ADM.RequirementLinked', AuthorityClass::Adm, $current['project'], $current['company'], $current['id'], null,
            AuditChange::updated([], array_filter(['document_version' => $versionId === null ? null : (string) $versionId, 'evidence' => $evidenceId === null ? null : (string) $evidenceId])));

        return $id;
    }

    /**
     * L-41: a link whose record no longer stands — its version voided or revised, its evidence unlinked from the item —
     * unlinked once with its reason, the row kept (DATABASE §4.11, §19), in the changing command's transaction.
     */
    public function unlink(CommandContext $context, int $linkId, string $reason): void
    {
        $link = DB::table('admin_requirement_links as l')->join('admin_requirements as r', 'r.id', '=', 'l.admin_requirement_id')
            ->where('l.id', $linkId)->whereNull('l.unlinked_at')->first(['r.id', 'r.project_id', 'r.company_id', 'l.document_version_id', 'l.evidence_document_id']);

        if ($link === null) {
            return;
        }

        DB::table('admin_requirement_links')->where('id', $linkId)
            ->update(['unlinked_at' => $context->now(), 'unlinked_by' => $context->request->actorUserId, 'unlink_reason' => $reason]);
        $this->record($context, 'ADM.RequirementUnlinked', AuthorityClass::Adm, (int) Row::text($link, 'project_id'), (int) Row::text($link, 'company_id'),
            (int) Row::text($link, 'id'), $reason, AuditChange::updated(array_filter(['document_version' => Row::nullable($link, 'document_version_id'),
                'evidence' => Row::nullable($link, 'evidence_document_id')]), ['linked' => 'false']));
    }

    /**
     * ROUTE-166 or ROUTE-169: a waiver at the head of the item's chain, superseding the head when there is one — a
     * superseding decision (CM-27) or a waiver after a correction reopened the item. `reopened` records a reopening.
     *
     * @param  Item  $current
     */
    public function waive(CommandContext $context, array $current, string $reason, bool $reopened = false, string $action = 'ADM.RequirementWaived', AuthorityClass $class = AuthorityClass::Adm): int
    {
        $head = self::head($current['id']);
        $id = (int) DB::table('admin_requirement_waivers')->insertGetId([
            'admin_requirement_id' => $current['id'],
            'company_id' => $current['company'],
            'reason' => $reason,
            'decided_by' => $context->request->actorUserId,
            'supersedes_waiver_id' => $head['id'] ?? null,
            'reopened_by_correction' => $reopened,
            'command_id' => $context->request->commandId,
            'command_line_no' => self::line('admin_requirement_waivers', $context),
            'recorded_at' => $context->now(),
            'recorded_by' => $context->request->actorUserId,
        ]);
        $this->record($context, $action, $class, $current['project'], $current['company'], $current['id'], $reason,
            AuditChange::updated(['waiver' => $head === null ? '' : (string) $head['id']], ['waiver' => (string) $id, 'reopened' => $reopened ? 'true' : 'false']));

        return $id;
    }

    /**
     * The head of an item's waiver chain — superseded by no later row —, with whether it is a reopening.
     *
     * @return ?array{id: int, reopened: bool, decided_by: int}
     */
    public static function head(int $requirementId): ?array
    {
        $row = DB::selectOne(<<<'SQL'
            select w.id, w.reopened_by_correction, w.decided_by from admin_requirement_waivers w
            where w.admin_requirement_id = ? and not exists (select 1 from admin_requirement_waivers n where n.supersedes_waiver_id = w.id)
            SQL, [$requirementId]);

        return is_object($row) ? ['id' => (int) Row::text($row, 'id'), 'reopened' => ($row->reopened_by_correction ?? false) === true, 'decided_by' => (int) Row::text($row, 'decided_by')] : null;
    }

    /** The command's next line in a table, so one command may write several rows of it (C-48). */
    private static function line(string $table, CommandContext $context): int
    {
        return DB::table($table)->where('command_id', $context->request->commandId)->count() + 1;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function texts(array $values): array
    {
        return array_map(fn (mixed $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : ''), $values);
    }

    private function record(CommandContext $context, string $action, AuthorityClass $class, int $projectId, int $companyId, int $requirementId, ?string $reason, AuditChange $change): void
    {
        $publicId = DB::table('projects')->where('id', $projectId)->value('public_id');
        $this->audit->record(
            action: $action,
            authorityClass: $class,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'project',
            entityId: $projectId,
            entityPublicId: is_string($publicId) ? $publicId : null,
            reason: $reason,
            commandId: $context->request->commandId,
            change: $change,
            metadata: ['requirement' => $requirementId],
        );
    }
}
