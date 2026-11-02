<?php

namespace App\Modules\INV\Services;

use App\Modules\INV\Data\StockPicks;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Ubah Kondisi", "Periksa Ulang" and "Pindah Lokasi" (WF-INV-04; AX-06; NX-05; L-13; L-36; DATABASE §5.3): physical pool state, each a movement of no company (CS-05; AZ-08). A condition change moves units between usable and unusable on the same lot and rack, a cut in the same action where usable supply would fall below the reservations; a re-inspection is its explicit reverse, with its reason; a move relocates the same units of one lot and condition, its rows born on first use (FU-01; FU-02), a wrong one corrected by the opposite move. Each net-zero group is held at commit by the deferred trigger (C-07).
 */
final class StockChanges
{
    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly AuditRecorder $audit,
    ) {}

    /** ROUTE-195's "Ubah Kondisi" (`$back` false) and "Periksa Ulang": the movement. */
    public function change(CommandContext $context, StockPicks $entry, bool $back): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $context->require(StockPlaces::locks($entry->productId, $entry->picks, $entry->cuts, $entry->evidence));
        $scale = StockPlaces::ensurePolicy($entry);
        $from = $back ? 'UNUSABLE' : 'USABLE';
        $to = $back ? 'USABLE' : 'UNUSABLE';

        foreach ($entry->picks as $pick) {
            if ($pick['condition'] !== $from) {
                throw Refused::rejected('C-03', ['reason' => 'condition']);
            }
        }

        $reduced = StockPlaces::ensureHeld($entry->picks, $entry->productId, $scale, $entry->serializedSeen);

        if ($entry->unidentified) {
            StockPlaces::ensureConfirmable($entry->picks, $entry->productId);
        }

        $matches = StockPlaces::likelyDuplicates('CONDITION_CHANGE', $entry->picks, $entry->businessDate);
        DuplicateWarning::check('L-49', $matches, $entry->acknowledged, $entry->duplicateReason);
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'CONDITION_CHANGE', 'business_date' => $entry->businessDate, 'reason' => $entry->reason,
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        $reserved = null;

        if ($reduced->isPositive() && (InventoryPosting::shortfall($entry->productId, (string) $reduced)->isPositive() || $entry->cuts !== [])) {
            $reserved = $this->inventory->cut($context, $entry->productId, $entry->cuts, (string) $reduced, $movement, $back ? 'INV.Reinspect' : 'INV.ChangeCondition');
        }

        $rows = [];

        foreach ($entry->picks as $pick) {
            $base = ['lot' => $pick['lot'], 'product' => $entry->productId, 'scale' => $scale, 'location' => $pick['location'], ...($pick['serial'] === null ? [] : ['serial' => $pick['serial']])];
            $rows[] = [...$base, 'condition' => $from, 'qty' => '-'.$pick['qty']];
            $rows[] = [...$base, 'condition' => $to, 'qty' => $pick['qty']];
        }

        $ids = $this->inventory->ledger($context, $movement, 'CONDITION_CHANGE', $rows, $reserved);

        foreach ($rows as $index => $row) {
            // Conditional on what it was just read to be: one serial changed by two commands leaves one success (C-02).
            if (isset($row['serial']) && $row['condition'] === $to && DB::table('serial_units')->where('id', $row['serial'])->where('state', 'IN_STOCK')->where('current_condition', $from)->update(['current_condition' => $to, 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial']);
            }
        }

        StockPlaces::linkEvidence($context, $entry->evidence, $movement);
        $action = $back ? 'INV.UnitsReinspected' : 'INV.ConditionChanged';
        $this->audit->record(
            action: $matches === [] ? $action : $action.'DuplicateConfirmed',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $context->request->actorUserId,
            companyId: null,
            entityType: 'stock_movement',
            entityId: $movement,
            entityPublicId: $publicId,
            reason: $entry->reason,
            commandId: $context->request->commandId,
            change: AuditChange::created(['from' => $from, 'to' => $to, 'quantity' => (string) self::total($entry), 'unidentified' => $entry->unidentified, 'evidence' => count($entry->evidence), 'cuts' => count($entry->cuts), ...($matches === [] ? [] : ['matches' => $matches, 'duplicate_reason' => $entry->duplicateReason])]),
            metadata: ['business_date' => $entry->businessDate],
        );

        return $movement;
    }

    /**
     * NX-05's set for a move: the product, both racks' scopes and lot rows in the condition, each serial.
     *
     * @param  list<int>  $serials
     */
    public static function moveLocks(int $product, int $lot, int $from, int $to, string $condition, array $serials): LockSet
    {
        $set = (new LockSet)->add('stock_product_balances', ['product_id' => $product]);

        foreach ([$from, $to] as $location) {
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $location, 'condition' => $condition]);
            $set->add('stock_lot_balances', ['lot_id' => $lot, 'location_id' => $location, 'condition' => $condition]);
        }

        foreach ($serials === [] ? [] : DB::table('serial_units')->whereIn('id', $serials)->orderBy('id')->pluck('serial_key') as $key) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => is_string($key) ? $key : '']);
        }

        return $set;
    }

    /**
     * ROUTE-190: the same units leaving one rack and arriving at another (L-36); the movement. CALC-01 unchanged: no reservation, cost or company is touched.
     *
     * @param  list<int>  $serials
     */
    public function move(CommandContext $context, int $product, int $lot, int $from, int $to, string $condition, string $quantity, array $serials, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require(self::moveLocks($product, $lot, $from, $to, $condition, $serials));
        $policy = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']) ?? throw Refused::rejected('C-14', ['reason' => 'product']);
        $scale = (int) Row::text($policy, 'quantity_scale');
        $serialized = (bool) Row::text($policy, 'is_serialized');

        if ($serialized !== $serializedSeen || $scale !== $scaleSeen) {
            throw Refused::conflict('ST-09', ['serialized' => $serialized, 'scale' => $scale]);
        }

        if ($from === $to) {
            throw Refused::rejected('C-07', ['reason' => 'same_rack']);
        }

        if (! DB::table('locations')->where('id', $to)->where('is_active', true)->exists()) {
            throw Refused::rejected('C-14', ['reason' => 'rack']);
        }

        $qty = ReceivingRecords::base($quantity, 1, $scale);

        if ($serialized && ! $qty->isEqualTo(count($serials))) {
            throw Refused::rejected('C-02', ['reason' => 'serial']);
        }

        $picks = $serialized
            ? array_map(fn (int $serial): array => ['lot' => $lot, 'location' => $from, 'condition' => $condition, 'qty' => '1', 'serial' => $serial], $serials)
            : [['lot' => $lot, 'location' => $from, 'condition' => $condition, 'qty' => (string) $qty, 'serial' => null]];
        StockPlaces::ensureHeld($picks, $product, $scale, $serialized);
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'LOCATION_MOVE', 'business_date' => $businessDate,
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        $rows = [];

        foreach ($picks as $pick) {
            $base = ['lot' => $lot, 'product' => $product, 'scale' => $scale, 'condition' => $condition, ...($pick['serial'] === null ? [] : ['serial' => $pick['serial']])];
            $rows[] = [...$base, 'location' => $from, 'qty' => '-'.$pick['qty']];
            $rows[] = [...$base, 'location' => $to, 'qty' => $pick['qty']];
        }

        $ids = $this->inventory->ledger($context, $movement, 'LOCATION_MOVE', $rows);

        foreach ($rows as $index => $row) {
            if (isset($row['serial']) && $row['location'] === $to && DB::table('serial_units')->where('id', $row['serial'])->where('state', 'IN_STOCK')->where('current_location_id', $from)->update(['current_location_id' => $to, 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial']);
            }
        }

        $this->audit->record(
            action: 'INV.LocationMoved',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: null,
            entityType: 'stock_movement',
            entityId: $movement,
            entityPublicId: $publicId,
            commandId: $context->request->commandId,
            change: AuditChange::created(['quantity' => (string) $qty, 'condition' => $condition]),
            metadata: ['business_date' => $businessDate],
        );

        return $movement;
    }

    private static function total(StockPicks $entry): BigDecimal
    {
        return array_reduce($entry->picks, fn (BigDecimal $sum, array $pick): BigDecimal => $sum->plus($pick['qty']), BigDecimal::zero());
    }
}
