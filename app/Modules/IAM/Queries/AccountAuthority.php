<?php

namespace App\Modules\IAM\Queries;

use App\Modules\IAM\Data\Authority;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * IAM's read of an account's authority for the modules that only read (ARCHITECTURE §3; AZ-03): the memoized authority
 * of a request or job, and the authority read again — RV-07's check before a job's snapshot and RV-08's at delivery.
 */
final class AccountAuthority
{
    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    public function for(Authenticatable|int $user): Authority
    {
        return $this->capabilities->for($user);
    }

    public function fresh(int $userId): Authority
    {
        return $this->capabilities->fresh($userId);
    }
}
