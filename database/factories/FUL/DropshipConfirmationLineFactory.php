<?php

namespace Database\Factories\FUL;

use App\Modules\CAT\Models\Product;
use App\Modules\FUL\Models\DropshipConfirmation;
use App\Modules\FUL\Models\DropshipConfirmationLine;
use Database\Factories\CAT\ProductFactory;
use Database\Factories\PRJ\ProjectLineFactory;
use Database\Factories\PUR\PurchaseLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented confirmed line of two pieces: a drop-ship purchase line of the confirmation's purchase for a
 * drop-ship demand line of its project, of one product.
 *
 * @extends Factory<DropshipConfirmationLine>
 */
final class DropshipConfirmationLineFactory extends Factory
{
    protected $model = DropshipConfirmationLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'dropship_confirmation_id' => DropshipConfirmation::factory(),
            'company_id' => static fn (array $row): int => DropshipConfirmationFactory::partsOf($row['dropship_confirmation_id'])['company_id'],
            'product_id' => Product::factory(),
            'quantity_scale' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['quantity_scale'],
            'project_line_id' => static fn (array $row): int => ProjectLineFactory::new()->createOne([
                'project_id' => DropshipConfirmationFactory::partsOf($row['dropship_confirmation_id'])['project_id'], 'product_id' => $row['product_id'], 'fulfillment_mode' => 'DROPSHIP',
            ])->id,
            'purchase_line_id' => static fn (array $row): int => PurchaseLineFactory::new()->createOne([
                'purchase_id' => DropshipConfirmationFactory::partsOf($row['dropship_confirmation_id'])['purchase_id'], 'product_id' => $row['product_id'],
                'delivery_mode' => 'DROPSHIP', 'project_line_id' => $row['project_line_id'],
            ])->id,
            'entered_qty' => '2.000',
            'entered_unit_id' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['base_unit_id'],
            'ratio_to_base' => 1,
            'qty_base' => '2.000',
        ];
    }
}
