<?php

use App\Support\Database\TableRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// TASK-058 (DIR-088; PERFORMANCE §12; IX-13): the partial indexes TASK-005 deferred on `audit_events` — (`company_id`,
// `occurred_at`, `id`) over the acknowledged action codes (QS-21; QS-14) and over the routed codes (QS-20), and
// (`command_id`) over the routed codes for QS-20's every-company test. Each predicate is the literal list of the codes
// the Tasks TASK-058 depends on register, the same list the queries write (OPS AuditCodes; PF-10); a code registered
// later extends both in its own Task. Expand-only: no row is touched.
return new class extends Migration
{
    private const ACKNOWLEDGED = "'DOC.EvidenceDuplicateOverridden', 'FIN.PaymentDuplicateOverridden', 'FUL.DeliveryClosedDuplicateConfirmed', 'FUL.DeliveryCorrectedDuplicateConfirmed', "
        ."'FUL.DeliveryDuplicateConfirmed', 'FUL.DropShipConfirmationReversalDuplicateConfirmed', 'FUL.DropShipReturnDuplicateConfirmed', 'FUL.HandoverDuplicateConfirmed', "
        ."'FUL.LossInTransitDuplicateConfirmed', 'INV.ClientReturnDuplicateConfirmed', 'INV.ConditionChangedDuplicateConfirmed', 'INV.CountAppliedDuplicateConfirmed', "
        ."'INV.DispatchReversalDuplicateConfirmed', 'INV.GoodsDispatchDuplicateConfirmed', 'INV.LossRecognizedDuplicateConfirmed', 'INV.StockAdjustedDuplicateConfirmed', "
        ."'INV.UnattributedRecognizedDuplicateConfirmed', 'INV.UnitsReinspectedDuplicateConfirmed'";

    private const ROUTED = "'INV.AllocationRouted', 'INV.LossRouted', 'INV.MovementReversalRouted', 'INV.ReservationCutRouted', 'PUR.ReturnOutcomeRouted'";

    public function up(): void
    {
        TableRules::for('audit_events')
            ->index('audit_events_ix13_acknowledged', 'company_id, occurred_at, id', 'action in ('.self::ACKNOWLEDGED.')')
            ->index('audit_events_ix13_routed', 'company_id, occurred_at, id', 'action in ('.self::ROUTED.')')
            ->index('audit_events_ix13_routed_command', 'command_id', 'action in ('.self::ROUTED.')');
    }

    public function down(): void
    {
        DB::statement('drop index audit_events_ix13_routed_command');
        DB::statement('drop index audit_events_ix13_routed');
        DB::statement('drop index audit_events_ix13_acknowledged');
    }
};
