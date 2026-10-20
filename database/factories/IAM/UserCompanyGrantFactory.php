<?php

namespace Database\Factories\IAM;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCompanyGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Until ORG's companies exist (TASK-006), the company is a synthetic key.
 *
 * @extends Factory<UserCompanyGrant>
 */
final class UserCompanyGrantFactory extends Factory
{
    protected $model = UserCompanyGrant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => 1001,
            'granted_at' => now(),
        ];
    }
}
