<?php

namespace App\Modules\FIN\Models;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientOrganization;
use App\Support\Database\AppendOnly;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\OpeningCustomerCreditFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's credit carried over at cutover, an application source and never Cash-In (DATABASE §4.12
 * `opening_customer_credits`, AF; BR-XD-06; L-50).
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_organization_id
 * @property string $amount
 * @property int $import_row_id
 * @property int $signed_evidence_id
 * @property CarbonImmutable $business_date
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 */
#[UseFactory(OpeningCustomerCreditFactory::class)]
class OpeningCustomerCredit extends Model
{
    use AppendOnly;

    /** @use HasFactory<OpeningCustomerCreditFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<ClientOrganization, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientOrganization::class, 'client_organization_id');
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function signedEvidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'client_organization_id' => 'integer',
            'amount' => 'decimal:2',
            'import_row_id' => 'integer',
            'signed_evidence_id' => 'integer',
            'business_date' => 'immutable_date',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
        ];
    }
}
