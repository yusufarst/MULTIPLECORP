<?php

namespace App\Modules\INV\Models;

use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\UnattributedLossResolutionLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The economic assignment of a resolution to a candidate company (DATABASE §4.7
 * `unattributed_loss_resolution_lines`, AD; C-37), within its exposure.
 *
 * @property int $id
 * @property int $unattributed_loss_resolution_id
 * @property int $unattributed_loss_case_id
 * @property int $product_id
 * @property int $quantity_scale
 * @property int $assigned_company_id
 * @property string $assigned_qty_base
 * @property int|null $bearer_project_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(UnattributedLossResolutionLineFactory::class)]
class UnattributedLossResolutionLine extends Model
{
    use AppendOnly;

    /** @use HasFactory<UnattributedLossResolutionLineFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<UnattributedLossResolution, $this> */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossResolution::class, 'unattributed_loss_resolution_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function assignedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function bearerProject(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unattributed_loss_resolution_id' => 'integer',
            'unattributed_loss_case_id' => 'integer',
            'product_id' => 'integer',
            'quantity_scale' => 'integer',
            'assigned_company_id' => 'integer',
            'assigned_qty_base' => 'decimal:3',
            'bearer_project_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
