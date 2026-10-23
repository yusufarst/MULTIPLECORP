<?php

namespace Database\Factories\PTY;

use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented person in charge, on a reserved example domain.
 *
 * @extends Factory<ClientContact>
 */
final class ClientContactFactory extends Factory
{
    protected $model = ClientContact::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'client_unit_id' => ClientUnit::factory(),
            'name' => "Contoh Lestari {$number}",
            'position' => 'Bendahara',
            'email' => "pic{$number}@example.org",
        ];
    }
}
