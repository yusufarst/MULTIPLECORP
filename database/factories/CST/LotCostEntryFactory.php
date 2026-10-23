<?php

namespace Database\Factories\CST;

use App\Modules\CST\Models\LotCostEntry;
use App\Modules\ORG\Models\Company;
use Database\Factories\INV\InventoryLotFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The invented opening cost basis of an opening lot of the entry's source company (§4.8; C-28).
 *
 * @extends Factory<LotCostEntry>
 */
final class LotCostEntryFactory extends Factory
{
    protected $model = LotCostEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'source_company_id' => Company::factory(),
            'lot_id' => static fn (array $row): int => InventoryLotFactory::new()->createOne(['source_company_id' => $row['source_company_id']])->id,
            'entry_kind' => 'OPENING_COST',
            'cost_component' => 'BASIS',
            'amount' => '500000.00',
            'business_date' => '2026-10-01',
        ];
    }
}
