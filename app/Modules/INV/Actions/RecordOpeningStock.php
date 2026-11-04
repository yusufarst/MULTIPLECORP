<?php

namespace App\Modules\INV\Actions;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Services\InventoryPosting;
use App\Modules\INV\Services\ReceivingRecords;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `INV RecordOpeningStock` (AX-28; WF-MIG-01; BR-INV-12; DATABASE §24): an opening import row's lot — its company,
 * product, location and condition with its cost, the migration-time conversion snapshot, integral base quantities for
 * counted and serialized products and each serial (C-09; IMP-09) — written as an OPENING movement through the ledger,
 * its OPENING_COST basis with it, keyed to its import row (C-47). It reserves nothing (IMP-08). Driven only, by the
 * Owner's commit of a signed-off batch (`import.commit`).
 */
final class RecordOpeningStock
{
    public const TYPE = 'INV.RecordOpeningStock';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $costs,
        private readonly ReceivingRecords $receiving,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{import_row: int, product: int, location: int, condition: string, quantity: string, unit: int, ratio: int, cost: string, serials: list<string>, date: string}  $entry
     */
    public function run(string $commandId, int $actorUserId, int $companyId, array $entry): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $entry));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $entry): CommandResult {
            $product = DB::table('products')->where('id', $entry['product'])->first(['quantity_scale', 'is_serialized', 'kind']);
            $set = (new LockSet)->add('stock_product_balances', ['product_id' => $entry['product']])
                ->add('stock_scope_balances', ['product_id' => $entry['product'], 'location_id' => $entry['location'], 'condition' => $entry['condition']]);

            foreach ($entry['serials'] as $serial) {
                $set->add('serial_units', ['product_id' => $entry['product'], 'serial_key' => ReceivingRecords::serialKey($serial)]);
            }

            $context->lock($set);

            if ($product === null || $product->kind !== 'GOODS') {
                throw Refused::rejected('BR-INV-11', ['reason' => 'not_stocked']);
            }

            $scale = max(0, (int) Row::text($product, 'quantity_scale'));
            $base = BigDecimal::of($entry['quantity'])->multipliedBy($entry['ratio']);

            // C-09: base quantities stay integral for counted and serialized products; the snapshot is the ratio in force now.
            if ($base->strippedOfTrailingZeros()->getScale() > $scale || ! $base->isPositive()) {
                throw Refused::rejected('C-09', ['reason' => 'scale']);
            }

            $serialized = in_array(Row::text($product, 'is_serialized'), ['1', 'true'], true);

            if ($serialized !== ($entry['serials'] !== []) || ($serialized && ! $base->isEqualTo(count($entry['serials'])))) {
                throw Refused::rejected('C-02', ['reason' => 'serial_count']);
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            $movement = (int) $context->connection->table('stock_movements')->insertGetId([
                'public_id' => (string) Str::uuid7(), 'movement_type' => 'OPENING', 'business_date' => $entry['date'], 'company_id' => $companyId,
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $code = ReceivingRecords::withLotCode(fn (string $code): int => $context->connection->table('inventory_lots')->insertOrIgnore([
                'lot_code' => $code, 'product_id' => $entry['product'], 'quantity_scale' => $scale, 'source_company_id' => $companyId, 'acquisition_kind' => 'OPENING',
                'import_row_id' => $entry['import_row'], 'entered_qty' => $entry['quantity'], 'entered_unit_id' => $entry['unit'], 'ratio_to_base' => $entry['ratio'],
                'acquired_qty_base' => (string) $base->toScale($scale, RoundingMode::Unnecessary), 'business_date' => $entry['date'],
                'command_id' => $context->request->commandId, 'command_line_no' => 2, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]));
            $lot = (int) Row::text(DB::table('inventory_lots')->where('lot_code', $code)->first(['id']) ?? throw Refused::rejected('FU-13', []), 'id');
            $this->costs->basis($context, $lot, 'OPENING_COST', Money::of($entry['cost']), $entry['date'], ['import_row_id' => $entry['import_row']]);
            $line = ['lot' => $lot, 'product' => $entry['product'], 'scale' => $scale, 'location' => $entry['location'], 'condition' => $entry['condition']];
            $rows = [];
            $serials = [];

            foreach ($entry['serials'] as $index => $serial) {
                $id = $this->receiving->registerSerial($context, $entry['product'], ReceivingRecords::serialKey($serial), $serial, $lot, $entry['location'], $entry['condition'], $index + 3);
                $serials[] = $id;
                $rows[] = [...$line, 'qty' => '1', 'serial' => $id];
            }

            $ids = $this->inventory->ledger($context, $movement, 'OPENING', $rows === [] ? [[...$line, 'qty' => (string) $base->toScale($scale, RoundingMode::Unnecessary)]] : $rows);

            foreach ($serials as $index => $serial) {
                $context->connection->table('serial_units')->where('id', $serial)->update(['last_entry_id' => $ids[$index]]);
            }

            $this->audit->record(
                action: 'INV.OpeningStockRecorded',
                authorityClass: AuthorityClass::OwnerOnly,
                actorUserId: $actor,
                companyId: $companyId,
                entityType: 'inventory_lot',
                entityId: $lot,
                commandId: $context->request->commandId,
                change: AuditChange::created(['product_id' => $entry['product'], 'location_id' => $entry['location'], 'condition' => $entry['condition'], 'qty_base' => (string) $base, 'cost' => $entry['cost']]),
                businessDate: $entry['date'],
                metadata: ['import_row_id' => $entry['import_row'], 'serials' => count($serials)],
            );

            return new CommandResult('inventory_lot', $lot);
        });
    }
}
