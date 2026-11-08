<?php

namespace App\Modules\IAM\Data;

/**
 * What an account may do at one moment (PERMISSIONS_MATRIX RG-02, RG-03, RG-06): its effective
 * capabilities and its company scope, read from the committed grants (AZ-03). An inactive account has
 * neither.
 */
final class Authority
{
    /**
     * @param  list<string>  $capabilities  sorted
     * @param  bool  $ownerRole  whether the account holds the built-in OWNER role — for SECURITY AU-18's factor test only (AZ-13)
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $active,
        public readonly array $capabilities,
        public readonly CompanyScope $scope,
        public readonly bool $ownerRole = false,
    ) {}

    public static function none(int $userId): self
    {
        return new self($userId, false, [], CompanyScope::none());
    }

    public function has(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * RV-07 and RV-08's comparison (DP-07): whether this authority still covers a recorded export context — every
     * recorded company in scope, every recorded view capability held, every masking entry that names a capability
     * held —, the account active.
     *
     * @param  list<string>  $companies
     * @param  list<string>  $capabilities
     * @param  list<string>  $masking
     */
    public function covers(array $companies, array $capabilities, array $masking): bool
    {
        foreach ($companies as $company) {
            if (! is_numeric($company) || ! $this->scope->includes((int) $company)) {
                return false;
            }
        }

        foreach ([...$capabilities, ...array_filter($masking, fn (string $entry): bool => CapabilityCatalogue::has($entry))] as $capability) {
            if (! $this->has($capability)) {
                return false;
            }
        }

        return $this->active;
    }

    /** The capability, and a company in scope when one is named (AZ-01's scope and capability steps). */
    public function allows(string $capability, ?int $companyId = null): bool
    {
        return $this->has($capability) && ($companyId === null || $this->scope->includes($companyId));
    }
}
