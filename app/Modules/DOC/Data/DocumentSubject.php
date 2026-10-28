<?php

namespace App\Modules\DOC\Data;

use App\Modules\DOC\Models\Document;

/**
 * A document's record-bound subject (DATABASE §4.10's arc; FU-12): its type and mode, the company its record belongs
 * to — the document's own (CS-03) — and the arc's columns with the records' ids, one chain per subject and type.
 */
final class DocumentSubject
{
    /** The arc's columns of `documents`, in the order DATABASE §4.10 lists them. */
    public const COLUMNS = [
        'project_id', 'purchase_id', 'dispatch_movement_id', 'dropship_confirmation_id', 'invoice_id', 'payment_id',
        'parent_document_id', 'delivery_id', 'service_handover_id', 'receiving_movement_id',
    ];

    /**
     * @param  array<string, int>  $records  the arc's columns the subject sets, each with its record's id
     */
    public function __construct(
        public readonly string $typeCode,
        public readonly string $mode,
        public readonly int $companyId,
        public readonly array $records,
    ) {}

    public static function of(Document $document): self
    {
        $records = [];

        foreach (self::COLUMNS as $column) {
            $id = $document->getAttribute($column);

            if (is_int($id)) {
                $records[$column] = $id;
            }
        }

        return new self($document->document_type_code, $document->mode, $document->company_id, $records);
    }
}
