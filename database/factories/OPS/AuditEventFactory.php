<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
final class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'occurred_at' => now(),
            'action' => 'contoh.tindakan',
            'authority_class' => 'SYSTEM',
        ];
    }
}
