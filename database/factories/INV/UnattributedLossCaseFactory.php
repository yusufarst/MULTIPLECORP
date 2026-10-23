<?php

namespace Database\Factories\INV;

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\Location;
use App\Modules\INV\Models\UnattributedLossCase;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * An invented pending loss of one piece in a usable scope, recognized against an exposure of two by a pool
 * movement, founding its own overlap group.
 *
 * @extends Factory<UnattributedLossCase>
 */
final class UnattributedLossCaseFactory extends Factory
{
    protected $model = UnattributedLossCase::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'case_kind' => 'LOSS',
            'product_id' => Product::factory(),
            'quantity_scale' => static fn (array $row): int => ProductFactory::keyOf($row['product_id'])['quantity_scale'],
            'location_id' => Location::factory(),
            'condition' => 'USABLE',
            'recognized_qty_base' => '1.000',
            'exposure_qty_base' => '2.000',
            'recognition_movement_id' => static fn (): int => StockMovementFactory::post('UNATTRIBUTED_RECOGNITION'),
            'business_date' => '2026-10-01',
            'recognized_by' => User::factory(),
            'reason' => 'Selisih opname tanpa bukti perusahaan',
        ];
    }

    /** A candidate company's exposure to the case, written as the recognition writes it (§5.8). */
    public static function expose(mixed $case, int $company, string $quantity = '2.000'): int
    {
        DB::table('unattributed_loss_exposures')->insertOrIgnore([
            'unattributed_loss_case_id' => UnattributedLossCase::query()->whereKey($case)->firstOrFail()->id, 'company_id' => $company, 'exposure_qty_base' => $quantity,
        ]);

        return $company;
    }
}
