<?php

namespace Database\Factories\IAM;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserCompanyGrant;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active grant of an invented company.
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
            'company_id' => Company::factory(),
            'granted_at' => now(),
        ];
    }
}
