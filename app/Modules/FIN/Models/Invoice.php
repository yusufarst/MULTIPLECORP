<?php

namespace App\Modules\FIN\Models;

use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PTY\Models\ClientOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\FIN\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The single sales-value record rendered as DOC-06 or DOC-04 (DATABASE §4.12 `invoices`, SR; L-26), its
 * state forward only, a void stating its typed basis (C-56), a replacement naming the invoice it replaces
 * (L-30).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int|null $project_id
 * @property int $client_organization_id
 * @property string $origin
 * @property int|null $import_row_id
 * @property string $aging_basis
 * @property string $state
 * @property int|null $current_version_id
 * @property int|null $replaces_invoice_id
 * @property int|null $voided_by
 * @property CarbonImmutable|null $voided_at
 * @property string|null $voided_reason
 * @property string|null $void_basis
 * @property int|null $void_correction_case_id
 * @property int|null $void_confirmation_id
 * @property int|null $void_cancellation_id
 * @property int|null $void_project_transition_id
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(InvoiceFactory::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
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

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    /** @return BelongsTo<InvoiceVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(InvoiceVersion::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'replaces_invoice_id');
    }

    /** @return HasMany<InvoiceVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(InvoiceVersion::class);
    }

    /** @return HasMany<InvoiceBillingAct, $this> */
    public function billingActs(): HasMany
    {
        return $this->hasMany(InvoiceBillingAct::class);
    }

    /** @return HasOne<InvoiceBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(InvoiceBalance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'project_id' => 'integer',
            'client_organization_id' => 'integer',
            'import_row_id' => 'integer',
            'current_version_id' => 'integer',
            'replaces_invoice_id' => 'integer',
            'voided_by' => 'integer',
            'voided_at' => 'immutable_datetime',
            'void_correction_case_id' => 'integer',
            'void_confirmation_id' => 'integer',
            'void_cancellation_id' => 'integer',
            'void_project_transition_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
