<?php

namespace App\Modules\DOC\Data;

use App\Modules\IAM\Data\Authority;

/**
 * The evidence types of DATABASE §4.10 and who may see and handle each (PJ-08; TM-55): a company's evidence by
 * `evidence.view`, its company in scope and its family's view capability; pool evidence — WAREHOUSE_RECORD and OTHER of
 * no company — by `stock.view` with `evidence.view`; OPENING_SIGNOFF by the import rule (PJ-20), registered by the
 * import (TASK-057), never chosen on PG-038. Uploading, versioning or linking needs `evidence.upload` and the same
 * family. The amount-bearing types carry a proven amount that caps every citation (C-18), and each citing role accepts
 * only its types (C-58; DATABASE §4.10 "Type is bound to role"), so a document registered again under another type
 * gains no capacity in any role.
 */
final class EvidenceTypes
{
    public const TYPES = [
        'CLIENT_SPK', 'CLIENT_ORDER', 'SIPLAH_ORDER', 'THIRD_PARTY_HPS', 'TAX_INVOICE', 'NPWP', 'NIB', 'BANK_STATEMENT',
        'TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF', 'TAX_PAYMENT_PROOF', 'FEE_ADVICE', 'SUPPLIER_BILL', 'DELIVERY_PROOF',
        'PAYMENT_PROOF', 'TRANSFER_PROOF', 'WAREHOUSE_RECORD', 'OPENING_SIGNOFF', 'LEGACY_DOCUMENT', 'OTHER',
    ];

    /** The types pool evidence may have through PG-038 (PJ-08; H7-11); a pool OPENING_SIGNOFF is the import's. */
    public const POOL_TYPES = ['WAREHOUSE_RECORD', 'OTHER'];

    /** The types whose proven amount a citing role caps (C-18). */
    public const AMOUNT_BEARING = ['FEE_ADVICE', 'TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF', 'TAX_PAYMENT_PROOF', 'SUPPLIER_BILL'];

    /** C-58: each amount-bearing citing role and the types it accepts, as the citing tables' CHECKs state them. */
    public const CITING_ROLES = [
        'FEE_SETTLEMENT' => ['FEE_ADVICE'],
        'TAX_SETTLEMENT_WITHHELD' => ['TAX_WITHHOLDING_PROOF'],
        'TAX_SETTLEMENT_COLLECTED' => ['TAX_COLLECTION_PROOF'],
        'FEE_DEDUCTION_EXPENSE' => ['FEE_ADVICE'],
        'TAX_TREATMENT_EXPENSE' => ['TAX_WITHHOLDING_PROOF', 'TAX_COLLECTION_PROOF'],
        'CHARGE_SOURCE_BILL' => ['SUPPLIER_BILL'],
        'TAX_REMITTANCE' => ['TAX_PAYMENT_PROOF'],
    ];

    public static function isAmountBearing(string $type): bool
    {
        return in_array($type, self::AMOUNT_BEARING, true);
    }

    /** PJ-08: whether the viewer sees evidence of the type in the company — `null` for pool evidence. */
    public static function mayView(Authority $viewer, string $type, ?int $companyId): bool
    {
        if (! $viewer->active) {
            return false;
        }

        if ($type === 'OPENING_SIGNOFF') {
            // PJ-20: the Owner, or `import.prepare` with the company in scope — a pool sign-off with every company.
            return $viewer->ownerRole || ($viewer->has('import.prepare') && ($companyId === null ? $viewer->scope->all : $viewer->scope->includes($companyId)));
        }

        if ($companyId === null) {
            return in_array($type, self::POOL_TYPES, true) && $viewer->has('evidence.view') && $viewer->has('stock.view');
        }

        return $viewer->allows('evidence.view', $companyId) && self::family($viewer, $type, $companyId);
    }

    /** PJ-08: whether the actor may upload, version or link evidence of the type in the company — `null` for the pool. */
    public static function mayHandle(Authority $actor, string $type, ?int $companyId): bool
    {
        if ($type === 'OPENING_SIGNOFF' || ! $actor->active) {
            return false;
        }

        if ($companyId === null) {
            return in_array($type, self::POOL_TYPES, true) && $actor->has('evidence.upload') && $actor->has('evidence.view') && $actor->has('stock.view');
        }

        return $actor->allows('evidence.upload', $companyId) && $actor->allows('evidence.view', $companyId) && self::family($actor, $type, $companyId);
    }

    /**
     * The types the actor may choose in the company, or for the pool (IP-16 step 3; TM-55).
     *
     * @return list<string>
     */
    public static function choosable(Authority $actor, ?int $companyId): array
    {
        return array_values(array_filter(self::TYPES, fn (string $type): bool => self::mayHandle($actor, $type, $companyId)));
    }

    private static function family(Authority $account, string $type, int $companyId): bool
    {
        foreach (FileFamilies::evidence($type) as $capability) {
            if ($account->allows($capability, $companyId)) {
                return true;
            }
        }

        return false;
    }
}
