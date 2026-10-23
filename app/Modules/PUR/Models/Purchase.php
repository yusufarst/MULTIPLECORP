<?php

namespace App\Modules\PUR\Models;

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PTY\Models\Supplier;
use Carbon\CarbonImmutable;
use Database\Factories\PUR\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase of one company (DATABASE §4.6 `purchases`, SR; BR-PUR-01–04): for a project of the same
 * company or for replenishment (L-05), its number drawn at its save and never reused (DIR-077 CA-09;
 * C-10), never deleted — a cancellation keeps the row.
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $purchase_number
 * @property string $period_key
 * @property int $sequence_no
 * @property int $supplier_id
 * @property array<string, mixed> $supplier_snapshot
 * @property int|null $project_id
 * @property CarbonImmutable $business_date
 * @property string|null $supplier_reference
 * @property string $state
 * @property int|null $cancelled_by
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_reason
 * @property string $origin
 * @property int|null $import_row_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(PurchaseFactory::class)]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

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

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<PurchaseLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /** @return HasMany<PurchaseCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(PurchaseCharge::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sequence_no' => 'integer',
            'supplier_id' => 'integer',
            'supplier_snapshot' => 'array',
            'project_id' => 'integer',
            'business_date' => 'immutable_date',
            'cancelled_by' => 'integer',
            'cancelled_at' => 'immutable_datetime',
            'import_row_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
