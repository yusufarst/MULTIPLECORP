<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\UnattributedLossCandidate;
use App\Modules\INV\Models\UnattributedLossCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented candidate of a case: a lot of its product holding two pieces in its scope, of the lot's source
 * company.
 *
 * @extends Factory<UnattributedLossCandidate>
 */
final class UnattributedLossCandidateFactory extends Factory
{
    protected $model = UnattributedLossCandidate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unattributed_loss_case_id' => UnattributedLossCase::factory(),
            'lot_id' => static fn (array $row): int => InventoryLotFactory::new()->createOne([
                'product_id' => UnattributedLossCase::query()->whereKey($row['unattributed_loss_case_id'])->firstOrFail()->product_id,
            ])->id,
            'candidate_company_id' => static fn (array $row): int => InventoryLotFactory::keyOf($row['lot_id'])['source_company_id'],
            'qty_in_scope_at_recognition' => '2.000',
            'net_unit_cost_at_recognition' => '10000.00',
        ];
    }
}
