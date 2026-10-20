<?php

namespace Database\Factories\CAT;

use App\Modules\CAT\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
final class UnitFactory extends Factory
{
    protected $model = Unit::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'code' => "SAT{$number}",
            'name' => "Satuan Contoh {$number}",
        ];
    }
}
