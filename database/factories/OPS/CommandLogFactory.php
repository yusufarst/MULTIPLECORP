<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\CommandLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A committed command with its 30-day expiry (CONCURRENCY_IDEMPOTENCY CI-07); the fingerprint is the
 * `v1:` form of CI-02 over an invented payload.
 *
 * @extends Factory<CommandLog>
 */
final class CommandLogFactory extends Factory
{
    protected $model = CommandLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $commandId = (string) Str::uuid7();

        return [
            'command_id' => $commandId,
            'command_type' => 'contoh.perintah',
            'payload_fingerprint' => 'v1:'.hash('sha256', $commandId),
            'state' => 'COMMITTED',
            'completed_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
