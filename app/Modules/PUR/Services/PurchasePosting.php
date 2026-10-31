<?php

namespace App\Modules\PUR\Services;

use App\Modules\CST\Data\ReceiptPosted;
use App\Support\Command\CommandContext;
use App\Support\GuardUpdate;

/**
 * The only normal writer of `purchase_line_balances` (ARCHITECTURE §7; GU-01): its quantities and cumulative posted values moved in one statement per command, its CHECKs reading the end state (C-29; C-57).
 */
final class PurchasePosting
{
    public function bear(CommandContext $context, int $lineId, string $ordered, string $goods, string $tax): void
    {
        GuardUpdate::bear($context->connection, 'purchase_line_balances', ['purchase_line_id' => $lineId], ['ordered_qty_base' => $ordered, 'goods_value' => $goods, 'noncreditable_tax_value' => $tax, 'recorded_at' => $context->now()]);
    }

    /** @param  array<string, string>  $deltas  column => signed decimal */
    public function move(CommandContext $context, int $lineId, array $deltas): void
    {
        $deltas = array_filter($deltas, fn (string $delta): bool => preg_match('/^-?0+(\.0+)?$/', $delta) !== 1);

        if ($deltas !== []) {
            GuardUpdate::apply($context->connection, 'purchase_line_balances', [['key' => ['purchase_line_id' => $lineId], 'deltas' => $deltas]]);
        }
    }

    /** What a cost posting carried of the line's goods and non-creditable tax (C-57). */
    public function posted(CommandContext $context, int $lineId, ReceiptPosted $posted): void
    {
        $this->move($context, $lineId, ['posted_goods_value' => $posted->goods->toString(), 'posted_noncreditable_tax_value' => $posted->tax->toString()]);
    }
}
