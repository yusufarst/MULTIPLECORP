<?php

namespace Database\Factories\PTY;

use App\Modules\PTY\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented supplier.
 *
 * @extends Factory<Supplier>
 */
final class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'CV Pemasok Contoh '.++self::$sequence,
            'city' => 'Sleman',
        ];
    }
}
