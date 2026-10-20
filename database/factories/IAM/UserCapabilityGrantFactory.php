<?php

namespace Database\Factories\IAM;

use App\Modules\IAM\Models\Capability;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCapabilityGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCapabilityGrant>
 */
final class UserCapabilityGrantFactory extends Factory
{
    protected $model = UserCapabilityGrant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'capability_id' => Capability::factory(),
            'granted_at' => now(),
        ];
    }
}
