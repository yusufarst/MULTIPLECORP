<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Data\ReceiptPosted;
use App\Modules\CST\Services\CostPosting;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * INV's part of a drop-ship confirmation line (DATABASE §5.7; BR-INV-11; FS-05): its consumption guard born with it and
 * its serial DROPSHIPPED — never IN_STOCK, no ledger entry —; its restorations, each capped by that guard (C-27): a
 * confirmation reversal (CM-27) and a return to the supplier (CM-15) movement-less, the serial back to NEVER_RECEIVED or
 * RETURNED_TO_SUPPLIER; a return to the warehouse (CM-14) the one drop-ship event with a ledger entry, its
 * DROPSHIP_RETURN_RECEIPT movement into a new DROPSHIP_RETURN lot at a rack whose cost is exactly the HPP contra
 * (C-08). The cost is CST's (`CostPosting::dropshipRestored`).
 */
final class DropshipStock
{
    public const KINDS = ['DROPSHIP_RETURN', 'DROPSHIP_SUPPLIER_RETURN', 'DROPSHIP_CONFIRMATION_REVERSAL'];

    /** The states a drop-shipped serial may come from: never in stock here, or back with its supplier (BD-18; C-02). */
    private const SHIPPABLE = ['NEVER_RECEIVED', 'RETURNED_TO_SUPPLIER'];

    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
    ) {}

    /**
     * LK-24 for the lines' consumption guards (LK-17 serials, LK-13 the product's, LK-14 and LK-15 a rack's scopes of a return to the warehouse).
     *
     * @param  list<int>  $dropshipLineIds
     * @param  list<string>  $serials  the serial numbers as entered
     */
    public static function locks(int $productId, array $dropshipLineIds, array $serials, ?int $locationId): LockSet
    {
        $set = new LockSet;

        foreach (array_unique($dropshipLineIds) as $line) {
            $set->add('stock_consumption_balances', ['dropship_confirmation_line_id' => $line]);
        }

        foreach ($serials as $serial) {
            $set->add('serial_units', ['product_id' => $productId, 'serial_key' => ReceivingRecords::serialKey($serial)]);
        }

        if ($locationId !== null) {
            $set->add('stock_product_balances', ['product_id' => $productId]);

            foreach (['USABLE', 'UNUSABLE'] as $condition) {
                $set->add('stock_scope_balances', ['product_id' => $productId, 'location_id' => $locationId, 'condition' => $condition]);
            }
        }

        return $set;
    }

    /** FU-04: the serial's row found or born NEVER_RECEIVED, then its conditional transition to DROPSHIPPED; in stock or already drop-shipped refused (BD-18; C-02). */
    public function ship(CommandContext $context, int $productId, string $number, int $lineNo): int
    {
        $key = ReceivingRecords::serialKey($number);
        $context->connection->table('serial_units')->insertOrIgnore(['product_id' => $productId, 'serial_number' => trim($number), 'serial_key' => $key, 'state' => 'NEVER_RECEIVED', 'command_id' => $context->request->commandId, 'command_line_no' => $lineNo, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
        $moved = DB::table('serial_units')->where('product_id', $productId)->where('serial_key', $key)->whereIn('state', self::SHIPPABLE)->update(['state' => 'DROPSHIPPED']);

        if ($moved !== 1) {
            throw Refused::rejected('C-02', ['reason' => 'shipped', 'serial' => trim($number)]);
        }

        $id = DB::table('serial_units')->where('product_id', $productId)->where('serial_key', $key)->value('id');

        return is_numeric($id) ? (int) $id : throw Refused::rejected('C-02', ['reason' => 'serial']);
    }

    /**
     * The confirmation lines' consumption guards, born in one statement with their confirmed quantities (C-27).
     *
     * @param  array<int, string>  $quantities  drop-ship line => base quantity
     */
    public function confirmed(CommandContext $context, array $quantities): void
    {
        $now = $context->now();

        $context->connection->table('stock_consumption_balances')->insert(array_map(fn (int $line, string $qty): array => ['dropship_confirmation_line_id' => $line, 'qty_base' => $qty, 'recorded_at' => $now], array_keys($quantities), array_values($quantities)));
    }

    /** What of a confirmation line is still out with the client or confirmed: its guard's quantity less what came back. */
    public static function open(int $dropshipLineId): BigDecimal
    {
        $row = DB::table('stock_consumption_balances as b')->where('b.dropship_confirmation_line_id', $dropshipLineId)->first(['b.qty_base', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base']);

        return $row === null ? BigDecimal::zero() : BigDecimal::of(Row::text($row, 'qty_base'))->minus(Row::text($row, 'restored_qty_base'))->minus(Row::text($row, 'reclassified_qty_base'))->minus(Row::text($row, 'substituted_qty_base'));
    }

    /**
     * One restoration of a confirmation line, entered in the product's base unit and citing the line's unit snapshot
     * (L-31): never above the line's open quantity (C-27); its cost through CST; for a return to the warehouse the
     * receipt movement and the new lot at the rack first, so the lot's basis is the contra; the serial moved; the
     * guard's restored quantity raised.
     *
     * @param  array{kind: string, qty: string, after_delivery: ?bool, case: ?int, location: ?int, condition: ?string, project: int, date: string, reason: string, line_no: int}  $entry
     * @return array{restoration: int, posted: ReceiptPosted, lot: ?int, movement: ?int}
     */
    public function restore(CommandContext $context, int $dropshipLineId, array $entry): array
    {
        $line = DB::table('dropship_confirmation_lines as d')->join('products as p', 'p.id', '=', 'd.product_id')->where('d.id', $dropshipLineId)
            ->first(['d.company_id', 'd.product_id', 'd.quantity_scale', 'd.entered_unit_id', 'd.ratio_to_base', 'd.serial_unit_id', 'p.base_unit_id'])
            ?? throw Refused::rejected('C-14', ['reason' => 'line']);
        $kind = $entry['kind'];
        $qty = BigDecimal::of($entry['qty']);
        $open = self::open($dropshipLineId);

        if (! in_array($kind, self::KINDS, true) || ! $qty->isPositive() || $qty->isGreaterThan($open)) {
            throw Refused::rejected('C-27', ['reason' => 'above_open', 'open' => (string) $open]);
        }

        $warehouse = $kind === 'DROPSHIP_RETURN';

        if ($warehouse && ($entry['location'] === null || ! in_array($entry['condition'], ['USABLE', 'UNUSABLE'], true))) {
            throw Refused::rejected('WF-INV-01', ['reason' => 'no_rack']);
        }

        if ($warehouse && ! DB::table('locations')->where('id', $entry['location'])->where('is_active', true)->exists()) {
            throw Refused::rejected('C-14', ['reason' => 'rack']);
        }

        $company = (int) Row::text($line, 'company_id');
        $product = (int) Row::text($line, 'product_id');
        $scale = max(0, (int) Row::text($line, 'quantity_scale'));
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $movement = null;

        if ($warehouse) {
            $movement = (int) DB::table('stock_movements')->insertGetId([
                'public_id' => (string) Str::uuid7(), 'movement_type' => 'DROPSHIP_RETURN_RECEIPT', 'business_date' => $entry['date'], 'reason' => $entry['reason'], 'company_id' => $company, 'project_id' => $entry['project'],
                'correction_case_id' => $entry['case'], 'command_id' => $commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
        }

        $restoration = (int) DB::table('stock_restorations')->insertGetId([
            'stock_movement_id' => $movement, 'stock_consumption_id' => null, 'dropship_confirmation_line_id' => $dropshipLineId, 'source_company_id' => $company, 'product_id' => $product, 'quantity_scale' => $scale,
            'cited_unit_id' => (int) Row::text($line, 'entered_unit_id'), 'cited_ratio_to_base' => (int) Row::text($line, 'ratio_to_base'),
            'entered_qty' => (string) $qty, 'entered_unit_id' => (int) Row::text($line, 'base_unit_id'), 'ratio_to_base' => 1, 'qty_base' => (string) $qty,
            'kind' => $kind, 'after_delivery' => $kind === 'DROPSHIP_CONFIRMATION_REVERSAL' ? null : (bool) $entry['after_delivery'], 'returned_condition' => $warehouse ? $entry['condition'] : null,
            'correction_case_id' => $entry['case'], 'case_company_id' => $entry['case'] === null ? null : $company,
            'command_id' => $commandId, 'command_line_no' => $entry['line_no'], 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $lot = null;
        $ids = [];

        if ($warehouse) {
            $lot = $this->lot($context, $restoration, $product, $scale, $company, (string) $qty, (int) Row::text($line, 'base_unit_id'), $entry['date']);
            $ids = $this->inventory->ledger($context, (int) $movement, 'DROPSHIP_RETURN_RECEIPT', [[
                'lot' => $lot, 'product' => $product, 'scale' => $scale, 'location' => (int) $entry['location'], 'condition' => (string) $entry['condition'], 'qty' => (string) $qty, 'restoration' => $restoration,
                ...(Row::nullable($line, 'serial_unit_id') === null ? [] : ['serial' => (int) Row::text($line, 'serial_unit_id')]),
            ]]);
        }

        $posted = $this->cost->dropshipRestored($context, $restoration, $entry['date']);

        // The return lot's cost guard is this command's own: a later line's cascade over the confirmation's returns finds it held (SQ-20).
        if ($lot !== null) {
            $context->created((new LockSet)->add('lot_cost_balances', ['lot_id' => $lot]));
        }
        GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['dropship_confirmation_line_id' => $dropshipLineId], 'deltas' => ['restored_qty_base' => (string) $qty]]]);
        $serial = Row::nullable($line, 'serial_unit_id');

        if ($serial !== null) {
            $state = match ($kind) {
                'DROPSHIP_RETURN' => ['state' => 'IN_STOCK', 'current_lot_id' => $lot, 'current_location_id' => $entry['location'], 'current_condition' => $entry['condition'], 'last_entry_id' => $ids[0] ?? null],
                'DROPSHIP_SUPPLIER_RETURN' => ['state' => 'RETURNED_TO_SUPPLIER'],
                default => ['state' => 'NEVER_RECEIVED'],
            };

            if (DB::table('serial_units')->where('id', (int) $serial)->where('state', 'DROPSHIPPED')->update($state) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial_return']);
            }
        }

        return ['restoration' => $restoration, 'posted' => $posted, 'lot' => $lot, 'movement' => $movement];
    }

    /** FU-13: the DROPSHIP_RETURN lot keyed to its restoration, its code generated (C-08). */
    private function lot(CommandContext $context, int $restoration, int $product, int $scale, int $company, string $qty, int $unit, string $businessDate): int
    {
        $code = ReceivingRecords::withLotCode(fn (string $code): int => $context->connection->table('inventory_lots')->insertOrIgnore([
            'lot_code' => $code, 'product_id' => $product, 'quantity_scale' => $scale, 'source_company_id' => $company, 'acquisition_kind' => 'DROPSHIP_RETURN', 'restoration_id' => $restoration,
            'entered_qty' => $qty, 'entered_unit_id' => $unit, 'ratio_to_base' => 1, 'acquired_qty_base' => $qty, 'business_date' => $businessDate,
            'command_id' => $context->request->commandId, 'command_line_no' => InventoryPosting::lineNo('inventory_lots', $context), 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]));
        $id = DB::table('inventory_lots')->where('lot_code', $code)->value('id');

        return is_numeric($id) ? (int) $id : throw Refused::rejected('FU-13', []);
    }
}
