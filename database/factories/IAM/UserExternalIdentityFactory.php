<?php

namespace Database\Factories\IAM;

use App\Modules\IAM\Models\User;
use App\Modules\IAM\Models\UserExternalIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Synthetic Google links, created by their holder as in V1 (C-63): the subject is an invented numeric
 * identifier, the address on a reserved example domain.
 *
 * @extends Factory<UserExternalIdentity>
 */
final class UserExternalIdentityFactory extends Factory
{
    protected $model = UserExternalIdentity::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'user_id' => User::factory(),
            'provider' => 'google',
            'provider_subject' => '10'.str_pad((string) $number, 19, '0', STR_PAD_LEFT),
            'email_at_link' => "tautan{$number}@example.com",
            'linked_at' => now(),
        ];
    }

    /** The link's creator is its holder unless a test names another (C-63). */
    public function configure(): static
    {
        return $this->afterMaking(function (UserExternalIdentity $identity): void {
            if ($identity->getAttribute('linked_by') === null) {
                $identity->linked_by = $identity->user_id;
            }
        });
    }
}
