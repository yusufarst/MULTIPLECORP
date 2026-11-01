<?php

namespace App\Modules\INV\Services;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\INV\Data\StockFigures;
use App\Modules\INV\Data\StockPicks;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * What a condition change, a move and a loss check alike under their locks (C-02; C-03; C-09; L-42; ST-09; BD-13): the units named are held where the command says, each serial in stock at its lot, rack and condition, the quantities at the product's scale, an unidentified lot confirmed by the actor and only where the candidates are one company's.
 */
final class StockPlaces
{
    /**
     * The product, each place in either condition, each serial, the cut and the evidence.
     *
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @param  array<int, string>  $cuts
     * @param  list<int>  $evidence
     */
    public static function locks(int $product, array $picks, array $cuts, array $evidence): LockSet
    {
        $set = (new LockSet)->add('stock_product_balances', ['product_id' => $product]);
        $serials = [];

        foreach ($picks as $pick) {
            foreach (['USABLE', 'UNUSABLE'] as $condition) {
                $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $pick['location'], 'condition' => $condition]);
                $set->add('stock_lot_balances', ['lot_id' => $pick['lot'], 'location_id' => $pick['location'], 'condition' => $condition]);
            }

            if ($pick['serial'] !== null) {
                $serials[] = $pick['serial'];
            }
        }

        foreach ($serials === [] ? [] : DB::table('serial_units')->whereIn('id', $serials)->orderBy('id')->pluck('serial_key') as $key) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => is_string($key) ? $key : '']);
        }

        foreach ($evidence as $id) {
            $set = $set->union(EvidenceGuard::locks($id));
        }

        return $cuts === [] ? $set : $set->union(InventoryPosting::cutLocks($product, array_keys($cuts)));
    }

    /** ST-09: the policy has not changed since the page read it (L-35); the product's scale. */
    public static function ensurePolicy(StockPicks $entry): int
    {
        $product = DB::table('products')->where('id', $entry->productId)->first(['is_serialized', 'quantity_scale']);

        if ($product === null) {
            throw Refused::rejected('C-14', ['reason' => 'product']);
        }

        if ((bool) Row::text($product, 'is_serialized') !== $entry->serializedSeen || (int) Row::text($product, 'quantity_scale') !== $entry->scaleSeen) {
            throw Refused::conflict('ST-09', ['serialized' => (bool) Row::text($product, 'is_serialized'), 'scale' => (int) Row::text($product, 'quantity_scale')]);
        }

        return (int) Row::text($product, 'quantity_scale');
    }

    /**
     * Each pick read under its lock; what lowers usable supply.
     *
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     */
    public static function ensureHeld(array $picks, int $product, int $scale, bool $serialized): BigDecimal
    {
        if ($picks === []) {
            throw Refused::rejected('C-03', ['reason' => 'nothing']);
        }

        $places = [];
        $named = [];
        $usable = BigDecimal::zero();

        foreach ($picks as $pick) {
            $qty = ReceivingRecords::base($pick['qty'], 1, $scale);
            $lot = DB::table('inventory_lots')->where('id', $pick['lot'])->where('product_id', $product)->exists();

            if (! $lot || ! $qty->isPositive()) {
                throw Refused::rejected('C-14', ['reason' => 'lot']);
            }

            $place = "{$pick['lot']}|{$pick['location']}|{$pick['condition']}";
            $places[$place] = ($places[$place] ?? BigDecimal::zero())->plus($qty);
            $held = DB::table('stock_lot_balances as b')->where('lot_id', $pick['lot'])->where('location_id', $pick['location'])->where('condition', $pick['condition'])->value('qty');

            if (! is_string($held) || $places[$place]->isGreaterThan($held)) {
                throw Refused::rejected('C-03', ['reason' => 'lot_short', 'available' => is_string($held) ? StockFigures::plain($held) : '0']);
            }

            if ($serialized !== ($pick['serial'] !== null)) {
                throw Refused::rejected('C-02', ['reason' => 'serial']);
            }

            if ($pick['serial'] !== null) {
                $present = DB::table('serial_units')->where('id', $pick['serial'])->where('product_id', $product)->where('state', 'IN_STOCK')->where('current_lot_id', $pick['lot'])->where('current_location_id', $pick['location'])->where('current_condition', $pick['condition'])->exists();

                if (! $present || ! $qty->isEqualTo(1) || isset($named[$pick['serial']])) {
                    throw Refused::rejected('C-02', ['reason' => 'serial']);
                }

                $named[$pick['serial']] = true;
            }

            $usable = $pick['condition'] === 'USABLE' ? $usable->plus($qty) : $usable;
        }

        return $usable;
    }

    /**
     * L-42: unidentified fungible units are the actor's to confirm lot by lot, and only where every candidate lot of the scope is one company's; across companies the Owner decides through the pending case (DIR-026; TASK-043), never an automatic rule.
     *
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     */
    public static function ensureConfirmable(array $picks, int $product): void
    {
        foreach ($picks as $pick) {
            $companies = DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->where('b.product_id', $product)->where('b.location_id', $pick['location'])->where('b.condition', $pick['condition'])->where('b.qty', '>', 0)->distinct()->count('i.source_company_id');

            if ($companies > 1) {
                throw Refused::rejected('L-42', ['reason' => 'several_companies']);
            }
        }
    }

    /**
     * BD-13's likely duplicates (L-49): a fungible event of the same case, otherwise the same type, lot and rack, with the same quantity and business date, not given back since. A serial has its own identity (C-02).
     *
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @return list<string> the movements' public identifiers
     */
    public static function likelyDuplicates(string $type, array $picks, string $businessDate, ?int $case = null): array
    {
        $found = [];
        $fungible = array_filter($picks, fn (array $pick): bool => $pick['serial'] === null);

        if ($fungible === []) {
            return [];
        }

        if ($case !== null) {
            $total = array_reduce($fungible, fn (BigDecimal $sum, array $pick): BigDecimal => $sum->plus($pick['qty']), BigDecimal::zero());

            foreach (DB::select(<<<'SQL'
                select m.public_id from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id join stock_movements m on m.id = c.stock_movement_id
                where c.correction_case_id = ? and c.kind = 'LOSS' and m.business_date = ?::date
                group by m.public_id having sum(b.qty_base) = ?::numeric and sum(b.qty_base - b.restored_qty_base) > 0
                order by m.public_id
                SQL, [$case, $businessDate, (string) $total]) as $row) {
                if (is_object($row)) {
                    $found[Row::text($row, 'public_id')] = true;
                }
            }

            return array_keys($found);
        }

        $places = [];

        foreach ($fungible as $pick) {
            $place = "{$pick['lot']}|{$pick['location']}|{$pick['condition']}";
            $places[$place] = ['pick' => $pick, 'qty' => ($places[$place]['qty'] ?? BigDecimal::zero())->plus($pick['qty'])];
        }

        foreach ($places as $place) {
            foreach (DB::select(<<<'SQL'
                select m.public_id from stock_movement_entries e join stock_movements m on m.id = e.stock_movement_id
                left join stock_consumptions c on c.id = e.stock_consumption_id left join stock_consumption_balances b on b.stock_consumption_id = c.id
                where e.movement_type = ? and e.lot_id = ? and e.location_id = ? and e.condition = ? and e.qty_delta_base < 0 and m.business_date = ?::date and c.correction_case_id is null
                    and not exists (select 1 from stock_movements r where r.reverses_movement_id = m.id)
                    and (e.stock_consumption_id is null or b.qty_base - b.restored_qty_base > 0)
                group by m.public_id having -sum(e.qty_delta_base) = ?::numeric
                order by m.public_id
                SQL, [$type, $place['pick']['lot'], $place['pick']['location'], $place['pick']['condition'], $businessDate, (string) $place['qty']]) as $row) {
                if (is_object($row)) {
                    $found[Row::text($row, 'public_id')] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * SF-EVIDENCE: the proofs linked to the movement they prove, at their own scope (IP-16; DATABASE §4.10).
     *
     * @param  list<int>  $evidence
     */
    public static function linkEvidence(CommandContext $context, array $evidence, int $movement): void
    {
        foreach (array_values(array_unique($evidence)) as $number => $id) {
            $row = DB::table('evidence_documents')->where('id', $id)->first(['evidence_scope', 'company_id']) ?? throw Refused::rejected('C-14', ['reason' => 'evidence']);
            DB::table('evidence_links')->insert(['evidence_document_id' => $id, 'evidence_scope' => Row::text($row, 'evidence_scope'), 'company_id' => Row::nullable($row, 'company_id'), 'role' => 'PRIMARY_PROOF', 'stock_movement_id' => $movement, 'command_id' => $context->request->commandId, 'command_line_no' => $number + 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
        }
    }

    /**
     * A replayed refusal's current state (CI-05): the likely duplicates, what the first short place holds, what a cut must still cover.
     *
     * @return array<string, mixed>|null
     */
    public static function refusalState(?string $code, StockPicks $entry, string $type): ?array
    {
        if ($code === 'L-49') {
            return ['matches' => self::likelyDuplicates($type, $entry->picks, $entry->businessDate)];
        }

        if ($code === 'C-06') {
            $usable = array_reduce(array_filter($entry->picks, fn (array $pick): bool => $pick['condition'] === 'USABLE'), fn (BigDecimal $sum, array $pick): BigDecimal => $sum->plus($pick['qty']), BigDecimal::zero());

            return ['shortfall' => StockFigures::plain((string) InventoryPosting::shortfall($entry->productId, (string) $usable))];
        }

        if ($code === 'AZ-08') {
            return ['reason' => 'bearer'];
        }

        if ($code !== 'C-03') {
            return null;
        }

        foreach ($entry->picks as $pick) {
            $held = DB::table('stock_lot_balances as b')->where('lot_id', $pick['lot'])->where('location_id', $pick['location'])->where('condition', $pick['condition'])->value('qty');

            if (! is_string($held) || BigDecimal::of($pick['qty'])->isGreaterThan($held)) {
                return ['available' => is_string($held) ? StockFigures::plain($held) : '0'];
            }
        }

        return null;
    }
}
