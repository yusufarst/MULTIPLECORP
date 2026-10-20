<?php

namespace App\Modules\OPS\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OPS\CommandLogFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A command's identity and terminal outcome (DATABASE §4.13 `command_log`, OP; CONCURRENCY_IDEMPOTENCY
 * CI-01–CI-09), keyed by the client's `command_id`. Its actor and company are plain values: no relation
 * loads them through a key (CI-03).
 *
 * @property string $command_id
 * @property string $command_type
 * @property int|null $actor_user_id
 * @property int|null $company_id
 * @property string $payload_fingerprint
 * @property string $state
 * @property string|null $result_entity_type
 * @property int|null $result_entity_id
 * @property string|null $outcome_code
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(CommandLogFactory::class)]
class CommandLog extends Model
{
    /** @use HasFactory<CommandLogFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    protected $table = 'command_log';

    protected $primaryKey = 'command_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
            'company_id' => 'integer',
            'result_entity_id' => 'integer',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
