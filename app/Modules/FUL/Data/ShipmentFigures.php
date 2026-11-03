<?php

namespace App\Modules\FUL\Data;

use App\Modules\INV\Data\ClientReturns;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A shipment line's delivery figures, pure (PX-08; C-30; C-09): net delivered = its deliveries less their exact reversals;
 * what a refusal or an undelivered closure still holds back is the closed quantity the dispatch's restoration has not yet
 * brought back (SF-RESTORE); what can still be delivered is net shipped less both. A quantity entered at the shipment's
 * unit converts at its snapshot and must be whole in the product's scale.
 */
final class ShipmentFigures
{
    /** A shipment line still awaiting: net shipped above net delivered and what closures hold back. */
    public const AWAITING = <<<'SQL'
        exists (select 1 from dispatch_lines d where d.stock_movement_id = m.id and
            (select coalesce(sum(c.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base), 0) from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id where c.dispatch_line_id = d.id)
            >
        SQL
        // Net delivered: what the client returned is no longer delivered (PX-08; TASK-048).
        .ClientReturns::NET_DELIVERED.<<<'SQL'
            + greatest(0, (select coalesce(sum(k.closed_qty_base), 0) from delivery_closures k where k.dispatch_line_id = d.id and k.closure_kind <> 'LOST_IN_TRANSIT' and not exists (select 1 from delivery_closures s where s.supersedes_id = k.id))
                - (select coalesce(sum(r.qty_base + coalesce((select sum(e.qty_delta_base) from stock_movement_entries e where e.stock_restoration_id = r.id and e.movement_type = 'MOVEMENT_REVERSAL'), 0)), 0) from stock_restorations r join stock_consumptions c on c.id = r.stock_consumption_id where c.dispatch_line_id = d.id and r.kind = 'UNDELIVERED_RETURN'
                    and r.recorded_at > (select min(k.recorded_at) from delivery_closures k where k.dispatch_line_id = d.id and k.closure_kind <> 'LOST_IN_TRANSIT'))))
        SQL;

    /**
     * Per shipment line `d`: net shipped, net delivered, closed for refusal or non-delivery, and returned undelivered after the
     * first such closure, a superseded one included — strictly after, so a return in the closure's own second releases nothing,
     * as recorded times are kept to the second — an UNDELIVERED_RETURN before it brought back other units, which no closure
     * held, and one after it keeps its credit when the closure is corrected (PX-08; SF-RESTORE; CM-27); one reversed in error
     * by its MOVEMENT_REVERSAL credits nothing for what it took back (CM-37; TASK-048).
     */
    public const SQL = <<<'SQL'
        (select coalesce(sum(c.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base), 0) from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id where c.dispatch_line_id = d.id) as shipped,
        SQL
        // Net delivered: the deliveries less what the client returned since, its return never reversing them (PX-08; TASK-048).
        .ClientReturns::NET_DELIVERED." as delivered,\n".<<<'SQL'
        (select coalesce(sum(k.closed_qty_base), 0) from delivery_closures k where k.dispatch_line_id = d.id and k.closure_kind <> 'LOST_IN_TRANSIT' and not exists (select 1 from delivery_closures s where s.supersedes_id = k.id)) as closed,
        (select coalesce(sum(r.qty_base + coalesce((select sum(e.qty_delta_base) from stock_movement_entries e where e.stock_restoration_id = r.id and e.movement_type = 'MOVEMENT_REVERSAL'), 0)), 0) from stock_restorations r join stock_consumptions c on c.id = r.stock_consumption_id where c.dispatch_line_id = d.id and r.kind = 'UNDELIVERED_RETURN'
            and r.recorded_at > (select min(k.recorded_at) from delivery_closures k where k.dispatch_line_id = d.id and k.closure_kind <> 'LOST_IN_TRANSIT')) as returned
        SQL;

    /**
     * @param  list<array{qty: string, reversal: bool}>  $lines  the line's delivery lines in base units
     */
    public static function delivered(array $lines): BigDecimal
    {
        $net = BigDecimal::zero();

        foreach ($lines as $line) {
            $net = $line['reversal'] ? $net->minus($line['qty']) : $net->plus($line['qty']);
        }

        return $net;
    }

    /** The closed quantity still out: closed for refusal or non-delivery, less what came back undelivered since. */
    public static function held(BigDecimal $closed, BigDecimal $returned): BigDecimal
    {
        return BigDecimal::max(BigDecimal::zero(), $closed->minus($returned));
    }

    /** What can still be delivered or closed: net shipped − net delivered − held, never below zero. */
    public static function remainder(BigDecimal $shipped, BigDecimal $delivered, BigDecimal $held): BigDecimal
    {
        return BigDecimal::max(BigDecimal::zero(), $shipped->minus($delivered)->minus($held));
    }

    /** An entered quantity in base units at the shipment's snapshot; null when it is not positive or not whole in the scale (C-09). */
    public static function base(string $entered, int $ratio, int $scale): ?BigDecimal
    {
        $base = BigDecimal::of($entered)->multipliedBy(max(1, $ratio));

        return $base->isPositive() && $base->isEqualTo($base->toScale(max(0, $scale), RoundingMode::Down)) ? $base : null;
    }
}
