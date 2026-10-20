<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\SecurityEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityEvent>
 */
final class SecurityEventFactory extends Factory
{
    protected $model = SecurityEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'occurred_at' => now(),
            'event_kind' => 'LOGIN_SUCCEEDED',
        ];
    }
}
