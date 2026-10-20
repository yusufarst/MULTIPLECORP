<?php

namespace Database\Factories\CAT;

use App\Modules\CAT\Models\Product;
use App\Modules\CAT\Models\ProductImage;
use App\Modules\DOC\Models\FileObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A product's image over an S0 file of no company.
 *
 * @extends Factory<ProductImage>
 */
final class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'file_object_id' => FileObject::factory()->productImage(),
        ];
    }
}
