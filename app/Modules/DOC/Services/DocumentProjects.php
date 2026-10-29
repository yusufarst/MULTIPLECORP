<?php

namespace App\Modules\DOC\Services;

use Illuminate\Support\Facades\DB;

/**
 * The project a document belongs to through its subject (DATABASE §4.10 `documents_subject_by_type`): its own
 * `project_id` for DOC-01, DOC-09 and DOC-11, else the project of the purchase, the dispatch or receiving movement, the
 * direct shipment, the invoice, the delivery, the service handover or the parent document it was issued for. DOC
 * publishes it as one SQL expression over a `documents d` row, so a reader names a project's documents in its own
 * statement without one query per document (QB-01) — the administrative checklist among them (TASK-035; BR-ADM-01).
 */
final class DocumentProjects
{
    /** The subject's project of the document aliased `d`, null when its subject belongs to none. */
    public const SQL = <<<'SQL'
        coalesce(d.project_id,
            (select p.project_id from purchases p where p.id = d.purchase_id),
            (select m.project_id from stock_movements m where m.id = coalesce(d.dispatch_movement_id, d.receiving_movement_id)),
            (select c.project_id from dropship_confirmations c where c.id = d.dropship_confirmation_id),
            (select i.project_id from invoices i where i.id = d.invoice_id),
            (select x.project_id from deliveries x where x.id = d.delivery_id),
            (select h.project_id from service_handovers h where h.id = d.service_handover_id),
            (select coalesce(pd.project_id, (select pi.project_id from invoices pi where pi.id = pd.invoice_id)) from documents pd where pd.id = d.parent_document_id))
        SQL;

    /** The project of the version's document, null when its subject belongs to none. */
    public static function ofVersion(int $versionId): ?int
    {
        $row = DB::selectOne('select '.self::SQL.' as project_id from document_versions v join documents d on d.id = v.document_id where v.id = ?', [$versionId]);
        $project = is_object($row) ? ($row->project_id ?? null) : null;

        return is_numeric($project) ? (int) $project : null;
    }
}
