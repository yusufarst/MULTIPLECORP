<?php

namespace Database\Factories\IAM;

use App\Modules\IAM\Models\Capability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Synthetic capabilities; the catalogue of PERMISSIONS_MATRIX is TASK-011's.
 *
 * @extends Factory<Capability>
 */
final class CapabilityFactory extends Factory
{
    protected $model = Capability::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'contoh.kemampuan_'.++self::$sequence,
            'authority_class' => 'ADM',
        ];
    }
}
