<?php

namespace Database\Factories\IAM;

use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Support\Synthetic;

/**
 * Synthetic accounts (TEST_STRATEGY TS-04): invented names on the reserved example domains, never a
 * real person; the password is hashed by the model.
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;
        $synthetic = new Synthetic(Synthetic::SEED + $number);

        return [
            'name' => $synthetic->personName(),
            'email' => $synthetic->email("pengguna{$number}"),
            'password' => 'kata-sandi-contoh',
        ];
    }

    public function deactivated(): static
    {
        return $this->state(fn (): array => ['is_active' => false, 'deactivated_at' => now()]);
    }
}
