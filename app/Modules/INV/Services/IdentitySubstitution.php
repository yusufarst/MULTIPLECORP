<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\INV\Data\DocumentDisposition;
use App\Modules\INV\Data\StockFigures;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Koreksi Nomor Seri atau Lot" (AX-35; CM-36; L-48; WORKFLOWS §8): the same product and quantity, the recorded unit physically present back in its recorded condition, the unit actually shipped, in stock and usable, taking its place — an IDENTITY_SUBSTITUTION net zero per product, a SUBSTITUTION restoration and a new DISPATCH consumption on the same dispatch line, HPP contra'd at the old consumption's current net cost and drawn at the new lot's, the allocation reversed and re-recorded by the lot's company, a new one needing its authority again (XL-1). The line, reservation, shipment, delivery and quantities do not change.
 */
final class IdentitySubstitution
{
    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly Corrections $corrections,
        private readonly Revalidation $revalidation,
        private readonly AuditRecorder $audit,
        private readonly DispatchDocuments $documents,
    ) {}

    /** AX-35's set: the product, both places' scopes and lots, both serials, both lots' cost guards with the bearer project, the consumption's guard, the evidence. */
    public function locks(int $consumptionId, int $shippedLot, int $shippedLocation, ?int $recordedSerial, ?int $shippedSerial, int $evidence): LockSet
    {
        $consumption = self::consumption($consumptionId);
        $product = (int) Row::text($consumption, 'product_id');
        $lot = (int) Row::text($consumption, 'lot_id');
        $set = CostPosting::consumptionLocks($consumptionId)->union(EvidenceGuard::locks($evidence))->add('stock_product_balances', ['product_id' => $product])->add('stock_consumption_balances', ['stock_consumption_id' => $consumptionId])->add('lot_cost_balances', ['lot_id' => $shippedLot])->union($this->documents->locks((int) Row::text($consumption, 'stock_movement_id')));
        $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $shippedLocation, 'condition' => 'USABLE']);
        $set->add('stock_lot_balances', ['lot_id' => $shippedLot, 'location_id' => $shippedLocation, 'condition' => 'USABLE']);

        foreach (DB::table('stock_movement_entries')->where('stock_consumption_id', $consumptionId)->get(['location_id', 'condition']) as $place) {
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => (int) Row::text($place, 'location_id'), 'condition' => Row::text($place, 'condition')]);
            $set->add('stock_lot_balances', ['lot_id' => $lot, 'location_id' => (int) Row::text($place, 'location_id'), 'condition' => Row::text($place, 'condition')]);
        }

        $serials = array_values(array_filter([$recordedSerial, $shippedSerial], fn (?int $serial): bool => $serial !== null));

        foreach ($serials === [] ? [] : DB::table('serial_units')->whereIn('id', $serials)->orderBy('id')->pluck('serial_key') as $key) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => is_string($key) ? $key : '']);
        }

        return $set;
    }

    /** ROUTE-189: the substitution committed; its movement. */
    public function substitute(CommandContext $context, int $consumptionId, string $quantity, ?int $recordedSerial, int $shippedLot, int $shippedLocation, ?int $shippedSerial, ?string $allocationReason, int $evidence, string $reason, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen, ?DocumentDisposition $document = null): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require($this->locks($consumptionId, $shippedLot, $shippedLocation, $recordedSerial, $shippedSerial, $evidence));
        $old = self::consumption($consumptionId);

        if ((bool) Row::text($old, 'is_serialized') !== $serializedSeen || (int) Row::text($old, 'product_scale') !== $scaleSeen) {
            throw Refused::conflict('ST-09', ['serialized' => (bool) Row::text($old, 'is_serialized'), 'scale' => (int) Row::text($old, 'product_scale')]);
        }

        $product = (int) Row::text($old, 'product_id');
        $scale = max(0, (int) Row::text($old, 'quantity_scale'));
        $qty = ReceivingRecords::base($quantity, 1, $scale);
        $open = BigDecimal::of(Row::text($old, 'qty_base'))->minus(Row::text($old, 'restored_qty_base'))->minus(Row::text($old, 'reclassified_qty_base'))->minus(Row::text($old, 'substituted_qty_base'));

        if (! $qty->isPositive() || $qty->isGreaterThan($open)) {
            throw Refused::rejected('C-27', ['reason' => 'above_open', 'open' => StockFigures::plain((string) $open)]);
        }

        $shipped = DB::table('inventory_lots')->where('id', $shippedLot)->first(['product_id', 'source_company_id']);

        if ($shipped === null || (int) Row::text($shipped, 'product_id') !== $product) {
            throw Refused::rejected('C-07', ['reason' => 'product']);
        }

        $held = DB::table('stock_lot_balances as b')->where('lot_id', $shippedLot)->where('location_id', $shippedLocation)->where('condition', 'USABLE')->value('qty');

        if (! is_string($held) || $qty->isGreaterThan($held)) {
            throw Refused::rejected('C-02', ['reason' => 'shipped_not_in_stock']);
        }

        $bearer = (int) Row::text($old, 'bearer_company_id');
        $source = (int) Row::text($shipped, 'source_company_id');
        $this->ensureSerials($consumptionId, $product, $qty, $recordedSerial, $shippedSerial, $shippedLot, $shippedLocation);
        $crossed = $source !== $bearer;
        $this->ensureAllocation($context, $bearer, (int) Row::text($old, 'project_id'), $source, $crossed, $allocationReason);
        $place = DB::table('stock_movement_entries')->where('stock_consumption_id', $consumptionId)->where('qty_delta_base', '<', 0)->when($recordedSerial !== null, fn ($where) => $where->where('serial_unit_id', $recordedSerial))->orderBy('id')->first(['location_id', 'condition']);

        if ($place === null) {
            throw Refused::rejected('C-27', ['reason' => 'no_entry']);
        }

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $company = (int) Row::text($old, 'company_id');
        $project = (int) Row::text($old, 'project_id');
        $dispatchLine = (int) Row::text($old, 'dispatch_line_id');
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'IDENTITY_SUBSTITUTION', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => $company, 'project_id' => $project,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $base = (int) Row::text($old, 'base_unit_id');
        $restoration = (int) DB::table('stock_restorations')->insertGetId([
            'stock_movement_id' => $movement, 'stock_consumption_id' => $consumptionId, 'source_company_id' => (int) Row::text($old, 'source_company_id'), 'product_id' => $product, 'quantity_scale' => $scale,
            'cited_dispatch_line_id' => $dispatchLine, 'cited_unit_id' => (int) Row::text($old, 'entered_unit_id'), 'cited_ratio_to_base' => (int) Row::text($old, 'ratio_to_base'),
            'entered_qty' => (string) $qty, 'entered_unit_id' => $base, 'ratio_to_base' => 1, 'qty_base' => (string) $qty, 'kind' => 'SUBSTITUTION', 'returned_condition' => Row::text($place, 'condition'),
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $contra = $this->cost->restore($context, $restoration, $businessDate);
        GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $consumptionId], 'deltas' => ['substituted_qty_base' => (string) $qty]]]);
        $new = (int) DB::table('stock_consumptions')->insertGetId([
            'stock_movement_id' => $movement, 'lot_id' => $shippedLot, 'source_company_id' => $source, 'product_id' => $product, 'quantity_scale' => $scale, 'qty_base' => (string) $qty, 'kind' => 'DISPATCH', 'dispatch_line_id' => $dispatchLine,
            'bearer_company_id' => $bearer, 'bearer_project_id' => $project, 'allocation_reason' => $crossed ? $allocationReason : null, 'allocation_authorized_by' => $crossed ? $actor : null,
            'command_id' => $commandId, 'command_line_no' => 2, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $context->connection->table('stock_consumption_balances')->insert(['stock_consumption_id' => $new, 'qty_base' => (string) $qty]);
        $back = ['lot' => (int) Row::text($old, 'lot_id'), 'product' => $product, 'scale' => $scale, 'location' => (int) Row::text($place, 'location_id'), 'condition' => Row::text($place, 'condition'), 'qty' => (string) $qty, 'restoration' => $restoration, ...($recordedSerial === null ? [] : ['serial' => $recordedSerial])];
        $out = ['lot' => $shippedLot, 'product' => $product, 'scale' => $scale, 'location' => $shippedLocation, 'condition' => 'USABLE', 'qty' => (string) $qty->negated(), 'consumption' => $new, ...($shippedSerial === null ? [] : ['serial' => $shippedSerial])];
        $ids = $this->inventory->ledger($context, $movement, 'IDENTITY_SUBSTITUTION', [$back, $out]);

        if ($recordedSerial !== null && DB::table('serial_units')->where('id', $recordedSerial)->where('state', 'DISPATCHED')->update(['state' => 'IN_STOCK', 'current_lot_id' => $back['lot'], 'current_location_id' => $back['location'], 'current_condition' => $back['condition'], 'last_entry_id' => $ids[0]]) !== 1) {
            throw Refused::rejected('C-02', ['reason' => 'recorded_not_dispatched']);
        }

        if ($shippedSerial !== null && DB::table('serial_units')->where('id', $shippedSerial)->where('state', 'IN_STOCK')->where('current_lot_id', $shippedLot)->update(['state' => 'DISPATCHED', 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null, 'last_entry_id' => $ids[1]]) !== 1) {
            throw Refused::rejected('C-02', ['reason' => 'shipped_not_in_stock']);
        }

        $drawn = $this->cost->draw($context, $new, $businessDate);
        $net = DB::table('cost_attributions')->whereIn('id', array_values(array_filter([$contra, $drawn], fn (?int $id): bool => $id !== null)))->sum('amount');

        if (! BigDecimal::of((string) $net)->isZero()) {
            $this->revalidation->revalidate($context, $project, $publicId);
        }

        $proof = DB::table('evidence_documents')->where('id', $evidence)->first(['evidence_scope', 'company_id']) ?? throw Refused::rejected('C-14', ['reason' => 'evidence']);
        DB::table('evidence_links')->insert(['evidence_document_id' => $evidence, 'evidence_scope' => Row::text($proof, 'evidence_scope'), 'company_id' => Row::nullable($proof, 'company_id'), 'role' => 'PRIMARY_PROOF', 'stock_movement_id' => $movement, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor]);
        // The Surat Jalan names the serials, not the lots: only a serial's substitution affects what it says.
        $disposed = $recordedSerial === null ? null : $this->documents->dispose($context, (int) Row::text($old, 'stock_movement_id'), $document);
        $this->corrections->record($context, 'INV.DispatchIdentitySubstituted', AuthorityClass::AdmPlus, $company, 'stock_movement', $movement, $publicId, AuditRelation::Reverses, 'stock_movement', Row::text($old, 'movement_public_id'), $reason,
            AuditChange::updated(['lot' => (int) Row::text($old, 'lot_id')], ['lot' => $shippedLot, 'quantity' => (string) $qty, ...($crossed ? ['allocated' => true] : []), ...($disposed === null ? [] : ['document' => $disposed])]), null, ['business_date' => $businessDate]);

        return $movement;
    }

    /** The recorded unit on this consumption and still out; the shipped unit in stock, usable, at its lot and rack; a serialized consumption's serials named, one unit each (C-02; L-13). */
    private function ensureSerials(int $consumptionId, int $product, BigDecimal $qty, ?int $recordedSerial, ?int $shippedSerial, int $shippedLot, int $shippedLocation): void
    {
        $tracked = DB::table('stock_movement_entries')->where('stock_consumption_id', $consumptionId)->whereNotNull('serial_unit_id')->exists();
        $serialized = (bool) DB::table('products')->where('id', $product)->value('is_serialized');

        if ($tracked !== ($recordedSerial !== null) || $serialized !== ($shippedSerial !== null) || (($recordedSerial !== null || $shippedSerial !== null) && ! $qty->isEqualTo(1))) {
            throw Refused::rejected('C-02', ['reason' => 'serial_count']);
        }

        if ($recordedSerial !== null && ! DispatchRestoration::stillOut($consumptionId, $recordedSerial)) {
            throw Refused::rejected('C-02', ['reason' => 'recorded_not_dispatched']);
        }

        if ($shippedSerial !== null && ! DB::table('serial_units')->where('id', $shippedSerial)->where('product_id', $product)->where('state', 'IN_STOCK')->where('current_lot_id', $shippedLot)->where('current_location_id', $shippedLocation)->where('current_condition', 'USABLE')->exists()) {
            throw Refused::rejected('C-02', ['reason' => 'shipped_not_in_stock']);
        }
    }

    /** XL-1: a substitution onto another company's lot is a new allocation, its authority needed again; otherwise refused and routed, one audit event per affected company committed with the refusal (AZ-08; SQ-23). */
    private function ensureAllocation(CommandContext $context, int $bearer, int $project, int $source, bool $crossed, ?string $reason): void
    {
        if (! $crossed) {
            return;
        }

        $authority = $context->authority();

        if ($authority !== null && $authority->scope->includes($bearer) && $authority->allows('stock.allocate_intercompany', $bearer) && $reason !== null && trim($reason) !== '') {
            return;
        }

        $actor = $context->request->actorUserId;
        $command = $context->request->commandId;

        throw Refused::rejected('AZ-08', ['routed' => true, 'reason' => 'allocation'], function (ConnectionInterface $connection) use ($bearer, $project, $source, $actor, $command): void {
            $this->audit->record(action: 'INV.AllocationRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $bearer, entityType: 'project', entityId: $project, commandId: $command, metadata: ['routed_action' => 'INV.SubstituteDispatchIdentity'], connection: $connection);
            $this->audit->record(action: 'INV.AllocationRouted', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: $source, entityType: 'company', entityId: $source, commandId: $command, metadata: ['routed_action' => 'INV.SubstituteDispatchIdentity'], connection: $connection);
        });
    }

    private static function consumption(int $consumptionId): object
    {
        return DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('dispatch_lines as d', 'd.id', '=', 'c.dispatch_line_id')->join('stock_movements as m', 'm.id', '=', 'd.stock_movement_id')->join('products as p', 'p.id', '=', 'c.product_id')->where('c.id', $consumptionId)
            ->first(['c.id', 'd.stock_movement_id', 'c.lot_id', 'c.source_company_id', 'c.product_id', 'c.quantity_scale', 'c.dispatch_line_id', 'c.bearer_company_id', 'b.qty_base', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base', 'd.company_id', 'd.project_id', 'd.entered_unit_id', 'd.ratio_to_base', 'm.public_id as movement_public_id', 'p.base_unit_id', 'p.is_serialized', 'p.quantity_scale as product_scale'])
            ?? throw Refused::rejected('C-14', ['reason' => 'consumption']);
    }
}
