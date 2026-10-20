<?php

namespace App\Modules\ORG\Models;

use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ORG\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company of the workspace (DATABASE §4.2 `companies`, MD; CAP-01): never deleted, deactivated
 * instead, collection continuing (BR-ACC-03); its identity assets are its own immutable files (§14).
 *
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property string $legal_name
 * @property string|null $legal_form
 * @property string|null $npwp
 * @property string|null $address_lines
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $director_name
 * @property int|null $logo_file_id
 * @property int|null $stamp_file_id
 * @property int|null $signature_file_id
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 * @property int|null $deactivated_by
 * @property string|null $deactivated_reason
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 * @property int|null $recorded_by
 * @property int $lock_version
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(CompanyFactory::class)]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use HasUuids;

    public const CREATED_AT = 'recorded_at';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return HasMany<CompanyBankAccount, $this> */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CompanyBankAccount::class);
    }

    /** @return HasMany<NumberingScheme, $this> */
    public function numberingSchemes(): HasMany
    {
        return $this->hasMany(NumberingScheme::class);
    }

    /** @return HasMany<CompanyTaxProfile, $this> */
    public function taxProfiles(): HasMany
    {
        return $this->hasMany(CompanyTaxProfile::class);
    }

    /** @return BelongsTo<FileObject, $this> */
    public function logoFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'logo_file_id');
    }

    /** @return BelongsTo<FileObject, $this> */
    public function stampFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'stamp_file_id');
    }

    /** @return BelongsTo<FileObject, $this> */
    public function signatureFile(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'signature_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'logo_file_id' => 'integer',
            'stamp_file_id' => 'integer',
            'signature_file_id' => 'integer',
            'is_active' => 'boolean',
            'deactivated_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
            'lock_version' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
