<?php

namespace Database\Factories\IAM;

use App\Modules\IAM\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Synthetic custom roles; the two built-in launch roles are TASK-011's catalogue.
 *
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'PERAN_CONTOH_'.++self::$sequence];
    }
}
