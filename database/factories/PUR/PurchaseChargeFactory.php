<?php

namespace Database\Factories\PUR;

use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseCharge;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented attributable freight charge spread by line value, its source bill a supplier's bill of the
 * purchase's company (L-43; C-32, C-58).
 *
 * @extends Factory<PurchaseCharge>
 */
final class PurchaseChargeFactory extends Factory
{
    protected $model = PurchaseCharge::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'company_id' => static fn (array $row): int => PurchaseFactory::companyOf($row['purchase_id']),
            'source_bill_evidence_id' => static fn (array $row): int => EvidenceDocumentFactory::new()->supplierBill()->createOne(['company_id' => $row['company_id']])->id,
            'source_bill_evidence_type' => 'SUPPLIER_BILL',
            'classification' => 'ATTRIBUTABLE',
            'amount' => '50000.00',
            'allocation_basis' => 'LINE_VALUE',
        ];
    }
}
