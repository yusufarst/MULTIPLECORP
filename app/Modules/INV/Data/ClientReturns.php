<?php

namespace App\Modules\INV\Data;

/**
 * What a client returned of a shipment line (WF-FUL-05; PX-08; CM-10; CM-37): its SALES_RETURN restorations, net of the
 * exact reversals of those returns recorded in error — a MOVEMENT_REVERSAL whose entries name the restoration they take
 * back. Net delivered is a shipment line's deliveries less this; the delivery record itself is never reversed.
 */
final class ClientReturns
{
    /** Per shipment line `d`: the quantity the client returned, net of its reversals. */
    public const RETURNED = "(select coalesce(sum(r.qty_base), 0) from stock_restorations r join stock_consumptions c on c.id = r.stock_consumption_id where c.dispatch_line_id = d.id and r.kind = 'SALES_RETURN')"
        .' + (select coalesce(sum(e.qty_delta_base), 0) from stock_movement_entries e join stock_restorations r on r.id = e.stock_restoration_id join stock_consumptions c on c.id = r.stock_consumption_id'
        ." where c.dispatch_line_id = d.id and r.kind = 'SALES_RETURN' and e.movement_type = 'MOVEMENT_REVERSAL')";

    /** Per shipment line `d`: its deliveries less their exact reversals (CM-08). */
    public const DELIVERIES = '(select coalesce(sum(case when x.reverses_delivery_line_id is null then x.qty_base else -x.qty_base end), 0) from delivery_lines x where x.dispatch_line_id = d.id)';

    /** Per shipment line `d`: net delivered — what reached the client less what it returned (PX-08). */
    public const NET_DELIVERED = '('.self::DELIVERIES.' - ('.self::RETURNED.'))';
}
