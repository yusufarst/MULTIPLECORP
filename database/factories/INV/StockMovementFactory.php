<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented pool-wide location move, a header with no entries yet.
 *
 * @extends Factory<StockMovement>
 */
final class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'movement_type' => 'LOCATION_MOVE',
            'business_date' => '2026-10-01',
        ];
    }

    /** A movement of the type, of the company's command or of the pool's (§4.7), its id. */
    public static function post(string $type, mixed $company = null, mixed $project = null): int
    {
        return self::new()->createOne(['movement_type' => $type, 'company_id' => $company, 'project_id' => $project])->id;
    }
}
