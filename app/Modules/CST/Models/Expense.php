<?php

namespace App\Modules\CST\Models;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PUR\Models\PurchaseCharge;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\CST\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's or a project's expense (DATABASE §4.8 `expenses`, AF): a fee only from its settlement's
 * posting (BR-FIN-09; C-23), a charge or a configured tax at most its evidence (L-43; BR-FIN-16). The
 * deduction settlement is FIN's (TASK-009), related by its own model.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $project_id
 * @property string $kind
 * @property string $category
 * @property string $amount
 * @property CarbonImmutable $business_date
 * @property int|null $deduction_settlement_id
 * @property int|null $evidence_document_id
 * @property string|null $evidence_type
 * @property int|null $purchase_charge_id
 * @property int|null $purchase_line_id
 * @property int|null $source_bill_evidence_id
 * @property string|null $source_bill_evidence_type
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(ExpenseFactory::class)]
class Expense extends Model
{
    use AppendOnly;

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<PurchaseCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(PurchaseCharge::class, 'purchase_charge_id');
    }

    /** @return BelongsTo<PurchaseLine, $this> */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'evidence_document_id');
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function sourceBill(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'source_bill_evidence_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'project_id' => 'integer',
            'amount' => 'decimal:2',
            'business_date' => 'immutable_date',
            'deduction_settlement_id' => 'integer',
            'evidence_document_id' => 'integer',
            'purchase_charge_id' => 'integer',
            'purchase_line_id' => 'integer',
            'source_bill_evidence_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
