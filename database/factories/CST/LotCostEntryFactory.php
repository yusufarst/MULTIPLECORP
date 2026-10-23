<?php

namespace Database\Factories\CST;

use App\Modules\CST\Models\LotCostEntry;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The invented opening cost basis of a lot. Until INV's lots exist (TASK-008), the lot is a synthetic
 * key, as every record of a later module is.
 *
 * @extends Factory<LotCostEntry>
 */
final class LotCostEntryFactory extends Factory
{
    protected $model = LotCostEntry::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lot_id' => 7000 + ++self::$sequence,
            'source_company_id' => Company::factory(),
            'entry_kind' => 'OPENING_COST',
            'cost_component' => 'BASIS',
            'amount' => '500000.00',
            'business_date' => '2026-10-01',
        ];
    }
}
