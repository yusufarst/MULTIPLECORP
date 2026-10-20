<?php

namespace App\Modules\OPS\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OPS\ImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One source row of an import batch (DATABASE §4.13 `import_rows`, SR, frozen once COMMITTED; §24) with
 * its stable legacy identity, the only link to legacy keys (§16).
 *
 * @property int $id
 * @property int $import_batch_id
 * @property int $row_number
 * @property string $import_kind
 * @property string $import_identity
 * @property int|null $company_id
 * @property array<string, mixed>|null $normalized_payload
 * @property string $validation_state
 * @property array<string, mixed>|null $errors
 * @property string|null $result_entity_type
 * @property int|null $result_entity_id
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ImportRowFactory::class)]
class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    /** @return BelongsTo<ImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'company_id' => 'integer',
            'normalized_payload' => 'array',
            'errors' => 'array',
            'result_entity_id' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
