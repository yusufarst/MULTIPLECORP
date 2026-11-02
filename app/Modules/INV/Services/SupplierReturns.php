<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\INV\Data\StockPicks;
use App\Modules\OPS\Services\CorrectionCases;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Retur ke Pemasok" (ROUTE-176; AX-11; WF-PUR-03): units of one lot of the purchase, physically present and unconsumed, leave stock to the supplier on a PURCHASE_RETURN movement of the purchase's company, under the return case it opens with its reason; their current net cost is drawn into SUPPLIER_RETURN and the case's returned cost, never HPP; the cut of IP-20 in the same action (SF-RSV-CUT; BR-INV-06; DATABASE §5.3, §6.1).
 */
final class SupplierReturns
{
    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly CorrectionCases $cases,
        private readonly AuditRecorder $audit,
    ) {}

    /** AX-11's set (STATIC; CONCURRENCY_IDEMPOTENCY §7.1): the purchase and its project, the places, serials, cut and evidence, each lot's cost, the units' causal project. */
    public static function locks(int $purchaseId, StockPicks $entry): LockSet
    {
        $set = StockPlaces::locks($entry->productId, $entry->picks, $entry->cuts, $entry->evidence)->add('purchases', ['id' => $purchaseId]);
        $project = DB::table('purchases')->where('id', $purchaseId)->value('project_id');

        if (is_numeric($project)) {
            $set->add('projects', ['id' => (int) $project]);
        }

        foreach ($entry->picks as $pick) {
            $set->add('lot_cost_balances', ['lot_id' => $pick['lot']]);
        }

        foreach (LossPosting::parts($entry->picks, null) as $part) {
            if ($part['project'] !== null) {
                $set->add('projects', ['id' => $part['project']]);
            }
        }

        return $set;
    }

    /** ROUTE-176: the case, the stock-out, the cut and the cost draw as one action; the case. */
    public function record(CommandContext $context, int $purchaseId, StockPicks $entry): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $context->require(self::locks($purchaseId, $entry));
        $purchase = DB::table('purchases')->where('id', $purchaseId)->first(['company_id', 'project_id', 'public_id']) ?? throw Refused::rejected('C-14', ['reason' => 'purchase']);
        $company = (int) Row::text($purchase, 'company_id');
        $scale = StockPlaces::ensurePolicy($entry);
        $lot = self::ensureOneLot($entry, $purchaseId, $company);
        $reduced = StockPlaces::ensureHeld($entry->picks, $entry->productId, $scale, $entry->serializedSeen);

        // A unit damaged under a case keeps that case's causal project (CM-11): one cause per return — a caused unit never shares a return with an uncaused one, whose unrecovered share is the lot's own company's — never chosen in the payload (AZ-08).
        $causes = array_values(array_unique(array_map(fn (array $part): string => $part['project'] === null ? '' : (string) $part['project'], LossPosting::parts($entry->picks, null))));

        if (count($causes) > 1) {
            throw Refused::rejected('C-07', ['reason' => 'mixed_cause']);
        }

        $projectId = Row::nullable($purchase, 'project_id');
        $cause = ($causes[0] ?? '') === '' ? null : (int) $causes[0];
        $case = $this->cases->open($context, AuthorityClass::AdmPlus, $company, 'PURCHASE_RETURN', $entry->reason, $projectId === null ? null : (int) $projectId, $cause, $lot);
        // The case is this command's own row: no other session sees it before the commit (CommandContext::created).
        $context->created((new LockSet)->add('correction_cases', ['id' => (int) $case->id]));
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'PURCHASE_RETURN', 'business_date' => $entry->businessDate, 'reason' => $entry->reason,
            'company_id' => $company, 'purchase_id' => $purchaseId, 'correction_case_id' => (int) $case->id,
            'command_id' => $commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $reserved = null;

        if ($reduced->isPositive() && (InventoryPosting::shortfall($entry->productId, (string) $reduced)->isPositive() || $entry->cuts !== [])) {
            $reserved = $this->inventory->cut($context, $entry->productId, $entry->cuts, (string) $reduced, $movement, 'INV.ReturnToSupplier');
        }

        $consumptions = [];
        $rows = [];
        $number = 0;
        $returned = BigDecimal::zero();

        // One consumption for each place or serial, the bearer the lot's own company and no project (C-28; DATABASE §25).
        foreach ($entry->picks as $pick) {
            $qty = ReceivingRecords::base($pick['qty'], 1, $scale);
            $returned = $returned->plus($qty);
            $consumption = (int) DB::table('stock_consumptions')->insertGetId([
                'stock_movement_id' => $movement, 'lot_id' => $pick['lot'], 'source_company_id' => $company, 'product_id' => $entry->productId, 'quantity_scale' => $scale, 'qty_base' => (string) $qty, 'kind' => 'PURCHASE_RETURN',
                'bearer_company_id' => $company, 'bearer_project_id' => null, 'correction_case_id' => (int) $case->id, 'case_company_id' => $company,
                'command_id' => $commandId, 'command_line_no' => ++$number, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $context->connection->table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption, 'qty_base' => (string) $qty]);
            $consumptions[] = $consumption;
            $rows[] = ['lot' => $pick['lot'], 'product' => $entry->productId, 'scale' => $scale, 'location' => $pick['location'], 'condition' => $pick['condition'], 'qty' => (string) $qty->negated(), 'consumption' => $consumption, ...($pick['serial'] === null ? [] : ['serial' => $pick['serial']])];
        }

        $ids = $this->inventory->ledger($context, $movement, 'PURCHASE_RETURN', $rows, $reserved);

        foreach ($rows as $index => $row) {
            // A unit leaves once (C-02): conditional on what it was just read to be.
            if (isset($row['serial']) && DB::table('serial_units')->where('id', $row['serial'])->where('state', 'IN_STOCK')->update(['state' => 'RETURNED_TO_SUPPLIER', 'current_lot_id' => null, 'current_location_id' => null, 'current_condition' => null, 'last_entry_id' => $ids[$index]]) !== 1) {
                throw Refused::rejected('C-02', ['reason' => 'serial']);
            }
        }

        foreach ($consumptions as $consumption) {
            $this->cost->draw($context, $consumption, $entry->businessDate);
        }

        StockPlaces::linkEvidence($context, $entry->evidence, $movement);
        $this->audit->record(
            action: 'INV.ReturnedToSupplier',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $actor,
            companyId: $company,
            entityType: 'correction_case',
            entityId: (int) $case->id,
            entityPublicId: (string) $case->public_id,
            reason: $entry->reason,
            commandId: $commandId,
            change: AuditChange::created(['quantity' => (string) $returned, 'movement' => $publicId, 'purchase' => Row::text($purchase, 'public_id'), 'cuts' => count($entry->cuts), 'evidence' => count($entry->evidence)]),
            metadata: ['business_date' => $entry->businessDate],
        );

        return (int) $case->id;
    }

    /** The purchase's lot, every pick one lot of it, its source the purchase's company (C-08; CS-04): the lot a replacement receipt later finds its line by. */
    private static function ensureOneLot(StockPicks $entry, int $purchaseId, int $company): int
    {
        $lots = array_values(array_unique(array_map(fn (array $pick): int => $pick['lot'], $entry->picks)));

        if (count($lots) !== 1) {
            throw Refused::rejected('C-14', ['reason' => count($lots) === 0 ? 'nothing' : 'one_lot']);
        }

        $ours = DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->join('purchase_lines as l', 'l.id', '=', 'r.purchase_line_id')
            ->where('i.id', $lots[0])->where('l.purchase_id', $purchaseId)->where('i.source_company_id', $company)->exists();

        if (! $ours) {
            throw Refused::rejected('C-14', ['reason' => 'lot']);
        }

        return $lots[0];
    }
}
