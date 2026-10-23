<?php

namespace App\Modules\INV\Models;

use App\Modules\ORG\Models\Company;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\INV\UnattributedLossCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lot of the immutable recognition snapshot (DATABASE §4.7 `unattributed_loss_candidates`, SN; DIR-027
 * items 3 and 8), summing with its case's other candidates to the case's exposure (C-36).
 *
 * @property int $id
 * @property int $unattributed_loss_case_id
 * @property int $candidate_company_id
 * @property int $lot_id
 * @property string $qty_in_scope_at_recognition
 * @property string $net_unit_cost_at_recognition
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(UnattributedLossCandidateFactory::class)]
class UnattributedLossCandidate extends Model
{
    use AppendOnly;

    /** @use HasFactory<UnattributedLossCandidateFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<UnattributedLossCase, $this> */
    public function unattributedCase(): BelongsTo
    {
        return $this->belongsTo(UnattributedLossCase::class, 'unattributed_loss_case_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function candidateCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<InventoryLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unattributed_loss_case_id' => 'integer',
            'candidate_company_id' => 'integer',
            'lot_id' => 'integer',
            'qty_in_scope_at_recognition' => 'decimal:3',
            'net_unit_cost_at_recognition' => 'decimal:2',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
