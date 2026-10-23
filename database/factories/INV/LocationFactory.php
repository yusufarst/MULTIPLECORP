<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented rack of the warehouse.
 *
 * @extends Factory<Location>
 */
final class LocationFactory extends Factory
{
    protected $model = Location::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'code' => "RAK-CTH-{$number}",
            'name' => "Rak contoh {$number}",
        ];
    }
}
