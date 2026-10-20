<?php

namespace App\Modules\OPS\Models;

use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\LegacyArchiveItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An identified legacy archive item (DATABASE §4.13 `legacy_archive_items`, AF; §24), never a current
 * effect (BR-XD-06) and never changed (C-53). The company and file relations arrive with ORG's and DOC's
 * models (TASK-006).
 *
 * @property int $id
 * @property int $company_id
 * @property string $archive_kind
 * @property string $legacy_reference
 * @property string|null $description
 * @property int|null $file_object_id
 * @property int|null $import_batch_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(LegacyArchiveItemFactory::class)]
class LegacyArchiveItem extends Model
{
    use AppendOnly;

    /** @use HasFactory<LegacyArchiveItemFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<FileObject, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'file_object_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
