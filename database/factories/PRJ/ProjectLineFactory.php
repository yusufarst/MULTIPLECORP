<?php

namespace Database\Factories\PRJ;

use App\Modules\CAT\Models\Product;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Models\ProjectLine;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented unconfirmed warehouse line of ten pieces in the product's base unit, of its project's company
 * and with its product's scale (§1; C-09, C-32).
 *
 * @extends Factory<ProjectLine>
 */
final class ProjectLineFactory extends Factory
{
    protected $model = ProjectLine::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'project_id' => Project::factory(),
            'company_id' => static fn (array $row): int => ProjectFactory::companyOf($row['project_id']),
            'line_no' => $number,
            'kind' => 'GOODS',
            'product_id' => Product::factory(),
            'quantity_scale' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['quantity_scale'],
            'description' => "Barang contoh baris {$number}",
            'entered_qty' => '10.000',
            'entered_unit_id' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['base_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '10.000',
            'unit_price' => '150000.00',
            'fulfillment_mode' => 'WAREHOUSE',
        ];
    }

    /**
     * The key a pin, a quotation line or a cancellation cites this line through (§1; C-09, C-32), with its unit.
     *
     * @return array{company_id: int, product_id: int, quantity_scale: int, entered_unit_id: int}
     */
    public static function keyOf(mixed $line): array
    {
        $row = ProjectLine::query()->whereKey($line)->firstOrFail(['company_id', 'product_id', 'quantity_scale', 'entered_unit_id']);

        return ['company_id' => $row->company_id, 'product_id' => $row->product_id, 'quantity_scale' => $row->quantity_scale, 'entered_unit_id' => $row->entered_unit_id];
    }

    /** A new line of the project of an invented row's revision or confirmation. */
    public static function newOn(int $project): int
    {
        return self::new()->createOne(['project_id' => $project])->id;
    }
}
