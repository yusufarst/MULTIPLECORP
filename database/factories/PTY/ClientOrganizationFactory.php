<?php

namespace Database\Factories\PTY;

use App\Modules\PTY\Models\ClientOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented school, no real institution's name.
 *
 * @extends Factory<ClientOrganization>
 */
final class ClientOrganizationFactory extends Factory
{
    protected $model = ClientOrganization::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => 'SMA Contoh Makmur '.++self::$sequence];
    }
}
