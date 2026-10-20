<?php

namespace Database\Factories\ORG;

use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented active company with its legal identity; no real name, tax number or address.
 *
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'code' => "CTH{$number}",
            'legal_name' => "CV Contoh Usaha {$number}",
            'legal_form' => 'CV',
            'npwp' => '00.000.000.0-000.000',
            'address_lines' => "Jl. Contoh Industri No. {$number}",
            'city' => 'Yogyakarta',
            'director_name' => 'Contoh Pramono',
        ];
    }
}
