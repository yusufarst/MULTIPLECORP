<?php

namespace App\Modules\PRJ\Data;

/**
 * A completion predicate's state for one project (BR-PRJ-02): whether it has facts to judge — a project without an
 * invoice has nothing to bill, so its billing predicate holds without applying —, and its unmet items, oldest first.
 * A predicate holds when it has no unmet item.
 */
final class PredicateState
{
    /** @param  list<JourneyItem>  $items */
    public function __construct(
        public readonly bool $applies,
        public readonly array $items = [],
    ) {}

    /** No facts to judge: the predicate holds and no stage of its own applies. */
    public static function none(): self
    {
        return new self(false);
    }

    public function met(): bool
    {
        return $this->items === [];
    }

    /** The state without QS-15's money items, for a viewer without `finance.view` (PJ-13). */
    public function withoutMoney(): self
    {
        return new self($this->applies, array_values(array_filter($this->items, fn (JourneyItem $item): bool => ! $item->money)));
    }
}
