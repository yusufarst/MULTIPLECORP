<?php

namespace App\Modules\IAM\Data;

use InvalidArgumentException;

/**
 * Who may see a field or a row, in the terms of PERMISSIONS_MATRIX §7.2's audience column: ALL, SV
 * (`stock.view`), PV(c), FV(c), CV(c), EV(c) and the other view capabilities for a company — `(any)` when
 * any company in scope is enough —, OWN (the Owner, through an OWNER_ONLY capability), SELF (the account's
 * own row) and none. Each admits only an active account; none admits nobody, the Owner included.
 */
final class Audience
{
    /**
     * @param  list<self>  $any
     */
    private function __construct(
        private readonly string $kind,
        private readonly ?string $capability = null,
        private readonly ?int $companyId = null,
        private readonly ?int $userId = null,
        private readonly array $any = [],
    ) {}

    /** ALL: every active account (S0). */
    public static function all(): self
    {
        return new self('all');
    }

    /** SV: the pooled physical view (S1). */
    public static function pool(): self
    {
        return new self('capability', 'stock.view');
    }

    /** A family's view capability for the given company, or for any company in scope when none is named. */
    public static function family(string $capability, ?int $companyId = null): self
    {
        if (! CapabilityCatalogue::has($capability)) {
            throw new InvalidArgumentException("{$capability} is not a capability of PERMISSIONS_MATRIX §4.");
        }

        return new self('family', $capability, $companyId);
    }

    /** OWN: the Owner, through the named OWNER_ONLY capability. */
    public static function owner(string $capability = 'scope.all_companies'): self
    {
        if (! CapabilityCatalogue::isOwnerOnly($capability)) {
            throw new InvalidArgumentException("{$capability} is not an OWNER_ONLY capability.");
        }

        return new self('capability', $capability);
    }

    /** SELF: the account whose own row it is. */
    public static function self(int $userId): self
    {
        return new self('self', userId: $userId);
    }

    /** none: no account, the Owner included (a password hash, a token, `command_log`). */
    public static function none(): self
    {
        return new self('none');
    }

    /** The first audience of the list that admits the viewer admits it. */
    public static function anyOf(self ...$audiences): self
    {
        return new self('any', any: array_values($audiences));
    }

    public function admits(Authority $viewer): bool
    {
        if (! $viewer->active) {
            return false;
        }

        return match ($this->kind) {
            'all' => true,
            'capability' => $viewer->has((string) $this->capability),
            // A family's field is company-held (S2–S4): without a named company, at least one in scope —
            // also for `stock.view`, whose pooled view needs none (§7.1; CS-05).
            'family' => $viewer->allows((string) $this->capability, $this->companyId) && ($this->companyId !== null || ! $viewer->scope->isEmpty()),
            'self' => $viewer->userId === $this->userId,
            'any' => array_filter($this->any, fn (self $audience): bool => $audience->admits($viewer)) !== [],
            default => false,
        };
    }
}
