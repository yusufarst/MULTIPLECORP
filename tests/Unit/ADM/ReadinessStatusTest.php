<?php

use App\Modules\ADM\Services\Readiness;

// TL-03 (TASK-035; WF-ADM-01; BR-ADM-01; BR-ADM-02; L-27; DESIGN_SYSTEM §12.1): the status of a requirement derived
// from the facts the statement reads — a standing waiver *Tidak Berlaku* first; a satisfying link *Terpenuhi* for every
// mode, never for the default invoice item, which only its invoiced value satisfies; a draft of its document only
// *Disiapkan*; else *Belum Ada*. Nothing stored decides it.

function readinessRow(array $flags = []): object
{
    return (object) [
        'id' => 7, 'project_id' => 3, 'company_id' => 1, 'requirement_kind' => 'CATALOG_DOCUMENT', 'document_type_code' => 'DOC-09', 'evidence_type' => null,
        'description' => 'SPK', 'is_required' => true, 'satisfaction_mode' => 'GENERATED', 'requires_client_original' => false, 'waiver_eligible' => false,
        'is_default_invoice_item' => false, 'lock_version' => 0, 'waived' => false, 'by_document' => false, 'by_evidence' => false, 'drafted' => false, 'invoiced' => false,
        ...$flags,
    ];
}

it('derives each status from its facts, a waiver first (WF-ADM-01; DESIGN_SYSTEM §12.1)', function (array $flags, string $status) {
    expect(Readiness::item(readinessRow($flags))['status'])->toBe($status);
})->with([
    'nothing linked' => [[], Readiness::MISSING],
    'a draft of its document' => [['drafted' => true], Readiness::PREPARED],
    'an issued and rendered version' => [['by_document' => true, 'drafted' => true], Readiness::SATISFIED],
    'an authentic upload' => [['satisfaction_mode' => 'UPLOADED', 'by_evidence' => true], Readiness::SATISFIED],
    'a client original by an authentic upload' => [['satisfaction_mode' => 'UPLOADED', 'requires_client_original' => true, 'by_evidence' => true], Readiness::SATISFIED],
    'either mode by an upload' => [['satisfaction_mode' => 'EITHER', 'by_evidence' => true], Readiness::SATISFIED],
    'a standing waiver' => [['waived' => true, 'drafted' => true], Readiness::WAIVED],
    'the invoice item linked but not invoiced' => [['is_default_invoice_item' => true, 'by_evidence' => true, 'by_document' => true], Readiness::MISSING],
    'the invoice item invoiced' => [['is_default_invoice_item' => true, 'invoiced' => true], Readiness::SATISFIED],
]);

it('carries the item\'s own facts beside its status, never a stored one (WF-ADM-01; L-01)', function () {
    expect(Readiness::item(readinessRow(['satisfaction_mode' => 'UPLOADED', 'requires_client_original' => true, 'waiver_eligible' => true])))->toMatchArray([
        'id' => 7, 'project' => 3, 'company' => 1, 'kind' => 'CATALOG_DOCUMENT', 'document_type' => 'DOC-09', 'evidence_type' => null, 'required' => true,
        'mode' => 'UPLOADED', 'client_original' => true, 'waiver_eligible' => true, 'default_invoice' => false, 'lock_version' => 0, 'status' => Readiness::MISSING,
    ]);
});
