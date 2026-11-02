<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\OPS\Services\Corrections;
use App\Modules\PUR\Services\PurchasePosting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What a receipt and its reversal record (WF-INV-01; AX-04; AX-05; BR-INV-01; BR-INV-04; L-13; DATABASE §5.3): the RECEIPT movement with exactly one lot of the purchase's company, keyed to its receiving line (C-08), usable units at a rack, damaged ones straight into UNUSABLE, rejected ones on the line with no stock event; serials registered, the line's guard moved through PUR, the lot's cost through CST. A reversal removes only what is unconsumed (CM-02; CM-03).
 */
final class ReceivingRecords
{
    /** FU-13: a lot code regenerated at most this many times (H6-10). */
    public const LOT_CODE_TRIES = 5;

    private const REPLACEMENT_REVERSED = 'A replacement receipt was reversed (AX-05; DATABASE §3.1).';

    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly PurchasePosting $purchases,
        private readonly CostPosting $cost,
        private readonly AuditRecorder $audit,
        private readonly Corrections $corrections,
        private readonly CorrectionCases $cases,
    ) {}

    /** AX-04's set (CONCURRENCY_IDEMPOTENCY §7.1): the purchase with its cost locks, the product, its scopes and serials; the case of a replacement; the reservation's rows of an offer. */
    public function locks(ReceiptEntry $entry): LockSet
    {
        $line = self::line($entry->lineId);
        $product = (int) Row::text($line, 'product_id');
        $set = CostPosting::purchaseLocks($entry->purchaseId)->add('stock_product_balances', ['product_id' => $product]);

        if ($entry->locationId !== null) {
            foreach (['USABLE', 'UNUSABLE'] as $condition) {
                $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $entry->locationId, 'condition' => $condition]);
            }
        }

        foreach ($entry->serials as $serial) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => self::serialKey($serial['serial'])]);
        }

        if ($entry->caseId !== null) {
            $set = $set->union(CorrectionCases::locks($entry->caseId));
        }

        foreach ($entry->evidence as $evidence) {
            $set = $set->union(EvidenceGuard::locks($evidence));
        }

        $demand = Row::nullable($line, 'project_line_id');

        return $entry->reserve !== null && $demand !== null ? $set->union(InventoryPosting::lineLocks((int) $demand)) : $set;
    }

    /** The receipt committed; its movement. */
    public function receive(CommandContext $context, ReceiptEntry $entry): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $line = self::line($entry->lineId);
        $product = (int) Row::text($line, 'product_id');
        $this->ensurePolicy($product, $entry);
        $purchase = DB::table('purchases')->where('id', $entry->purchaseId)->first(['state', 'company_id', 'project_id', 'public_id']);

        if ($purchase === null || (int) Row::text($line, 'purchase_id') !== $entry->purchaseId) {
            throw Refused::rejected('C-14', ['reason' => 'other_line']);
        }

        if (Row::text($purchase, 'state') !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => Row::text($purchase, 'state')]);
        }

        if (Row::text($line, 'kind') !== 'GOODS' || Row::text($line, 'delivery_mode') !== 'WAREHOUSE') {
            throw Refused::rejected('BR-INV-11', ['reason' => 'not_a_warehouse_line']);
        }

        $ratio = self::ratio($product, $entry->unitId);
        $scale = max(0, (int) Row::text($line, 'quantity_scale'));
        $usable = self::base($entry->usable, $ratio, $scale);
        $damaged = self::base($entry->damaged, $ratio, $scale);
        $rejected = self::base($entry->rejected, $ratio, $scale);
        $accepted = $usable->plus($damaged);
        $arrived = $accepted->plus($rejected);

        if (! $arrived->isPositive()) {
            throw Refused::rejected('C-09', ['reason' => 'nothing_arrived']);
        }

        if ($accepted->isPositive() && $entry->locationId === null) {
            throw Refused::rejected('WF-INV-01', ['reason' => 'no_rack']);
        }

        if ($entry->locationId !== null && ! DB::table('locations')->where('id', $entry->locationId)->where('is_active', true)->exists()) {
            throw Refused::rejected('C-14', ['reason' => 'rack']);
        }

        // The same delivery answers "Sudah Tercatat" even on a line already full (BD-01; IP-03).
        $this->ensureDelivery($entry);
        $this->ensureCap($entry, $line, $accepted);
        $serialized = (bool) DB::table('products')->where('id', $product)->value('is_serialized');
        $serials = $this->uniqueSerials($entry);

        if ($serialized ? count($serials) !== (int) (string) $accepted : $serials !== []) {
            throw Refused::rejected('C-02', ['reason' => 'serial_count', 'expected' => (string) $accepted]);
        }

        $company = (int) Row::text($purchase, 'company_id');
        $project = Row::nullable($purchase, 'project_id');
        $now = $context->now();
        $commandId = $context->request->commandId;
        $actor = $context->request->actorUserId;
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'RECEIPT', 'business_date' => $entry->businessDate, 'company_id' => $company, 'project_id' => $project, 'purchase_id' => $entry->purchaseId, 'correction_case_id' => $entry->caseId,
            'supplier_delivery_reference' => $entry->deliveryReference, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $receiving = (int) DB::table('receiving_lines')->insertGetId([
            'stock_movement_id' => $movement, 'company_id' => $company, 'purchase_line_id' => $entry->lineId, 'product_id' => $product, 'quantity_scale' => $scale, 'entered_qty' => (string) BigDecimal::of($entry->usable)->plus($entry->damaged)->plus($entry->rejected), 'entered_unit_id' => $entry->unitId, 'ratio_to_base' => $ratio,
            'qty_base' => (string) $arrived, 'usable_qty_base' => (string) $usable, 'damaged_qty_base' => (string) $damaged, 'rejected_qty_base' => (string) $rejected, 'receipt_kind' => $entry->kind, 'corrects_receiving_line_id' => $entry->correctsReceivingLineId, 'correction_case_id' => $entry->caseId,
            'supplier_delivery_reference' => $entry->deliveryReference, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        // The offer's reserved quantity moves with ON HAND in the ledger's one statement on the product guard (GU-01).
        $reserved = $entry->reserve !== null && BigDecimal::of($entry->reserve)->isPositive() ? $this->reserve($context, $line, $usable, $entry->reserve) : null;

        // Rejected at the door: recorded on the line, no stock event, the remainder staying open (L-13).
        if ($accepted->isPositive()) {
            $lot = $this->createLot($context, $product, $scale, $company, $receiving, $entry, $accepted, $ratio);
            $rows = $this->entries($context, $lot, $product, $scale, (int) $entry->locationId, $usable, $damaged, $entry, $serials);
            $ids = $this->inventory->ledger($context, $movement, 'RECEIPT', $rows, $reserved);

            foreach ($rows as $index => $row) {
                if (isset($row['serial'])) {
                    DB::table('serial_units')->where('id', $row['serial'])->update(['last_entry_id' => $ids[$index]]);
                }
            }

            $this->postCost($context, $entry, $lot, $accepted);
        }

        foreach ($entry->evidence as $position => $evidence) {
            $this->linkEvidence($context, $evidence, $movement, $position === 0 ? 'PRIMARY_PROOF' : 'SUPPORTING', $position + 1);
        }

        $this->audit->record(action: 'INV.GoodsReceived', authorityClass: AuthorityClass::Adm, actorUserId: $actor, companyId: $company, entityType: 'stock_movement', entityId: $movement, entityPublicId: $publicId, commandId: $commandId,
            change: AuditChange::created(['kind' => $entry->kind, 'usable' => (string) $usable, 'damaged' => (string) $damaged, 'rejected' => (string) $rejected, 'delivery' => $entry->deliveryReference]), metadata: ['purchase' => Row::text($purchase, 'public_id'), 'business_date' => $entry->businessDate]);

        return $movement;
    }

    /**
     * AX-05's set, re-derived under the locks (§5): the purchase's cost cascade, the product with the lot's scopes and places, its serials, the reservations cut, the return case of a replacement.
     *
     * @param  array<int, string>  $cuts  reservation => quantity
     */
    public function reversalLocks(int $lotId, array $cuts): LockSet
    {
        $lot = self::lot($lotId);
        $purchase = (int) Row::text($lot, 'purchase_id');
        $product = (int) Row::text($lot, 'product_id');
        $set = CostPosting::purchaseLocks($purchase)->union(CostPosting::lotLocks([$lotId]))->add('stock_product_balances', ['product_id' => $product]);

        foreach (DB::table('stock_lot_balances as b')->where('lot_id', $lotId)->get(['location_id', 'condition']) as $place) {
            $location = (int) Row::text($place, 'location_id');
            $condition = Row::text($place, 'condition');
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $location, 'condition' => $condition]);
            $set->add('stock_lot_balances', ['lot_id' => $lotId, 'location_id' => $location, 'condition' => $condition]);
        }

        foreach (DB::table('serial_units')->where('current_lot_id', $lotId)->orderBy('id')->get(['serial_key']) as $serial) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => Row::text($serial, 'serial_key')]);
        }

        $case = Row::nullable($lot, 'correction_case_id');

        if ($case !== null) {
            $set = $set->union(CorrectionCases::locks((int) $case));
        }

        return $cuts === [] ? $set : $set->union(InventoryPosting::cutLocks($product, array_keys($cuts)));
    }

    /**
     * AX-05: the lot's unconsumed units removed place by place, its cost and charge share leaving through CST, the guard lowered, serials NEVER_RECEIVED, the actor's cut where usable supply would fall below the reservations (IP-20).
     *
     * @param  list<array{location: int, condition: string, qty: string, serial: ?int}>  $removals
     * @param  array<int, string>  $cuts  reservation => quantity
     * @return int the movement
     */
    public function reverse(CommandContext $context, int $lotId, array $removals, array $cuts, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require($this->reversalLocks($lotId, $cuts));
        $lot = self::lot($lotId);
        $product = (int) Row::text($lot, 'product_id');
        $scale = max(0, (int) Row::text($lot, 'quantity_scale'));
        // The lot's own tracked units decide, not the product's policy today: its serials stay identities through a policy change (L-35; C-02).
        $serialized = DB::table('stock_movement_entries')->where('lot_id', $lotId)->whereNotNull('serial_unit_id')->exists();
        $total = BigDecimal::zero();
        $usable = BigDecimal::zero();
        $entries = [];
        $taken = [];
        $named = [];

        foreach ($removals as $removal) {
            $held = DB::table('stock_lot_balances as b')->where('lot_id', $lotId)->where('location_id', $removal['location'])->where('condition', $removal['condition'])->value('qty');
            $qty = BigDecimal::of($removal['qty']);
            $place = "{$removal['location']}|{$removal['condition']}";
            $taken[$place] = BigDecimal::of($taken[$place] ?? '0')->plus($qty);

            if (! $qty->isPositive() || ! is_string($held) || $taken[$place]->isGreaterThan($held)) {
                throw Refused::rejected('C-03', ['reason' => 'above_unconsumed', 'available' => is_string($held) ? $held : '0']);
            }

            if ($serialized) {
                self::ensureSerial($removal, $lotId, $qty, $named);
                $named[(int) $removal['serial']] = true;
            } elseif ($removal['serial'] !== null) {
                throw Refused::rejected('C-02', ['reason' => 'serial_reversal']);
            }

            $entries[] = ['lot' => $lotId, 'product' => $product, 'scale' => $scale, 'location' => $removal['location'], 'condition' => $removal['condition'], 'qty' => (string) $qty->negated(), ...($removal['serial'] === null ? [] : ['serial' => $removal['serial']])];
            $total = $total->plus($qty);
            $usable = $removal['condition'] === 'USABLE' ? $usable->plus($qty) : $usable;
        }

        if (! $total->isPositive()) {
            throw Refused::rejected('C-03', ['reason' => 'nothing']);
        }

        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'RECEIPT_REVERSAL', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => (int) Row::text($lot, 'source_company_id'), 'project_id' => Row::nullable($lot, 'project_id'), 'purchase_id' => (int) Row::text($lot, 'purchase_id'),
            'reverses_movement_id' => (int) Row::text($lot, 'stock_movement_id'), 'reverses_movement_type' => 'RECEIPT', 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ]);

        $reserved = null;

        if ($usable->isPositive() && (InventoryPosting::shortfall($product, (string) $usable)->isPositive() || $cuts !== [])) {
            $reserved = $this->inventory->cut($context, $product, $cuts, (string) $usable, $movement, 'INV.ReverseReceipt');
        }

        $entryIds = $this->inventory->ledger($context, $movement, 'RECEIPT_REVERSAL', $entries, $reserved);

        foreach ($entries as $index => $entry) {
            // Conditional on what it was just read to be (DATABASE §8): never two reversals of one serial (C-02; BR-INV-09).
            if (isset($entry['serial']) && DB::table('serial_units')->where('id', $entry['serial'])->where('state', 'IN_STOCK')->where('current_lot_id', $lotId)->update(['state' => 'NEVER_RECEIVED', 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null, 'last_entry_id' => $entryIds[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial_reversal']);
            }
        }

        // A replacement receipt counts against the line's replacement cap, never its normal received quantity (PX-03).
        $column = Row::text($lot, 'receipt_kind') === ReceiptEntry::REPLACEMENT ? 'replacement_received_net_qty_base' : 'normal_received_net_qty_base';
        $this->purchases->move($context, (int) Row::text($lot, 'purchase_line_id'), [$column => (string) $total->negated()]);
        $posted = $this->cost->reverseReceipt($context, $lotId, (string) $total, $businessDate);
        $this->purchases->posted($context, (int) Row::text($lot, 'purchase_line_id'), $posted);
        $case = Row::nullable($lot, 'correction_case_id');

        if ($column === 'replacement_received_net_qty_base' && $case !== null) {
            $this->replacementReversed($context, $lotId, (int) $case);
        }

        $this->corrections->record($context, 'INV.ReceiptReversed', AuthorityClass::AdmPlus, (int) Row::text($lot, 'source_company_id'), 'stock_movement', $movement, $publicId, AuditRelation::Reverses, 'stock_movement', Row::text($lot, 'movement_public_id'), $reason,
            AuditChange::updated(['quantity' => Row::text($lot, 'acquired_qty_base')], ['reversed' => (string) $total]), null, ['business_date' => $businessDate, 'cuts' => $cuts]);

        return $movement;
    }

    /**
     * A serial named by a reversal, read under its lock (DATABASE §8; C-02): one unit, this lot's, in stock at the place and condition removed, named once.
     *
     * @param  array{location: int, condition: string, qty: string, serial: ?int}  $removal
     * @param  array<int, true>  $named
     */
    private static function ensureSerial(array $removal, int $lotId, BigDecimal $qty, array $named): void
    {
        $serial = $removal['serial'];

        if ($serial === null || ! $qty->isEqualTo(1) || isset($named[$serial])
            || ! DB::table('serial_units')->where('id', $serial)->where('state', 'IN_STOCK')->where('current_lot_id', $lotId)->where('current_location_id', $removal['location'])->where('current_condition', $removal['condition'])->exists()) {
            throw Refused::rejected('C-02', ['reason' => 'serial_reversal']);
        }
    }

    /** The return case's replaced value lowered by what the reversal drew from the replacement lot, reopened when closed (DATABASE §3.1; AX-05). */
    private function replacementReversed(CommandContext $context, int $lotId, int $case): void
    {
        $drawn = DB::table('lot_cost_entries')->where('lot_id', $lotId)->where('entry_kind', 'RECEIPT_REVERSAL')->where('command_id', $context->request->commandId)->selectRaw('coalesce(sum(amount), 0)::text as total')->value('total');

        $this->cases->post($context, $case, ['replaced_value' => Money::of(is_string($drawn) ? $drawn : '0')->toString()], self::REPLACEMENT_REVERSED);
    }

    /** The product's policy as the form read it (ST-09; L-35). */
    private function ensurePolicy(int $product, ReceiptEntry $entry): void
    {
        $row = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']);

        if ($row === null || (bool) $row->is_serialized !== $entry->serializedSeen || (int) Row::text($row, 'quantity_scale') !== $entry->scaleSeen) {
            throw Refused::conflict('ST-09', ['serialized' => $row !== null && (bool) $row->is_serialized, 'scale' => $row === null ? 0 : (int) Row::text($row, 'quantity_scale')]);
        }
    }

    /** The cap of the receipt's kind on the line guard (C-29; PX-03): the remainder in words when exceeded; a replacement only against its separate cap, with its OPEN case (C-59). */
    private function ensureCap(ReceiptEntry $entry, object $line, BigDecimal $accepted): void
    {
        if ($entry->kind === ReceiptEntry::REPLACEMENT) {
            $case = $entry->caseId === null ? null : DB::table('correction_cases')->where('id', $entry->caseId)->first(['state', 'case_kind', 'source_lot_id']);
            $source = $case === null ? null : Row::nullable($case, 'source_lot_id');
            $sourceLine = $source === null ? null : DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->where('i.id', (int) $source)->value('r.purchase_line_id');

            if ($case === null || Row::text($case, 'state') !== 'OPEN' || Row::text($case, 'case_kind') !== 'PURCHASE_RETURN' || ($source !== null && (int) (is_numeric($sourceLine) ? $sourceLine : 0) !== $entry->lineId)) {
                throw Refused::rejected('C-59', ['reason' => 'case_not_open']);
            }
        }

        $room = self::room($entry->kind, $line);

        if ($accepted->isGreaterThan($room)) {
            throw Refused::rejected('C-29', ['reason' => 'above_remainder', 'remainder' => (string) $room]);
        }
    }

    /** The room of a receipt's kind on the line's guard: a replacement counted only against its separate cap (C-29; PX-03). */
    public static function room(string $kind, object $line): BigDecimal
    {
        return $kind === ReceiptEntry::REPLACEMENT
            ? BigDecimal::of(Row::text($line, 'replacement_expected_qty_base'))->minus(Row::text($line, 'replacement_received_net_qty_base'))
            : BigDecimal::of(Row::text($line, 'ordered_qty_base'))->minus(Row::text($line, 'normal_received_net_qty_base'))->minus(Row::text($line, 'closed_qty_base'))->minus(Row::text($line, 'dropship_confirmed_net_qty_base'));
    }

    /** BD-01: one ORIGINAL per line and supplier delivery reference; a corrective kind cites its receipt of the same line (C-59; CM-02; CM-04). */
    private function ensureDelivery(ReceiptEntry $entry): void
    {
        if ($entry->kind === ReceiptEntry::ORIGINAL && $entry->deliveryReference !== null
            && DB::table('receiving_lines')->where('purchase_line_id', $entry->lineId)->where('receipt_kind', ReceiptEntry::ORIGINAL)->where('supplier_delivery_reference', $entry->deliveryReference)->exists()) {
            throw Refused::conflict('BD-01', ['reference' => $entry->deliveryReference]);
        }

        if (in_array($entry->kind, [ReceiptEntry::SUPPLEMENT, ReceiptEntry::RE_RECEIPT], true)) {
            $cited = $entry->correctsReceivingLineId === null ? null : DB::table('receiving_lines')->where('id', $entry->correctsReceivingLineId)->value('purchase_line_id');

            if (! is_numeric($cited) || (int) $cited !== $entry->lineId) {
                throw Refused::rejected('C-59', ['reason' => 'cited_receipt']);
            }

            if ($entry->kind === ReceiptEntry::RE_RECEIPT && ! DB::table('inventory_lots as l')->join('stock_movement_entries as e', 'e.lot_id', '=', 'l.id')->where('l.receiving_line_id', $entry->correctsReceivingLineId)->where('e.movement_type', 'RECEIPT_REVERSAL')->exists()) {
                throw Refused::rejected('C-59', ['reason' => 'not_reversed']);
            }
        }
    }

    /**
     * A serial scanned twice in one command counts once (BD-18).
     *
     * @return array<string, array{serial: string, damaged: bool}>
     */
    private function uniqueSerials(ReceiptEntry $entry): array
    {
        $serials = [];

        foreach ($entry->serials as $serial) {
            $serials[self::serialKey($serial['serial'])] ??= $serial;
        }

        return $serials;
    }

    /** FU-13: the lot with an opaque, generated code (PJ-23); a collision regenerates it, at most five times. */
    private function createLot(CommandContext $context, int $product, int $scale, int $company, int $receiving, ReceiptEntry $entry, BigDecimal $accepted, int $ratio): int
    {
        $now = $context->now();

        $code = self::withLotCode(fn (string $code): int => $context->connection->table('inventory_lots')->insertOrIgnore([
            'lot_code' => $code, 'product_id' => $product, 'quantity_scale' => $scale, 'source_company_id' => $company, 'acquisition_kind' => 'RECEIPT', 'receiving_line_id' => $receiving,
            'entered_qty' => (string) BigDecimal::of($entry->usable)->plus($entry->damaged), 'entered_unit_id' => $entry->unitId, 'ratio_to_base' => $ratio, 'acquired_qty_base' => (string) $accepted, 'business_date' => $entry->businessDate,
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
        ]));
        $id = DB::table('inventory_lots')->where('lot_code', $code)->value('id');

        return is_numeric($id) ? (int) $id : throw Refused::rejected('FU-13', []);
    }

    /**
     * FU-13: a generated code tried until one inserts, at most LOT_CODE_TRIES times; the code taken.
     *
     * @param  Closure(string): int  $insert  the rows the code inserted
     */
    public static function withLotCode(Closure $insert): string
    {
        for ($try = 0; $try < self::LOT_CODE_TRIES; $try++) {
            $code = self::lotCode();

            if ($insert($code) === 1) {
                return $code;
            }
        }

        throw Refused::rejected('FU-13', ['reason' => 'lot_code']);
    }

    public static function lotCode(): string
    {
        return 'LOT-'.Str::upper(Str::random(12));
    }

    /**
     * The ledger rows of the receipt: one per serial, or one per condition; each serial registered and moved in stock (FU-04; C-02).
     *
     * @param  array<string, array{serial: string, damaged: bool}>  $serials
     * @return list<array{lot: int, product: int, scale: int, location: int, condition: string, qty: string, serial?: int}>
     */
    private function entries(CommandContext $context, int $lot, int $product, int $scale, int $location, BigDecimal $usable, BigDecimal $damaged, ReceiptEntry $entry, array $serials): array
    {
        $rows = [];
        $base = ['lot' => $lot, 'product' => $product, 'scale' => $scale, 'location' => $location];

        if ($serials === []) {
            foreach (['USABLE' => $usable, 'UNUSABLE' => $damaged] as $condition => $qty) {
                if ($qty->isPositive()) {
                    $rows[] = [...$base, 'condition' => $condition, 'qty' => (string) $qty];
                }
            }

            return $rows;
        }

        $damagedSerials = count(array_filter($serials, fn (array $serial): bool => $serial['damaged']));

        if (! BigDecimal::of($damagedSerials)->isEqualTo($damaged)) {
            throw Refused::rejected('C-02', ['reason' => 'serial_condition']);
        }

        $line = 0;

        foreach ($serials as $key => $serial) {
            $id = $this->registerSerial($context, $product, $key, $serial['serial'], $lot, $location, $serial['damaged'] ? 'UNUSABLE' : 'USABLE', ++$line);
            $rows[] = [...$base, 'condition' => $serial['damaged'] ? 'UNUSABLE' : 'USABLE', 'qty' => '1', 'serial' => $id];
        }

        return $rows;
    }

    /** FU-04: the serial's row found or born NEVER_RECEIVED, then its conditional transition into stock; in stock already refused (BR-INV-09; C-02; M-05). */
    public function registerSerial(CommandContext $context, int $product, string $key, string $number, int $lot, int $location, string $condition, int $line): int
    {
        $context->connection->table('serial_units')->insertOrIgnore(['product_id' => $product, 'serial_number' => $number, 'serial_key' => $key, 'state' => 'NEVER_RECEIVED', 'command_id' => $context->request->commandId, 'command_line_no' => $line, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
        $moved = DB::table('serial_units')->where('product_id', $product)->where('serial_key', $key)->where('state', '<>', 'IN_STOCK')->update(['state' => 'IN_STOCK', 'current_lot_id' => $lot, 'current_location_id' => $location, 'current_condition' => $condition]);

        if ($moved !== 1) {
            throw Refused::rejected('C-02', ['reason' => 'in_stock', 'serial' => $number]);
        }

        $id = DB::table('serial_units')->where('product_id', $product)->where('serial_key', $key)->value('id');

        return is_numeric($id) ? (int) $id : throw Refused::rejected('C-02', ['reason' => 'serial']);
    }

    /** The line's quantity, then the lot's cost: its receipt targets, or a replacement's basis from its case (DATABASE §6.1; C-57; C-59). */
    private function postCost(CommandContext $context, ReceiptEntry $entry, int $lot, BigDecimal $accepted): void
    {
        if ($entry->kind === ReceiptEntry::REPLACEMENT) {
            $case = (int) $entry->caseId;
            // Over the units still expected before this receipt: read before the guard moves.
            $basis = $this->replacementBasis($case, $entry->lineId, $accepted);
            $this->purchases->move($context, $entry->lineId, ['replacement_received_net_qty_base' => (string) $accepted]);
            $this->cost->basis($context, $lot, 'REPLACEMENT_BASIS', $basis, $entry->businessDate, ['correction_case_id' => $case]);
            $this->cases->post($context, $case, ['replaced_value' => $basis->toString()], self::REPLACEMENT_REVERSED);

            return;
        }

        $this->purchases->move($context, $entry->lineId, ['normal_received_net_qty_base' => (string) $accepted]);
        $this->purchases->posted($context, $entry->lineId, $this->cost->receipt($context, $lot, $entry->businessDate));
    }

    /** The replacement's share of what its case's returned cost still awaits, over the units still expected (DATABASE §5.7; PX-03). */
    private function replacementBasis(int $case, int $lineId, BigDecimal $accepted): Money
    {
        $row = DB::table('correction_cases')->where('id', $case)->first(['returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value']);
        $guard = DB::table('purchase_line_balances as b')->where('purchase_line_id', $lineId)->first(['replacement_expected_qty_base', 'replacement_received_net_qty_base']);

        if ($row === null || $guard === null) {
            return Money::zero();
        }

        $open = Money::of(Row::text($row, 'returned_cost'))->minus(Money::of(Row::text($row, 'refunded_value')))->minus(Money::of(Row::text($row, 'replaced_value')))->minus(Money::of(Row::text($row, 'unrecovered_value')));
        $expected = BigDecimal::of(Row::text($guard, 'replacement_expected_qty_base'))->minus(Row::text($guard, 'replacement_received_net_qty_base'));

        return $open->isPositive() && $expected->isPositive() ? $open->share((string) $accepted, (string) $expected) : Money::zero();
    }

    /** SF-EVIDENCE: the evidence linked to the receipt it proves (IP-16; DATABASE §4.10). */
    private function linkEvidence(CommandContext $context, int $evidence, int $movement, string $role, int $line): void
    {
        $row = DB::table('evidence_documents')->where('id', $evidence)->first(['evidence_scope', 'company_id']);

        if ($row === null) {
            throw Refused::rejected('C-14', ['reason' => 'evidence']);
        }

        DB::table('evidence_links')->insert(['evidence_document_id' => $evidence, 'evidence_scope' => Row::text($row, 'evidence_scope'), 'company_id' => Row::nullable($row, 'company_id'), 'role' => $role, 'stock_movement_id' => $movement, 'command_id' => $context->request->commandId, 'command_line_no' => $line, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
    }

    /** The same-action offer (AX-04; D-UX-12; BR-RSV-03): at most the usable units and the line's unreserved confirmed demand, through TASK-038's service; the quantity for the product guard's one statement. */
    private function reserve(CommandContext $context, object $line, BigDecimal $usable, string $qty): string
    {
        $demand = Row::nullable($line, 'project_line_id');

        if ($demand === null) {
            throw Refused::rejected('BR-RSV-03', ['reason' => 'no_demand']);
        }

        if (BigDecimal::of($qty)->isGreaterThan($usable)) {
            throw Refused::rejected('BR-RSV-03', ['reason' => 'above_usable', 'usable' => (string) $usable]);
        }

        $authority = $context->authority();
        $company = (int) Row::text($line, 'company_id');

        if ($authority === null || ! $authority->allows('stock.reserve', $company)) {
            throw Refused::rejected('AZ-01', ['reason' => 'reserve']);
        }

        return $this->inventory->reserveReceived($context, (int) $demand, $qty, (string) $usable);
    }

    /** The base quantity of an entered one, exact and never rounded (C-09; L-31). */
    public static function base(string $entered, int $ratio, int $scale): BigDecimal
    {
        $base = BigDecimal::of($entered)->multipliedBy($ratio);

        if ($base->isNegative() || ! $base->isEqualTo($base->toScale(max(0, $scale), RoundingMode::Down))) {
            throw Refused::rejected('C-09', ['reason' => 'scale']);
        }

        return $base;
    }

    private static function ratio(int $product, int $unit): int
    {
        $ratio = DB::table('product_units')->where('product_id', $product)->where('unit_id', $unit)->where('is_active', true)->value('ratio_to_base');

        return is_numeric($ratio) ? (int) $ratio : throw Refused::rejected('C-09', ['reason' => 'unit']);
    }

    public static function serialKey(string $serial): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', $serial));
    }

    private static function line(int $lineId): object
    {
        return DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->where('l.id', $lineId)
            ->first(['l.id', 'l.purchase_id', 'l.company_id', 'l.product_id', 'l.quantity_scale', 'l.kind', 'l.delivery_mode', 'l.project_line_id', 'b.ordered_qty_base', 'b.normal_received_net_qty_base', 'b.closed_qty_base', 'b.dropship_confirmed_net_qty_base', 'b.replacement_expected_qty_base', 'b.replacement_received_net_qty_base'])
            ?? throw Refused::rejected('C-14', ['reason' => 'other_line']);
    }

    private static function lot(int $lotId): object
    {
        return DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')->join('purchases as p', 'p.id', '=', 'l.purchase_id')->join('stock_movements as m', 'm.id', '=', 'r.stock_movement_id')->where('i.id', $lotId)
            ->first(['i.id', 'i.product_id', 'i.quantity_scale', 'i.source_company_id', 'i.acquired_qty_base', 'r.purchase_line_id', 'r.receipt_kind', 'r.stock_movement_id', 'r.correction_case_id', 'l.purchase_id', 'p.project_id', 'm.public_id as movement_public_id'])
            ?? throw Refused::rejected('C-14', ['reason' => 'other_lot']);
    }
}
