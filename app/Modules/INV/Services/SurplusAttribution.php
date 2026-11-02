<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Tetapkan Surplus" (ROUTE-197; AX-07; OD-08; BR-INV-04; C-08; C-34; C-35): the Owner's attribution of an open finding to an owning company with an explicit flagged cost basis. The scope's pending losses are found first and the identified losses reversed; what remains creates one flagged lot, once, and moves the scope, so a count started before it goes stale. A finding no longer OPEN is CONFLICT.
 */
final class SurplusAttribution
{
    public function __construct(
        private readonly UnattributedCases $cases,
        private readonly LossRestorations $restorations,
        private readonly InventoryPosting $inventory,
        private readonly ReceivingRecords $receiving,
        private readonly CostPosting $cost,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * AX-07's set: the finding, its scope and pending cases, the losses reversed with their bearers, the unexpected serials.
     *
     * @param  list<array{consumption: int, qty: string}>  $restored
     */
    public static function locks(int $finding, array $restored): LockSet
    {
        $set = (new LockSet)->add('stock_count_findings', ['id' => $finding]);
        $row = DB::table('stock_count_findings')->where('id', $finding)->first(['product_id', 'location_id', 'stock_count_id']);

        if ($row === null) {
            return $set;
        }

        $product = (int) Row::text($row, 'product_id');
        $set = $set->union(UnattributedCases::scopeLocks($product, (int) Row::text($row, 'location_id')));
        $consumptions = array_map(fn (array $item): int => $item['consumption'], $restored);

        foreach ($consumptions as $consumption) {
            $found = LossRestorations::of($consumption);

            if ($found !== null) {
                foreach (['USABLE', 'UNUSABLE'] as $condition) {
                    $set->add('stock_lot_balances', ['lot_id' => $found['lot'], 'location_id' => $found['location'], 'condition' => $condition]);
                }
            }
        }

        foreach (self::seenSerials((int) Row::text($row, 'stock_count_id')) as $text) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => ReceivingRecords::serialKey($text)]);
        }

        return $set->union(LossRestorations::locks($consumptions));
    }

    /**
     * ROUTE-197; the finding.
     *
     * @param  list<array{consumption: int, qty: string}>  $restored
     */
    public function attribute(CommandContext $context, int $findingId, int $company, Money $basis, array $restored, string $reason, string $businessDate, bool $prefilled, bool $serializedSeen, int $scaleSeen): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $context->require(self::locks($findingId, $restored));
        $finding = DB::table('stock_count_findings')->where('id', $findingId)->first();

        if ($finding === null) {
            throw Refused::rejected('C-14', ['reason' => 'finding']);
        }

        if (Row::text($finding, 'state') !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => Row::text($finding, 'state')]);
        }

        $product = (int) Row::text($finding, 'product_id');
        $scale = (int) Row::text($finding, 'quantity_scale');
        $location = (int) Row::text($finding, 'location_id');
        $condition = Row::text($finding, 'condition');
        $policy = DB::table('products')->where('id', $product)->first(['is_serialized', 'quantity_scale']);

        if ($policy === null || (bool) Row::text($policy, 'is_serialized') !== $serializedSeen || (int) Row::text($policy, 'quantity_scale') !== $scaleSeen) {
            throw Refused::conflict('ST-09', ['serialized' => $policy !== null && (bool) Row::text($policy, 'is_serialized')]);
        }

        $left = BigDecimal::of(Row::text($finding, 'surplus_qty_base'));

        foreach (DB::table('unattributed_loss_cases')->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->where('case_kind', 'LOSS')->where('pending_qty_base', '>', 0)->orderBy('id')->get(['id', 'pending_qty_base']) as $case) {
            $take = BigDecimal::min($left, Row::text($case, 'pending_qty_base'));

            if ($take->isPositive()) {
                $this->cases->found($context, (int) Row::text($case, 'id'), $take, $reason, $businessDate, null);
                $left = $left->minus($take);
            }
        }

        $identified = array_reduce($restored, fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['qty']), BigDecimal::zero());

        if ($identified->isGreaterThan($left)) {
            throw Refused::rejected('C-35', ['reason' => 'above_finding']);
        }

        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $now = $context->now();

        if ($restored !== []) {
            $back = (int) DB::table('stock_movements')->insertGetId([
                'public_id' => (string) Str::uuid7(), 'movement_type' => 'RESTORATION', 'business_date' => $businessDate, 'reason' => $reason,
                'command_id' => $commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $rows = $this->restorations->restore($context, $back, $restored, $businessDate);
            LossRestorations::serialsBack($rows, $this->inventory->ledger($context, $back, 'RESTORATION', $rows));
            $left = $left->minus($identified);
        }

        $attributed = $left->isPositive();

        if (DB::table('stock_count_findings')->where('id', $findingId)->where('state', 'OPEN')->update([
            'state' => $attributed ? 'ATTRIBUTED' : 'REVERSED_LOSS', 'attributed_company_id' => $attributed ? $company : null, 'flagged_cost_basis' => $attributed ? $basis->toString() : null, 'decided_by' => $actor, 'reason' => $reason, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $now,
        ]) !== 1) {
            throw Refused::conflict('ST-02', ['state' => 'changed']);
        }

        if ($attributed) {
            $this->flaggedLot($context, $findingId, $company, $basis, $product, $scale, $location, $condition, $left, (int) Row::text($finding, 'stock_count_id'), $serializedSeen, $reason, $businessDate);
        }

        $this->audit->record(
            action: 'INV.SurplusAttributed', authorityClass: AuthorityClass::OwnerOnly, actorUserId: $actor, companyId: $attributed ? $company : null, entityType: 'stock_count_finding', entityId: $findingId, reason: $reason, commandId: $commandId,
            change: AuditChange::updated(['state' => 'OPEN'], ['state' => $attributed ? 'ATTRIBUTED' : 'REVERSED_LOSS', 'attributed' => (string) $left, 'flagged_cost_basis' => $attributed ? $basis->toString() : null]), metadata: ['business_date' => $businessDate],
        );

        return $findingId;
    }

    private function flaggedLot(CommandContext $context, int $finding, int $company, Money $basis, int $product, int $scale, int $location, string $condition, BigDecimal $qty, int $count, bool $serialized, string $reason, string $businessDate): void
    {
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $unit = DB::table('products')->where('id', $product)->value('base_unit_id');
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'SURPLUS_ATTRIBUTION', 'business_date' => $businessDate, 'reason' => $reason, 'company_id' => $company,
            'command_id' => $commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $code = ReceivingRecords::withLotCode(fn (string $code): int => $context->connection->table('inventory_lots')->insertOrIgnore([
            'lot_code' => $code, 'product_id' => $product, 'quantity_scale' => $scale, 'source_company_id' => $company, 'acquisition_kind' => 'SURPLUS', 'stock_count_finding_id' => $finding, 'cost_basis_flagged' => true,
            'entered_qty' => (string) $qty, 'entered_unit_id' => is_numeric($unit) ? (int) $unit : 0, 'ratio_to_base' => 1, 'acquired_qty_base' => (string) $qty, 'business_date' => $businessDate,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]));
        $lot = DB::table('inventory_lots')->where('lot_code', $code)->value('id');
        $lot = is_numeric($lot) ? (int) $lot : throw Refused::rejected('FU-13', ['reason' => 'lot_code']);
        $this->cost->basis($context, $lot, 'SURPLUS_FLAGGED_BASIS', $basis, $businessDate, []);
        $base = ['lot' => $lot, 'product' => $product, 'scale' => $scale, 'location' => $location, 'condition' => $condition];
        $rows = [];

        if ($serialized) {
            $numbers = array_slice(self::seenSerials($count), 0, (int) (string) $qty);

            if (! BigDecimal::of(count($numbers))->isEqualTo($qty)) {
                throw Refused::rejected('C-02', ['reason' => 'serial_condition']);
            }

            foreach ($numbers as $number => $text) {
                $rows[] = [...$base, 'qty' => '1', 'serial' => $this->receiving->registerSerial($context, $product, ReceivingRecords::serialKey($text), $text, $lot, $location, $condition, $number + 1)];
            }
        } else {
            $rows[] = [...$base, 'qty' => (string) $qty];
        }

        $ids = $this->inventory->ledger($context, $movement, 'SURPLUS_ATTRIBUTION', $rows);

        foreach ($rows as $index => $row) {
            if (isset($row['serial'])) {
                DB::table('serial_units')->where('id', $row['serial'])->update(['last_entry_id' => $ids[$index]]);
            }
        }
    }

    /**
     * The serials a count saw that no one ever received: what a serialized surplus registers, by the Owner's decision alone; a serial recorded dispatched or lost is corrected through the CM rows (L-48).
     *
     * @return list<string>
     */
    private static function seenSerials(int $count): array
    {
        $seen = [];

        foreach (DB::table('stock_count_lines as l')->leftJoin('serial_units as s', 's.id', '=', 'l.serial_unit_id')->where('l.stock_count_id', $count)
            ->where(fn (Builder $seen) => $seen->whereNull('l.serial_unit_id')->orWhere('s.state', 'NEVER_RECEIVED'))->orderBy('l.id')->get(['l.unregistered_serial_text', 's.serial_number']) as $line) {
            $seen[] = Row::nullable($line, 'unregistered_serial_text') ?? Row::text($line, 'serial_number');
        }

        return $seen;
    }
}
