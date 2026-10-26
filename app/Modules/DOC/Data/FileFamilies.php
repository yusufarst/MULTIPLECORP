<?php

namespace App\Modules\DOC\Data;

/**
 * The view capabilities of PERMISSIONS_MATRIX's families for files: a document's payload and rendition by its
 * type's family (PJ-15), an evidence document by its type's family (PJ-08). A type outside the lists opens to
 * nobody.
 */
final class FileFamilies
{
    private const DOCUMENT_FINANCE = ['DOC-01', 'DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-09', 'DOC-11', 'DOC-13'];

    private const DOCUMENT_COST = ['DOC-02', 'DOC-14'];

    private const DOCUMENT_OPERATIONAL = ['DOC-03', 'DOC-08', 'DOC-10', 'DOC-12'];

    private const EVIDENCE = [
        'finance.view' => ['BANK_STATEMENT', 'PAYMENT_PROOF', 'TRANSFER_PROOF', 'FEE_ADVICE', 'TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF', 'TAX_PAYMENT_PROOF', 'LEGACY_DOCUMENT', 'TAX_INVOICE'],
        'cost.view' => ['SUPPLIER_BILL', 'TAX_INVOICE'],
        'projects.view' => ['CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER', 'THIRD_PARTY_HPS', 'DELIVERY_PROOF', 'NPWP', 'NIB', 'OTHER'],
        'stock.view' => ['WAREHOUSE_RECORD'],
    ];

    /** PJ-15: the one capability that opens a document type's payload and rendition. */
    public static function document(string $typeCode): ?string
    {
        return match (true) {
            in_array($typeCode, self::DOCUMENT_FINANCE, true) => 'finance.view',
            in_array($typeCode, self::DOCUMENT_COST, true) => 'cost.view',
            in_array($typeCode, self::DOCUMENT_OPERATIONAL, true) => 'projects.view',
            default => null,
        };
    }

    /**
     * PJ-08: the capabilities any one of which opens an evidence type's family, beside `evidence.view`;
     * OPENING_SIGNOFF follows the import rule instead (PJ-20).
     *
     * @return list<string>
     */
    public static function evidence(string $type): array
    {
        $capabilities = [];

        foreach (self::EVIDENCE as $capability => $types) {
            if (in_array($type, $types, true)) {
                $capabilities[] = $capability;
            }
        }

        return $capabilities;
    }
}
