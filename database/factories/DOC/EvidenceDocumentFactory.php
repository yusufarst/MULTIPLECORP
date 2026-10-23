<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented client order of a company; the identity key only illustrates L-45, its normalization
 * being TASK-030's.
 *
 * @extends Factory<EvidenceDocument>
 */
final class EvidenceDocumentFactory extends Factory
{
    protected $model = EvidenceDocument::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'evidence_scope' => 'COMPANY',
            'company_id' => Company::factory(),
            'evidence_type' => 'CLIENT_ORDER',
            'declared_issuer' => 'SMA Contoh Makmur',
            'issuer_reference' => "SP/CTH/{$number}",
            'document_date' => '2026-10-01',
            'identity_key' => "sma contoh makmur|CLIENT_ORDER|sp/cth/{$number}|2026-10-01",
        ];
    }

    /** A bank statement, the evidence its lines cite. */
    public function bankStatement(): self
    {
        $number = ++self::$sequence;

        return $this->state([
            'evidence_type' => 'BANK_STATEMENT',
            'declared_issuer' => 'Bank Contoh',
            'issuer_reference' => "RK/CTH/{$number}",
            'identity_key' => "bank contoh|BANK_STATEMENT|rk/cth/{$number}|2026-10-01",
        ]);
    }

    /** A supplier's bill, the source bill of charges and expenses (L-43). */
    public function supplierBill(): self
    {
        $number = ++self::$sequence;

        return $this->state([
            'evidence_type' => 'SUPPLIER_BILL',
            'declared_issuer' => 'Pemasok Contoh',
            'issuer_reference' => "INV/PMS/{$number}",
            'identity_key' => "pemasok contoh|SUPPLIER_BILL|inv/pms/{$number}|2026-10-01",
        ]);
    }

    /** Pool evidence of shared stock, which belongs to no company (DIR-027). */
    public function pool(): self
    {
        $number = ++self::$sequence;

        return $this->state([
            'evidence_scope' => 'POOL',
            'company_id' => null,
            'evidence_type' => 'WAREHOUSE_RECORD',
            'declared_issuer' => 'Gudang bersama',
            'issuer_reference' => null,
            'identity_key' => "gudang bersama|WAREHOUSE_RECORD|{$number}|2026-10-01",
        ]);
    }
}
