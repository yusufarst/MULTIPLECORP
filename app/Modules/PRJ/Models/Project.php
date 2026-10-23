<?php

namespace App\Modules\PRJ\Models;

use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use Carbon\CarbonImmutable;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A project of one company (DATABASE §4.5 `projects`, SR; §9): its number drawn once and never reused
 * (C-10), its client a live reference snapshotted on outputs (§14), its state changed only with its
 * transition row (SM:Project).
 *
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property string $project_number
 * @property string $period_key
 * @property int $sequence_no
 * @property int $client_organization_id
 * @property int|null $client_unit_id
 * @property int|null $client_contact_id
 * @property int|null $client_address_id
 * @property string $channel
 * @property string $state
 * @property string $title
 * @property CarbonImmutable|null $deadline
 * @property string $origin
 * @property int|null $import_row_id
 * @property int|null $linked_project_id
 * @property string|null $link_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(ProjectFactory::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
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

    /** @return BelongsTo<ClientOrganization, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientOrganization::class, 'client_organization_id');
    }

    /** @return BelongsTo<ClientUnit, $this> */
    public function clientUnit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class);
    }

    /** @return BelongsTo<ClientContact, $this> */
    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class);
    }

    /** @return BelongsTo<ClientAddress, $this> */
    public function clientAddress(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function linkedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    /** @return HasOne<ProjectSiplahDetail, $this> */
    public function siplahDetail(): HasOne
    {
        return $this->hasOne(ProjectSiplahDetail::class);
    }

    /** @return HasMany<ProjectLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProjectLine::class);
    }

    /** @return HasMany<QuotationRevision, $this> */
    public function quotationRevisions(): HasMany
    {
        return $this->hasMany(QuotationRevision::class);
    }

    /** @return HasMany<ProjectStateTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(ProjectStateTransition::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sequence_no' => 'integer',
            'client_organization_id' => 'integer',
            'client_unit_id' => 'integer',
            'client_contact_id' => 'integer',
            'client_address_id' => 'integer',
            'deadline' => 'immutable_date',
            'import_row_id' => 'integer',
            'linked_project_id' => 'integer',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
