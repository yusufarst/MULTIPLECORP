<?php

namespace Database\Factories\CST;

use App\Modules\CST\Models\CostAttribution;
use Database\Factories\PUR\PurchaseLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented initial HPP of a non-stock purchase line's goods value, borne by the purchase's company — a
 * direct attribution, which states its component.
 *
 * @extends Factory<CostAttribution>
 */
final class CostAttributionFactory extends Factory
{
    protected $model = CostAttribution::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cost_class' => 'HPP',
            'cause' => 'INITIAL',
            'amount' => '1000000.00',
            'purchase_line_id' => PurchaseLineFactory::new()->state(['kind' => 'NONSTOCK']),
            'cost_component' => 'GOODS',
            'bearer_company_id' => static fn (array $row): int => PurchaseLineFactory::keyOf($row['purchase_line_id'])['company_id'],
            'business_date' => '2026-10-01',
        ];
    }
}
