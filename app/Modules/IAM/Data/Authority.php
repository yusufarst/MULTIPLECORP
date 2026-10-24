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
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $active,
        public readonly array $capabilities,
        public readonly CompanyScope $scope,
    ) {}

    public static function none(int $userId): self
    {
        return new self($userId, false, [], CompanyScope::none());
    }

    public function has(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /** The capability, and a company in scope when one is named (AZ-01's scope and capability steps). */
    public function allows(string $capability, ?int $companyId = null): bool
    {
        return $this->has($capability) && ($companyId === null || $this->scope->includes($companyId));
    }
}
