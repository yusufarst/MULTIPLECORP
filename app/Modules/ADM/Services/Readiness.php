<?php

namespace App\Modules\ADM\Services;

use App\Modules\DOC\Services\DocumentProjects;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * Each administrative requirement's status, derived each time and never stored (WF-ADM-01; DESIGN_SYSTEM §12.1;
 * BR-ADM-01; L-01): *Tidak Berlaku* when the head of its waiver chain stands — a waiver-eligible item, its waiver
 * neither superseded nor reopened by a correction (BR-ADM-02; BR-ADM-03) —; *Terpenuhi* when an active link satisfies
 * it by its mode — a generated item an issued version of its type and of its project with a ready rendition, an
 * uploaded item an evidence of its type whose current file is no reproduction of a system rendition, a client-original
 * item only such an authentic upload (FS-12; architecture challenge 15b) —, or, for the default invoice item, when the invoiced value
 * equals the confirmed value less the cancelled scope (L-27; the client returns of TASK-048 join it); *Disiapkan*
 * when a generated item's document is still a draft; else *Belum Ada*. One statement over the projects asked, never
 * one per project (QB-01; PF-01).
 *
 * @phpstan-type Item array{id: int, project: int, company: int, kind: string, document_type: ?string, evidence_type: ?string, description: string, required: bool, mode: string, client_original: bool, waiver_eligible: bool, default_invoice: bool, lock_version: int, status: string}
 */
final class Readiness
{
    /** The states of DESIGN_SYSTEM §12.1's administrative requirement family: *Belum Ada*, *Disiapkan*, *Terpenuhi*, *Tidak Berlaku*. */
    public const MISSING = 'MISSING';

    public const PREPARED = 'DRAFT_ONLY';

    public const SATISFIED = 'SATISFIED';

    public const WAIVED = 'WAIVED';

    /** The statuses that let a required item pass the administration predicate (WF-PRJ-03 predicate 5). */
    public const MET = [self::SATISFIED, self::WAIVED];

    /** An unmet item over {@see self::select()}'s columns, as {@see self::item()} derives it — for a reader that filters (PG-039). */
    public const UNMET = 'not (s.waived or s.invoiced or (not s.is_default_invoice_item and (s.by_document or s.by_evidence)))';

    private const STATUS = <<<'SQL'
        select r.id, r.project_id, r.company_id, r.requirement_kind, r.document_type_code, r.evidence_type, r.description, r.is_required,
            r.satisfaction_mode, r.requires_client_original, r.waiver_eligible, r.is_default_invoice_item, r.lock_version,
            -- The head of the waiver chain: superseded by none; a reopening row stands for no waiver.
            exists (select 1 from admin_requirement_waivers w where w.admin_requirement_id = r.id and not w.reopened_by_correction
                and not exists (select 1 from admin_requirement_waivers n where n.supersedes_waiver_id = w.id)) and r.waiver_eligible as waived,
            exists (select 1 from admin_requirement_links l join document_versions v on v.id = l.document_version_id join documents d on d.id = v.document_id
                where l.admin_requirement_id = r.id and l.unlinked_at is null and v.state = 'ISSUED' and {document_project} = r.project_id
                    and r.satisfaction_mode in ('GENERATED', 'EITHER') and not r.requires_client_original
                    and (r.document_type_code is null or v.document_type_code = r.document_type_code)
                    and (select a.state from document_renditions a where a.document_version_id = v.id order by a.attempt_no desc limit 1) = 'READY') as by_document,
            exists (select 1 from admin_requirement_links l join evidence_documents e on e.id = l.evidence_document_id
                join evidence_document_versions ev on ev.id = e.current_version_id join file_objects f on f.id = ev.file_object_id
                where l.admin_requirement_id = r.id and l.unlinked_at is null and r.satisfaction_mode in ('UPLOADED', 'EITHER')
                    and (r.evidence_type is null or e.evidence_type = r.evidence_type)
                    -- FS-12: an upload equal to a stored rendition is a reproduction, never an authentic upload.
                    and not exists (select 1 from file_objects g join document_renditions dr on dr.file_object_id = g.id
                        where g.purpose = 'RENDITION' and g.sha256 = f.sha256)) as by_evidence,
            r.document_type_code is not null and r.satisfaction_mode in ('GENERATED', 'EITHER') and exists (select 1 from documents d
                join document_versions v on v.document_id = d.id and v.state = 'DRAFT'
                where d.company_id = r.company_id and d.document_type_code = r.document_type_code and {document_project} = r.project_id) as drafted,
            -- L-27: equal, never more — an invoice above the confirmed value less the cancelled scope needs its revision.
            r.is_default_invoice_item and coalesce((select c.invoiced_sales_value = c.confirmed_sales_value - coalesce((
                select sum(round(p.sales_value * b.cancelled_qty_base / nullif(p.qty_base, 0), 2))
                from project_lines pl join confirmation_line_pins p on p.id = pl.current_pin_id join project_line_balances b on b.project_line_id = pl.id
                where pl.project_id = r.project_id), 0)
                from project_commercial_balances c where c.project_id = r.project_id), false) as invoiced
        from admin_requirements r
        SQL;

    /** The derived flags over `admin_requirements r`, which a reader completes with its own conditions. */
    public static function select(): string
    {
        return str_replace('{document_project}', DocumentProjects::SQL, self::STATUS);
    }

    /**
     * The active requirements of the projects with their derived status, by project, missing items first.
     *
     * @param  list<int>  $projectIds
     * @return array<int, list<Item>>
     */
    public static function of(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = DB::select(self::select()." where r.state = 'ACTIVE' and r.project_id in ({$placeholders}) order by r.project_id, r.id", $projectIds);
        $byProject = [];

        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $item = self::item($row);
            $byProject[$item['project']][] = $item;
        }

        foreach ($byProject as $project => $items) {
            usort($items, fn (array $a, array $b): int => [in_array($a['status'], self::MET, true), $a['id']] <=> [in_array($b['status'], self::MET, true), $b['id']]);
            $byProject[$project] = $items;
        }

        return $byProject;
    }

    /** @return Item */
    public static function item(object $row): array
    {
        $flag = fn (string $column): bool => ($row->{$column} ?? false) === true;
        $status = match (true) {
            $flag('waived') => self::WAIVED,
            $flag('invoiced'), ! $flag('is_default_invoice_item') && ($flag('by_document') || $flag('by_evidence')) => self::SATISFIED,
            $flag('drafted') => self::PREPARED,
            default => self::MISSING,
        };

        return [
            'id' => (int) Row::text($row, 'id'),
            'project' => (int) Row::text($row, 'project_id'),
            'company' => (int) Row::text($row, 'company_id'),
            'kind' => Row::text($row, 'requirement_kind'),
            'document_type' => Row::nullable($row, 'document_type_code'),
            'evidence_type' => Row::nullable($row, 'evidence_type'),
            'description' => Row::text($row, 'description'),
            'required' => $flag('is_required'),
            'mode' => Row::text($row, 'satisfaction_mode'),
            'client_original' => $flag('requires_client_original'),
            'waiver_eligible' => $flag('waiver_eligible'),
            'default_invoice' => $flag('is_default_invoice_item'),
            'lock_version' => (int) Row::text($row, 'lock_version'),
            'status' => $status,
        ];
    }
}
