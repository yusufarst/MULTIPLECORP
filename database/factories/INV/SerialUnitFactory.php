<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\SerialUnit;
use Database\Factories\CAT\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented serial of a serialized product, registered but never received.
 *
 * @extends Factory<SerialUnit>
 */
final class SerialUnitFactory extends Factory
{
    protected $model = SerialUnit::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'product_id' => ProductFactory::new()->state(['is_serialized' => true]),
            'serial_number' => "SN-CTH-{$number}",
            'serial_key' => "sncth{$number}",
            'state' => 'NEVER_RECEIVED',
        ];
    }
}
