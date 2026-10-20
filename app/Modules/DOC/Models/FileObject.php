<?php

namespace App\Modules\DOC\Models;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The metadata of a private file whose bytes never change (DATABASE §4.10 `file_objects`, AF; §22): a
 * replacement is a new object, and access is always decided through the owning record (P5).
 *
 * @property int $id
 * @property string $public_id
 * @property string $storage_disk
 * @property string $storage_key
 * @property string|null $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int|null $company_id
 * @property string $sensitivity
 * @property string $purpose
 * @property int|null $uploaded_by
 * @property string|null $scan_state
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(FileObjectFactory::class)]
class FileObject extends Model
{
    use AppendOnly;

    /** @use HasFactory<FileObjectFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'company_id' => 'integer',
            'uploaded_by' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
