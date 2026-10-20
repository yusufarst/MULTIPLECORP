<?php

namespace Database\Factories\PTY;

use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientUnit>
 */
final class ClientUnitFactory extends Factory
{
    protected $model = ClientUnit::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_organization_id' => ClientOrganization::factory(),
            'name' => 'Bagian Contoh '.++self::$sequence,
        ];
    }
}
