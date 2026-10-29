<?php

namespace App\Modules\CST\Data;

use App\Support\Money;

/**
 * What a posting moved of a purchase line's goods and non-creditable tax (DATABASE §6.1; C-57): the amounts by which
 * the line's `posted_goods_value` and `posted_noncreditable_tax_value` move — negative for a reversal —, which the
 * caller hands to PUR's `PurchasePosting`, the sole writer of `purchase_line_balances` (ARCHITECTURE §7), in the same
 * transaction.
 */
final class ReceiptPosted
{
    public function __construct(
        public readonly Money $goods,
        public readonly Money $tax,
    ) {}

    public static function none(): self
    {
        return new self(Money::zero(), Money::zero());
    }

    public function plus(self $that): self
    {
        return new self($this->goods->plus($that->goods), $this->tax->plus($that->tax));
    }
}
