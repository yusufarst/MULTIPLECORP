<?php

namespace App\Modules\OPS\Models;

use App\Modules\IAM\Models\User;
use App\Support\Database\IntegerList;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An opening-data import batch (DATABASE §4.13 `import_batches`, SR; §24; WF-MIG-01): one source file per
 * import kind, signed off by the Admin and the Owner and committed by the Owner (L-33). The source file's
 * relation arrives with DOC's model (TASK-006).
 *
 * @property int $id
 * @property string $public_id
 * @property string $import_kind
 * @property int $source_file_id
 * @property string $source_sha256
 * @property CarbonImmutable $cutoff_date
 * @property list<int> $company_scope
 * @property string $state
 * @property array<string, mixed>|null $control_totals
 * @property array<string, mixed>|null $dry_run_report
 * @property int|null $prepared_by
 * @property int|null $admin_signoff_by
 * @property CarbonImmutable|null $admin_signoff_at
 * @property int|null $owner_signoff_by
 * @property CarbonImmutable|null $owner_signoff_at
 * @property int|null $committed_by
 * @property CarbonImmutable|null $committed_at
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ImportBatchFactory::class)]
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return HasMany<ImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function committedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_file_id' => 'integer',
            'cutoff_date' => 'immutable_date',
            'company_scope' => IntegerList::class,
            'control_totals' => 'array',
            'dry_run_report' => 'array',
            'admin_signoff_at' => 'immutable_datetime',
            'owner_signoff_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
