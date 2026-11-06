<?php

namespace App\Modules\Reporting\Signals;

/**
 * The audit action codes the review signals read (PF-10; DIR-088): the acknowledged variant — a duplicate warning
 * overridden or confirmed (CONCURRENCY_IDEMPOTENCY section 11), by the family that reads it (PERMISSIONS_MATRIX §7.4)
 * — and the routed variant of SQ-23 — a refusal routed to an Owner or an account with every affected company in
 * scope. Each list is the literal list the modules this Task depends on register, the same lists IX-13's partial
 * indexes are built over; a code registered later extends both in its own Task. No code is invented.
 */
final class AuditCodes
{
    /** The acknowledged codes of stock events (RD-02). */
    public const ACKNOWLEDGED_STOCK = [
        'INV.ClientReturnDuplicateConfirmed',
        'INV.ConditionChangedDuplicateConfirmed',
        'INV.CountAppliedDuplicateConfirmed',
        'INV.DispatchReversalDuplicateConfirmed',
        'INV.GoodsDispatchDuplicateConfirmed',
        'INV.LossRecognizedDuplicateConfirmed',
        'INV.StockAdjustedDuplicateConfirmed',
        'INV.UnattributedRecognizedDuplicateConfirmed',
        'INV.UnitsReinspectedDuplicateConfirmed',
    ];

    /** The acknowledged codes of delivery events (RD-03). */
    public const ACKNOWLEDGED_DELIVERY = [
        'FUL.DeliveryClosedDuplicateConfirmed',
        'FUL.DeliveryCorrectedDuplicateConfirmed',
        'FUL.DeliveryDuplicateConfirmed',
        'FUL.DropShipConfirmationReversalDuplicateConfirmed',
        'FUL.DropShipReturnDuplicateConfirmed',
        'FUL.HandoverDuplicateConfirmed',
        'FUL.LossInTransitDuplicateConfirmed',
    ];

    /** The acknowledged code of payments (RD-04). */
    public const ACKNOWLEDGED_PAYMENT = ['FIN.PaymentDuplicateOverridden'];

    /** The acknowledged code of evidence (RD-08). */
    public const ACKNOWLEDGED_EVIDENCE = ['DOC.EvidenceDuplicateOverridden'];

    /** The routed codes (SQ-23): each refused command writes one event per affected company under its `command_id`. */
    public const ROUTED = [
        'INV.AllocationRouted',
        'INV.LossRouted',
        'INV.MovementReversalRouted',
        'INV.ReservationCutRouted',
        'PUR.ReturnOutcomeRouted',
    ];

    /**
     * The routed codes as the literal SQL list of IX-13's partial index predicate, so a statement's predicate implies
     * the index's and its plan uses it (PERFORMANCE §12); `AuditCodesTest` holds it equal to ROUTED.
     */
    public const ROUTED_SQL = "'INV.AllocationRouted', 'INV.LossRouted', 'INV.MovementReversalRouted', 'INV.ReservationCutRouted', 'PUR.ReturnOutcomeRouted'";

    /** Every acknowledged code as the literal SQL list of IX-13's partial index predicate, sorted; held equal to acknowledged(). */
    public const ACKNOWLEDGED_SQL = "'DOC.EvidenceDuplicateOverridden', 'FIN.PaymentDuplicateOverridden', 'FUL.DeliveryClosedDuplicateConfirmed', 'FUL.DeliveryCorrectedDuplicateConfirmed', "
        ."'FUL.DeliveryDuplicateConfirmed', 'FUL.DropShipConfirmationReversalDuplicateConfirmed', 'FUL.DropShipReturnDuplicateConfirmed', 'FUL.HandoverDuplicateConfirmed', "
        ."'FUL.LossInTransitDuplicateConfirmed', 'INV.ClientReturnDuplicateConfirmed', 'INV.ConditionChangedDuplicateConfirmed', 'INV.CountAppliedDuplicateConfirmed', "
        ."'INV.DispatchReversalDuplicateConfirmed', 'INV.GoodsDispatchDuplicateConfirmed', 'INV.LossRecognizedDuplicateConfirmed', 'INV.StockAdjustedDuplicateConfirmed', "
        ."'INV.UnattributedRecognizedDuplicateConfirmed', 'INV.UnitsReinspectedDuplicateConfirmed'";

    /** @return list<string> every acknowledged code, sorted */
    public static function acknowledged(): array
    {
        $codes = [...self::ACKNOWLEDGED_STOCK, ...self::ACKNOWLEDGED_DELIVERY, ...self::ACKNOWLEDGED_PAYMENT, ...self::ACKNOWLEDGED_EVIDENCE];
        sort($codes);

        return $codes;
    }
}
