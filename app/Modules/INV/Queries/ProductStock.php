<?php

namespace App\Modules\INV\Queries;

use App\Modules\IAM\Data\Audience;
use App\Modules\IAM\Data\Authority;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * A product's pooled stock figures as the guard holds them (DATABASE §4.7; CALC-01; RD-02), INV's query class that the
 * catalogue reads (ARCHITECTURE §3): ON HAND, RESERVED, UNUSABLE and AVAILABLE = ON HAND − RESERVED − UNUSABLE, to a
 * holder of `stock.view` only — the pooled view needs no company (§7.1) —; and ON HAND alone for the serial-policy
 * switch, read under the guard the command holds at LK-13 (L-35; ST-09).
 */
final class ProductStock
{
    public static function audience(): Audience
    {
        return Audience::pool();
    }

    /** @return array{on_hand: string, reserved: string, unusable: string, available: string}|null */
    public function figures(Authority $viewer, int $productId): ?array
    {
        if (! self::audience()->admits($viewer)) {
            return null;
        }

        $row = DB::table('stock_product_balances')->where('product_id', $productId)
            ->first(['on_hand_qty', 'reserved_qty', 'unusable_qty', DB::raw('on_hand_qty - reserved_qty - unusable_qty as available_qty')]);

        return $row === null ? ['on_hand' => '0', 'reserved' => '0', 'unusable' => '0', 'available' => '0'] : [
            'on_hand' => Row::text($row, 'on_hand_qty'),
            'reserved' => Row::text($row, 'reserved_qty'),
            'unusable' => Row::text($row, 'unusable_qty'),
            'available' => Row::text($row, 'available_qty'),
        ];
    }

    /** The product's ON HAND in base units, as its guard row holds it; zero when it has none. */
    public function onHand(int $productId): string
    {
        $value = DB::table('stock_product_balances')->where('product_id', $productId)->value('on_hand_qty');

        return is_scalar($value) ? (string) $value : '0';
    }
}
