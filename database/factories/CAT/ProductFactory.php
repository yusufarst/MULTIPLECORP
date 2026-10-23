<?php

namespace Database\Factories\CAT;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Counted goods at scale 0, the commonest product.
 *
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'kind' => 'GOODS',
            'sku' => "BRG-CTH-{$number}",
            'name' => "Barang Contoh {$number}",
            'base_unit_id' => Unit::factory(),
            'quantity_scale' => 0,
        ];
    }

    /**
     * The scale and base unit an invented quantity-bearing row takes from its product (DATABASE §1, §7; C-09).
     *
     * @return array{quantity_scale: int, base_unit_id: int}
     */
    public static function keyOf(mixed $product): array
    {
        $row = Product::query()->whereKey($product)->firstOrFail(['quantity_scale', 'base_unit_id']);

        return ['quantity_scale' => $row->quantity_scale, 'base_unit_id' => $row->base_unit_id];
    }
}
