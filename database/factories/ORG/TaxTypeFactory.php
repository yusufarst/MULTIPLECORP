<?php

namespace Database\Factories\ORG;

use App\Modules\ORG\Models\TaxType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented output tax kind; no rate or treatment is implied.
 *
 * @extends Factory<TaxType>
 */
final class TaxTypeFactory extends Factory
{
    protected $model = TaxType::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'code' => "PAJAK-CONTOH-{$number}",
            'name' => "Pajak Contoh {$number}",
            'category' => 'OUTPUT_TAX',
        ];
    }
}
