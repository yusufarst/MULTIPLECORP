<?php

namespace Database\Factories\PTY;

use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented street of an invented unit.
 *
 * @extends Factory<ClientAddress>
 */
final class ClientAddressFactory extends Factory
{
    protected $model = ClientAddress::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_unit_id' => ClientUnit::factory(),
            'label' => 'Kantor',
            'address_lines' => 'Jl. Contoh Pendidikan No. '.++self::$sequence,
            'city' => 'Yogyakarta',
        ];
    }
}
