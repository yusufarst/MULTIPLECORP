<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * A loss's units given back by an event of its own (AX-06; AX-07; AX-37; SF-RESTORE; L-31; C-27): LOSS_REVERSAL restorations citing the consumptions they restore, capped by them, each with its proportional contra, the allocation reversed with it. The caller's movement carries the rows.
 */
final class LossRestorations
{
    public function __construct(
        private readonly CostPosting $cost,
        private readonly Revalidation $revalidation,
    ) {}

    /**
     * The consumptions, their cost guards and the projects that bear them.
     *
     * @param  list<int>  $consumptions
     */
    public static function locks(array $consumptions): LockSet
    {
        $set = new LockSet;

        foreach ($consumptions as $id) {
            $set = $set->union(CostPosting::consumptionLocks($id));
            $set->add('stock_consumption_balances', ['stock_consumption_id' => $id]);
            $project = DB::table('stock_consumptions')->where('id', $id)->value('bearer_project_id');

            if (is_numeric($project)) {
                $set->add('projects', ['id' => (int) $project]);
            }
        }

        return $set;
    }

    /**
     * What a consumption still may give back, with where it left and whom it charged.
     *
     * @return array{open: BigDecimal, lot: int, product: int, scale: int, location: int, condition: string, serial: ?int, source: int, bearer: int, project: ?int, kind: string, line: ?int}|null
     */
    public static function of(int $consumption): ?array
    {
        $row = DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')
            ->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')->where('c.id', $consumption)->whereIn('c.kind', ['LOSS', 'UNATTRIBUTED_LOSS'])
            ->first(['b.qty_base', 'b.restored_qty_base', 'b.reclassified_qty_base', 'b.substituted_qty_base', 'c.lot_id', 'c.product_id', 'c.quantity_scale', 'c.source_company_id', 'c.bearer_company_id', 'c.bearer_project_id', 'c.kind', 'c.unattributed_resolution_line_id', 'e.location_id', 'e.condition', 'e.serial_unit_id']);

        if ($row === null) {
            return null;
        }

        $project = Row::nullable($row, 'bearer_project_id');
        $serial = Row::nullable($row, 'serial_unit_id');
        $line = Row::nullable($row, 'unattributed_resolution_line_id');

        return [
            'open' => BigDecimal::of(Row::text($row, 'qty_base'))->minus(Row::text($row, 'restored_qty_base'))->minus(Row::text($row, 'reclassified_qty_base'))->minus(Row::text($row, 'substituted_qty_base')),
            'lot' => (int) Row::text($row, 'lot_id'), 'product' => (int) Row::text($row, 'product_id'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'location' => (int) Row::text($row, 'location_id'), 'condition' => Row::text($row, 'condition'),
            'serial' => $serial === null ? null : (int) $serial, 'source' => (int) Row::text($row, 'source_company_id'), 'bearer' => (int) Row::text($row, 'bearer_company_id'), 'project' => $project === null ? null : (int) $project,
            'kind' => Row::text($row, 'kind'), 'line' => $line === null ? null : (int) $line,
        ];
    }

    /** Whether the open fungible losses a surplus of this scope could reverse belong to several companies: only evidence then says whose units were found (WF-INV-05; L-15). */
    public static function acrossCompanies(int $product, int $location, string $condition): bool
    {
        return DB::table('stock_consumptions as c')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'c.id')->join('stock_movement_entries as e', 'e.stock_consumption_id', '=', 'c.id')
            ->where('c.product_id', $product)->where('e.location_id', $location)->where('e.condition', $condition)->whereNull('e.serial_unit_id')->whereIn('c.kind', ['LOSS', 'UNATTRIBUTED_LOSS'])
            ->whereRaw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base > 0')->distinct()->count('c.source_company_id') > 1;
    }

    /**
     * Restorations and contras for the quantities; the ledger rows that bring the units back. Never above what is open (C-27).
     *
     * @param  list<array{consumption: int, qty: string}>  $items
     * @return list<array{lot: int, product: int, scale: int, location: int, condition: string, qty: string, restoration: int, serial?: int}>
     */
    public function restore(CommandContext $context, int $movement, array $items, string $businessDate, int $firstLine = 1): array
    {
        $rows = [];
        $line = $firstLine;

        foreach ($items as $item) {
            $found = self::of($item['consumption']) ?? throw Refused::rejected('C-14', ['reason' => 'consumption']);
            $qty = BigDecimal::of($item['qty']);

            if (! $qty->isPositive() || $qty->isGreaterThan($found['open'])) {
                throw Refused::rejected('C-27', ['reason' => 'above_open', 'open' => (string) $found['open']]);
            }

            // A unit comes back with its identity: once the product is serialized, a loss recorded without a serial adds no unit a serial does not name (L-35; L-48; `DIR-101` F2).
            if ($found['serial'] === null && (bool) DB::table('products')->where('id', $found['product'])->value('is_serialized')) {
                throw Refused::rejected('C-02', ['reason' => 'serial_identity']);
            }

            $unit = DB::table('products')->where('id', $found['product'])->value('base_unit_id');
            $restoration = (int) DB::table('stock_restorations')->insertGetId([
                'stock_movement_id' => $movement, 'stock_consumption_id' => $item['consumption'], 'source_company_id' => $found['source'], 'product_id' => $found['product'], 'quantity_scale' => $found['scale'],
                'entered_qty' => (string) $qty, 'entered_unit_id' => is_numeric($unit) ? (int) $unit : 0, 'ratio_to_base' => 1, 'qty_base' => (string) $qty, 'kind' => 'LOSS_REVERSAL', 'returned_condition' => $found['condition'],
                'command_id' => $context->request->commandId, 'command_line_no' => $line++, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
            ]);
            $moved = $this->cost->restore($context, $restoration, $businessDate);

            if ($moved !== null && $found['project'] !== null) {
                $this->revalidation->revalidate($context, $found['project'], is_string($public = DB::table('stock_movements')->where('id', $movement)->value('public_id')) ? $public : '');
            }

            GuardUpdate::apply($context->connection, 'stock_consumption_balances', [['key' => ['stock_consumption_id' => $item['consumption']], 'deltas' => ['restored_qty_base' => (string) $qty]]]);
            $rows[] = ['lot' => $found['lot'], 'product' => $found['product'], 'scale' => $found['scale'], 'location' => $found['location'], 'condition' => $found['condition'], 'qty' => (string) $qty, 'restoration' => $restoration, ...($found['serial'] === null ? [] : ['serial' => $found['serial']])];
        }

        return $rows;
    }

    /**
     * Serials given back by their rows, now in stock where they left; a serial already in stock is refused (C-02).
     *
     * @param  list<array{lot?: ?int, location: int, condition: string, serial?: int}>  $rows
     * @param  list<int>  $ids
     */
    public static function serialsBack(array $rows, array $ids): void
    {
        foreach ($rows as $index => $row) {
            if (isset($row['serial']) && DB::table('serial_units')->where('id', $row['serial'])->where('state', '<>', 'IN_STOCK')->update(['state' => 'IN_STOCK', 'current_lot_id' => $row['lot'] ?? null, 'current_location_id' => $row['location'], 'current_condition' => $row['condition'], 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial_reversal']);
            }
        }
    }
}
