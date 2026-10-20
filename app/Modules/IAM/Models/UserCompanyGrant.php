<?php

namespace App\Modules\IAM\Models;

use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\IAM\UserCompanyGrantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account's access to one company (DATABASE §4.1 `user_company_grants`, SR): default deny, so no
 * active row means no access (BR-ACC-01). The company relation arrives with ORG's model (TASK-006).
 *
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property int|null $granted_by
 * @property CarbonImmutable $granted_at
 * @property int|null $revoked_by
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $command_id
 * @property int|null $command_line_no
 * @property CarbonImmutable $recorded_at
 */
#[UseFactory(UserCompanyGrantFactory::class)]
class UserCompanyGrant extends Model
{
    /** @use HasFactory<UserCompanyGrantFactory> */
    use HasFactory;

    public const CREATED_AT = 'recorded_at';

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'granted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'command_line_no' => 'integer',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
