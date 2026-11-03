<?php

namespace App\Modules\FUL\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\INV\Data\ReceiptEntry;
use App\Modules\INV\Services\DropshipStock;
use App\Modules\INV\Services\ReceivingRecords;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\DemandPosting;
use App\Modules\PRJ\Services\LineScope;
use App\Modules\PRJ\Services\Revalidation;
use App\Modules\PUR\Services\PurchasePosting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Costing\CostSplitter;
use App\Support\Money;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WF-FUL-03's commands (AX-08; AX-10; CM-14; CM-15; CM-27; L-18; L-40; BR-INV-11): "Konfirmasi Kirim Langsung" records
 * the supplier's shipment of a project-linked, direct-to-client purchase line — its shipment reference once per
 * purchase (BD-04), the quantity within the line's cap and the demand's remainder (C-29; C-30), each serial DROPSHIPPED
 * (C-02) — and attributes the line's actual cost to the project at once, never typed (C-25); the client's proof
 * arriving with it records the delivery in the same command. A confirmation recorded in error is reversed with its
 * reason, its delivered part corrected first (CM-08); the client's return goes to the warehouse, into a new lot at its
 * HPP contra, or straight to the supplier under a purchase-return case, each capped by the line's guard (C-27). No
 * warehouse event but the return to the warehouse (FS-05).
 */
final class DropshipPosting
{
    public const WAREHOUSE = 'WAREHOUSE';

    public const SUPPLIER = 'SUPPLIER';

    /** The audit actions whose after-image a likely duplicate return or reversal compares with (BD-13). */
    private const CORRECTIONS = ['FUL.DropShipReturned', 'FUL.DropShipReturnDuplicateConfirmed', 'FUL.DropShipConfirmationReversed', 'FUL.DropShipConfirmationReversalDuplicateConfirmed'];

    /** What a confirmation line delivered, net of the delivery's exact reversals and of what the client returned since (PX-08). */
    public const DELIVERED = <<<'SQL'
        coalesce((select sum(case when v.reverses_delivery_line_id is null then v.qty_base else -v.qty_base end) from delivery_lines v where v.dropship_confirmation_line_id = d.id), 0)
        - coalesce((select sum(r.qty_base) from stock_restorations r where r.dropship_confirmation_line_id = d.id and r.kind in ('DROPSHIP_RETURN', 'DROPSHIP_SUPPLIER_RETURN') and r.after_delivery), 0)
        + coalesce((select sum(e.qty_base) from stock_restorations e join stock_restorations o on o.id = e.reverses_restoration_id
            where e.dropship_confirmation_line_id = d.id and e.kind = 'DROPSHIP_ERROR_REVERSAL' and o.after_delivery), 0)
        SQL;

    public function __construct(
        private readonly DemandPosting $demand,
        private readonly PurchasePosting $purchases,
        private readonly CostPosting $cost,
        private readonly DropshipStock $stock,
        private readonly CorrectionCases $cases,
        private readonly DeliveryPosting $deliveries,
        private readonly Revalidation $revalidation,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * AX-08's set (STATIC): the purchase with its cost cascade (LK-21, LK-22, LK-03), the project and its lines' guards
     * (LK-01; LK-19), the product and the serials (LK-13; LK-17), the evidence (LK-29), a replacement's case (LK-10).
     *
     * @param  list<int>  $purchaseLineIds
     * @param  list<string>  $serials
     * @param  list<int>  $evidence
     */
    public static function locks(int $projectId, int $purchaseId, array $purchaseLineIds, array $serials, array $evidence, ?int $caseId): LockSet
    {
        $lines = DB::table('purchase_lines')->whereIn('id', $purchaseLineIds === [] ? [0] : $purchaseLineIds)->get(['project_line_id', 'product_id']);
        $set = CostPosting::purchaseLocks($purchaseId)->union(LineScope::locks($projectId, array_values(array_filter(array_map(fn (object $line): int => (int) (Row::nullable($line, 'project_line_id') ?? 0), $lines->all())))));

        foreach ($lines as $line) {
            $product = (int) Row::text($line, 'product_id');
            $set = $set->union(DropshipStock::locks($product, [], $serials, null))->add('stock_product_balances', ['product_id' => $product]);
        }

        foreach ($evidence as $document) {
            $set = $set->union(EvidenceGuard::locks($document));
        }

        return $caseId === null ? $set : $set->union(CorrectionCases::locks($caseId));
    }

    /**
     * AX-10's set for a reversal or a return of the confirmation's lines (STATIC): AX-08's on its purchase, the lines'
     * consumption guards (LK-24), a replacement line's case, a return to the warehouse's rack (LK-14; LK-15).
     *
     * @param  list<int>  $dropshipLineIds
     */
    public static function correctionLocks(int $confirmationId, array $dropshipLineIds, ?int $locationId): LockSet
    {
        $confirmation = DB::table('dropship_confirmations')->where('id', $confirmationId)->first(['project_id', 'purchase_id']) ?? throw Refused::rejected('C-14', ['reason' => 'confirmation']);
        $rows = DB::table('dropship_confirmation_lines as d')->leftJoin('serial_units as s', 's.id', '=', 'd.serial_unit_id')->where('d.dropship_confirmation_id', $confirmationId)->whereIn('d.id', $dropshipLineIds === [] ? [0] : $dropshipLineIds)
            ->get(['d.id', 'd.purchase_line_id', 'd.product_id', 'd.correction_case_id', 's.serial_number']);
        $set = self::locks((int) Row::text($confirmation, 'project_id'), (int) Row::text($confirmation, 'purchase_id'), array_values(array_unique(array_map(fn (object $row): int => (int) Row::text($row, 'purchase_line_id'), $rows->all()))), [], [], null);

        foreach ($rows as $row) {
            $serial = Row::nullable($row, 'serial_number');
            $set = $set->union(DropshipStock::locks((int) Row::text($row, 'product_id'), [(int) Row::text($row, 'id')], $serial === null ? [] : [$serial], $locationId));
            $case = Row::nullable($row, 'correction_case_id');

            if ($case !== null) {
                $set = $set->union(CorrectionCases::locks((int) $case));
            }
        }

        return $set;
    }

    /**
     * ROUTE-205: the confirmation, its lines and their guards, the direct cost, the serials, the supplier's proof and, with
     * the client's proof, the delivery; the confirmation.
     *
     * @param  list<array{line: int, quantity: string, serials: list<string>, serialized: bool, scale: int}>  $entered
     * @param  array{recipient: string, condition: ?string, proof: int}|null  $delivery
     */
    public function confirm(CommandContext $context, ProjectRef $project, int $purchaseId, string $reference, string $businessDate, bool $prefilled, array $entered, int $evidenceId, ?int $caseId, ?array $delivery): int
    {
        DeliveryPosting::open($project->id);
        BusinessDateRule::check($businessDate, $prefilled);
        $purchase = DB::table('purchases')->where('id', $purchaseId)->first(['state', 'company_id', 'project_id', 'public_id']);
        $reference = trim($reference);

        if ($purchase === null || (int) Row::text($purchase, 'company_id') !== $project->companyId || $entered === [] || $reference === '') {
            throw Refused::rejected('C-14', ['reason' => 'purchase']);
        }

        // Re-read under the purchase's lock: a cancellation that won leaves nothing to confirm (AX-12).
        if (Row::text($purchase, 'state') !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => Row::text($purchase, 'state')]);
        }

        $existing = DB::table('dropship_confirmations')->where('purchase_id', $purchaseId)->where('supplier_shipment_reference', $reference)->value('public_id');

        if (is_string($existing)) {
            throw Refused::conflict('BD-04', ['confirmation' => $existing, 'reference' => $reference]);
        }

        self::ensureEvidence($evidenceId, $project->companyId);
        $case = $caseId === null ? null : self::replacementCase($caseId, $project->companyId);
        $rows = [];
        $taken = [];
        $demanded = [];

        foreach ($entered as $item) {
            $line = self::purchaseLine($item['line']);

            if ((int) Row::text($line, 'purchase_id') !== $purchaseId || (int) Row::text($line, 'project_id') !== $project->id || isset($taken[$item['line']])) {
                throw Refused::rejected('C-14', ['reason' => 'line']);
            }

            if (Row::text($line, 'kind') !== 'GOODS' || Row::text($line, 'delivery_mode') !== 'DROPSHIP' || Row::text($line, 'fulfillment_mode') !== 'DROPSHIP') {
                throw Refused::rejected('BR-INV-11', ['reason' => 'not_a_dropship_line']);
            }

            if ((bool) Row::text($line, 'is_serialized') !== $item['serialized'] || (int) Row::text($line, 'product_scale') !== $item['scale']) {
                throw Refused::conflict('ST-09', ['serialized' => (bool) Row::text($line, 'is_serialized'), 'scale' => (int) Row::text($line, 'product_scale')]);
            }

            $scale = max(0, (int) Row::text($line, 'quantity_scale'));
            $serials = [];

            foreach ($item['serials'] as $serial) {
                if (trim($serial) !== '') {
                    $serials[ReceivingRecords::serialKey($serial)] ??= trim($serial);
                }
            }

            // A serialized product ships one unit per serial, in its base unit (BD-18; C-02); any other in the line's own unit (C-09).
            $serialized = (bool) Row::text($line, 'is_serialized');
            $base = $serialized ? BigDecimal::of(count($serials)) : ReceivingRecords::base($item['quantity'], (int) Row::text($line, 'ratio_to_base'), $scale);

            // The quantity of a serialized line is its count of units in the base unit, whatever unit it was purchased in — the form sends the serials' count.
            if ($serialized ? ! BigDecimal::of($item['quantity'] === '' ? '0' : $item['quantity'])->isEqualTo($base) : $serials !== []) {
                throw Refused::rejected('C-02', ['reason' => 'serial_count', 'expected' => $item['quantity']]);
            }

            if (! $base->isPositive()) {
                throw Refused::rejected('C-09', ['reason' => 'nothing_shipped']);
            }

            if ($case !== null && ($case['line'] !== $item['line'] || count($entered) !== 1)) {
                throw Refused::rejected('C-59', ['reason' => 'case_line']);
            }

            $room = ReceivingRecords::room($case === null ? ReceiptEntry::ORIGINAL : ReceiptEntry::REPLACEMENT, $line);

            if ($base->isGreaterThan($room)) {
                throw Refused::rejected('C-29', ['reason' => 'above_remainder', 'remainder' => (string) $room]);
            }

            // The demand's remainder less what this command's earlier lines already take of it (C-30).
            $projectLine = (int) Row::text($line, 'project_line_id');
            $remainder = LineScope::remainder($project->id, $projectLine);
            $demand = $remainder === null ? null : BigDecimal::of($remainder)->minus($demanded[$projectLine] ?? BigDecimal::zero());

            if ($demand === null || $base->isGreaterThan($demand)) {
                throw Refused::rejected('C-30', ['reason' => 'remainder', 'remainder' => $demand === null ? '0' : (string) $demand]);
            }

            $demanded[$projectLine] = ($demanded[$projectLine] ?? BigDecimal::zero())->plus($base);
            $taken[$item['line']] = true;
            $rows[] = ['item' => $item, 'line' => $line, 'base' => $base, 'serials' => array_values($serials)];
        }

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $publicId = (string) Str::uuid7();
        $confirmation = (int) DB::table('dropship_confirmations')->insertGetId([
            'public_id' => $publicId, 'company_id' => $project->companyId, 'project_id' => $project->id, 'purchase_id' => $purchaseId, 'supplier_shipment_reference' => $reference,
            'business_date' => $businessDate, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $number = 0;
        $serialNo = 0;
        $quantities = [];
        $idsOf = [];

        foreach ($rows as $index => $row) {
            $line = $row['line'];
            $product = (int) Row::text($line, 'product_id');
            $common = ['dropship_confirmation_id' => $confirmation, 'company_id' => $project->companyId, 'purchase_line_id' => (int) Row::text($line, 'id'), 'project_line_id' => (int) Row::text($line, 'project_line_id'),
                'product_id' => $product, 'quantity_scale' => (int) Row::text($line, 'quantity_scale'), 'is_replacement' => $case !== null, 'correction_case_id' => $case === null ? null : $caseId,
                'command_id' => $commandId, 'recorded_at' => $now, 'recorded_by' => $actor];
            $ids = [];

            if ($row['serials'] === []) {
                $id = (int) DB::table('dropship_confirmation_lines')->insertGetId([...$common, 'entered_qty' => $row['item']['quantity'], 'entered_unit_id' => (int) Row::text($line, 'entered_unit_id'),
                    'ratio_to_base' => (int) Row::text($line, 'ratio_to_base'), 'qty_base' => (string) $row['base'], 'serial_unit_id' => null, 'command_line_no' => ++$number]);
                $ids[] = $id;
                $quantities[$id] = (string) $row['base'];
            }

            foreach ($row['serials'] as $serial) {
                $unit = $this->stock->ship($context, $product, $serial, ++$serialNo);
                $id = (int) DB::table('dropship_confirmation_lines')->insertGetId([...$common, 'entered_qty' => '1', 'entered_unit_id' => (int) Row::text($line, 'base_unit_id'), 'ratio_to_base' => 1,
                    'qty_base' => '1', 'serial_unit_id' => $unit, 'command_line_no' => ++$number]);
                $ids[] = $id;
                $quantities[$id] = '1';
            }

            $idsOf[$index] = $ids;
        }

        $this->stock->confirmed($context, $quantities);
        $written = array_keys($quantities);
        $confirmed = [];
        $audited = [];

        foreach ($rows as $index => $row) {
            $ids = $idsOf[$index] ?? [];
            $lineId = (int) Row::text($row['line'], 'id');
            $projectLine = (int) Row::text($row['line'], 'project_line_id');
            $confirmed[$projectLine] = (string) BigDecimal::of($confirmed[$projectLine] ?? '0')->plus($row['base']);
            $audited['line_'.Row::text($row['line'], 'line_no')] = (string) $row['base'];

            if ($case !== null) {
                // Read before the guard moves: the replacement's share of what the case's returned cost still awaits (DATABASE §5.7; PX-03).
                $basis = ReceivingRecords::replacementBasis((int) $caseId, $lineId, $row['base']);
                $this->purchases->move($context, $lineId, ['replacement_received_net_qty_base' => (string) $row['base']]);

                if ($basis->isPositive()) {
                    $parts = CostSplitter::split($basis, array_map(fn (int $id): string => $quantities[$id], $ids));

                    foreach ($ids as $position => $id) {
                        if (($parts[$position] ?? Money::zero())->isPositive()) {
                            $this->cost->replacementConfirmed($context, $id, $parts[$position], $businessDate);
                        }
                    }

                    $this->cases->post($context, (int) $caseId, ['replaced_value' => $basis->toString()], 'A replacement drop-ship confirmation carried its case\'s basis (DATABASE §5.7).');
                }

                continue;
            }

            // Each confirmation line's cost at the line's cumulative target with its own units counted, then the values it carried (C-29; C-57; annex 14):
            // a serialized line's units each carry their own share, which a later return of that serial contras.
            foreach ($ids as $id) {
                $this->purchases->move($context, $lineId, ['dropship_confirmed_net_qty_base' => $quantities[$id]]);
                $this->purchases->posted($context, $lineId, $this->cost->dropshipConfirmed($context, $id, $businessDate));
            }
        }

        DB::table('evidence_links')->insert([
            'evidence_document_id' => $evidenceId, 'evidence_scope' => 'COMPANY', 'company_id' => $project->companyId, 'role' => 'PRIMARY_PROOF',
            'dropship_confirmation_id' => $confirmation, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $delivered = null;
        $recipient = $delivery === null ? null : trim($delivery['recipient']);

        // L-40: the client's proof came with the supplier's confirmation — the delivery of every line in the same command (AX-08).
        if ($delivery !== null && $recipient !== null) {
            self::ensureEvidence($delivery['proof'], $project->companyId);
            $lines = [];

            foreach ($written as $id) {
                $row = DB::table('dropship_confirmation_lines')->where('id', $id)->first(['project_line_id', 'product_id', 'quantity_scale', 'entered_unit_id', 'ratio_to_base', 'entered_qty', 'qty_base']) ?? throw Refused::rejected('C-14', ['reason' => 'line']);
                $lines[] = ['dropship' => $id, 'project_line' => (int) Row::text($row, 'project_line_id'), 'product' => (int) Row::text($row, 'product_id'), 'scale' => (int) Row::text($row, 'quantity_scale'),
                    'unit' => (int) Row::text($row, 'entered_unit_id'), 'ratio' => (int) Row::text($row, 'ratio_to_base'), 'entered' => Row::text($row, 'entered_qty'), 'base' => Row::text($row, 'qty_base')];
            }

            $delivered = $this->deliveries->direct($context, $project, $businessDate, $recipient, $delivery['condition'], $lines, $delivery['proof']);
        }

        // Net confirmed and, with the client's proof, net delivered in the lines' one statement (GU-01; C-30).
        $this->demand->dropship($context, $confirmed, $delivered === null ? [] : $confirmed);
        $linkage = AuditLinkage::make()->link(AuditRelation::Affects, 'purchases', Row::text($purchase, 'public_id'));
        $linkage = $delivered === null ? $linkage : $linkage->link(AuditRelation::Affects, 'deliveries', $delivered['public_id']);
        $caseRef = $caseId === null ? null : DB::table('correction_cases')->where('id', $caseId)->value('public_id');
        $linkage = is_string($caseRef) ? $linkage->correctionCase($caseRef) : $linkage;
        $this->audit->record(
            action: 'FUL.DropShipConfirmed',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $actor,
            companyId: $project->companyId,
            entityType: 'dropship_confirmation',
            entityId: $confirmation,
            entityPublicId: $publicId,
            commandId: $commandId,
            change: AuditChange::created([
                'reference' => $reference, 'lines' => $audited,
                ...(array_merge(...array_column($rows, 'serials')) === [] ? [] : ['serials' => array_merge(...array_column($rows, 'serials'))]),
                ...($case === null ? [] : ['replacement' => true]),
                ...($delivered === null ? [] : ['delivery' => $delivered['public_id'], 'recipient' => $recipient]),
            ]),
            linkage: $linkage,
            businessDate: $businessDate,
        );

        return $confirmation;
    }

    /**
     * ROUTE-206 (CM-27): the confirmation's lines reversed as never confirmed, each with its reason, never beyond what the
     * line still holds less what the client is recorded to have received — that delivery corrected first (CM-08); the
     * confirmation.
     *
     * @param  list<array{line: int, quantity: string}>  $items  confirmation line => base quantity
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     */
    public function reverse(CommandContext $context, ProjectRef $project, int $confirmationId, array $items, string $reason, string $businessDate, bool $prefilled, array $acknowledged = [], ?string $duplicateReason = null): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $confirmation = self::confirmation($confirmationId, $project);
        $lines = self::linesOf($confirmationId, $items);
        $total = BigDecimal::zero();

        foreach ($items as $item) {
            $line = $lines[$item['line']];
            $qty = self::quantity($line, $item['quantity']);
            $standing = BigDecimal::of(Row::text($line, 'open'))->minus(Row::text($line, 'delivered'));

            if ($qty->isGreaterThan($standing)) {
                throw Refused::rejected('C-27', ['reason' => BigDecimal::of(Row::text($line, 'delivered'))->isPositive() ? 'delivered' : 'above_open', 'open' => (string) BigDecimal::max(BigDecimal::zero(), $standing)]);
            }

            $total = $total->plus($qty);
        }

        $matches = self::likelyDuplicates($confirmationId, 'DROPSHIP_CONFIRMATION_REVERSAL', (string) $total, $businessDate);
        DuplicateWarning::check('L-49', $matches, $acknowledged, $duplicateReason);
        $confirmed = [];
        $number = 0;

        foreach ($items as $item) {
            $line = $lines[$item['line']];
            $qty = self::quantity($line, $item['quantity']);
            $restored = $this->stock->restore($context, $item['line'], ['kind' => 'DROPSHIP_CONFIRMATION_REVERSAL', 'qty' => (string) $qty, 'after_delivery' => null, 'case' => null, 'location' => null, 'condition' => null,
                'project' => $project->id, 'date' => $businessDate, 'reason' => $reason, 'line_no' => ++$number]);
            $purchaseLine = (int) Row::text($line, 'purchase_line_id');
            $projectLine = (int) Row::text($line, 'project_line_id');
            $confirmed[$projectLine] = (string) BigDecimal::of($confirmed[$projectLine] ?? '0')->minus($qty);
            $case = Row::nullable($line, 'correction_case_id');

            if ($case !== null) {
                // A replacement confirmed in error: its line's replacement quantity and its case's replaced value taken back (C-59; C-46).
                $this->purchases->move($context, $purchaseLine, ['replacement_received_net_qty_base' => (string) $qty->negated()]);
                $contra = Money::of((string) DB::table('cost_attributions')->where('stock_restoration_id', $restored['restoration'])->where('cause', 'RESTORATION_CONTRA')->sum('amount'));

                if (! $contra->isZero()) {
                    $this->cases->post($context, (int) $case, ['replaced_value' => $contra->toString()], 'A replacement drop-ship confirmation was reversed (CM-27; C-46).');
                }

                continue;
            }

            // As never confirmed, item by item: the next item's re-spread reads the line's received quantity this one lowered (§6.3).
            $this->purchases->move($context, $purchaseLine, ['dropship_confirmed_net_qty_base' => (string) $qty->negated(), 'posted_goods_value' => $restored['posted']->goods->toString(), 'posted_noncreditable_tax_value' => $restored['posted']->tax->toString()]);
        }

        $this->demand->dropship($context, $confirmed);
        $this->revalidation->revalidate($context, $project->id, Row::text($confirmation, 'public_id'));
        $this->audit->record(
            action: $matches === [] ? 'FUL.DropShipConfirmationReversed' : 'FUL.DropShipConfirmationReversalDuplicateConfirmed',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $context->request->actorUserId,
            companyId: $project->companyId,
            entityType: 'dropship_confirmation',
            entityId: $confirmationId,
            entityPublicId: Row::text($confirmation, 'public_id'),
            reason: $reason,
            commandId: $context->request->commandId,
            change: AuditChange::created(['kind' => 'DROPSHIP_CONFIRMATION_REVERSAL', 'quantity' => (string) $total, ...($matches === [] ? [] : ['matches' => $matches, 'duplicate_reason' => $duplicateReason])]),
            linkage: AuditLinkage::make()->link(AuditRelation::Reverses, 'dropship_confirmations', Row::text($confirmation, 'public_id')),
            businessDate: $businessDate,
        );

        return $confirmationId;
    }

    /**
     * ROUTE-207 (CM-14; CM-15): the client's return of the confirmation's lines, with its reason and its case — to the
     * warehouse, a DROPSHIP_RETURN case and a new lot at the rack carrying exactly the HPP contra; to the supplier, a
     * PURCHASE_RETURN case of the purchase's company whose causal project is the confirmation's, the units' net HPP moved
     * to SUPPLIER_RETURN and settled through ROUTE-177. A line the client is recorded to have received returns from what
     * it received, lowering the net delivered too (`after_delivery`); one never delivered returns from what it holds
     * (PX-08). The case.
     *
     * @param  list<array{line: int, quantity: string}>  $items
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms (L-49)
     */
    public function returned(CommandContext $context, ProjectRef $project, int $confirmationId, string $to, array $items, ?int $locationId, ?string $condition, string $reason, string $businessDate, bool $prefilled, array $acknowledged = [], ?string $duplicateReason = null): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $confirmation = self::confirmation($confirmationId, $project);
        $lines = self::linesOf($confirmationId, $items);
        $kind = match ($to) {
            self::WAREHOUSE => 'DROPSHIP_RETURN',
            self::SUPPLIER => 'DROPSHIP_SUPPLIER_RETURN',
            default => throw Refused::rejected('C-27', ['reason' => 'kind']),
        };
        $total = BigDecimal::zero();
        $plan = [];

        foreach ($items as $item) {
            $line = $lines[$item['line']];
            $qty = self::quantity($line, $item['quantity']);
            $received = BigDecimal::of(Row::text($line, 'delivered'));
            $after = $received->isPositive();
            $cap = $after ? $received : BigDecimal::of(Row::text($line, 'open'));

            if ($qty->isGreaterThan($cap)) {
                throw Refused::rejected('C-27', ['reason' => 'above_open', 'open' => (string) $cap]);
            }

            $plan[] = ['item' => $item, 'line' => $line, 'qty' => $qty, 'after' => $after];
            $total = $total->plus($qty);
        }

        $matches = self::likelyDuplicates($confirmationId, $kind, (string) $total, $businessDate);
        DuplicateWarning::check('L-49', $matches, $acknowledged, $duplicateReason);
        // A supplier settles a purchase return line by line — its replacement, credit and unrecovered share are one purchase line's (DATABASE §5.7; C-46):
        // one case per purchase line returned; the warehouse's return one case for the command.
        $cases = [];

        foreach ($plan as $step) {
            $key = $to === self::SUPPLIER ? (int) Row::text($step['line'], 'purchase_line_id') : 0;

            if (! isset($cases[$key])) {
                $opened = $this->cases->open($context, AuthorityClass::AdmPlus, $project->companyId, $to === self::SUPPLIER ? 'PURCHASE_RETURN' : 'DROPSHIP_RETURN', $reason, $project->id, $project->id);
                $context->created((new LockSet)->add('correction_cases', ['id' => (int) $opened->id]));
                $cases[$key] = ['id' => (int) $opened->id, 'public_id' => (string) $opened->public_id];
            }
        }

        $confirmed = [];
        $delivered = [];
        $lots = [];
        $number = 0;

        foreach ($plan as $step) {
            $case = $cases[$to === self::SUPPLIER ? (int) Row::text($step['line'], 'purchase_line_id') : 0];
            $restored = $this->stock->restore($context, $step['item']['line'], ['kind' => $kind, 'qty' => (string) $step['qty'], 'after_delivery' => $step['after'], 'case' => $case['id'],
                'location' => $locationId, 'condition' => $condition, 'project' => $project->id, 'date' => $businessDate, 'reason' => $reason, 'line_no' => ++$number]);
            $projectLine = (int) Row::text($step['line'], 'project_line_id');
            $confirmed[$projectLine] = (string) BigDecimal::of($confirmed[$projectLine] ?? '0')->minus($step['qty']);

            if ($step['after']) {
                $delivered[$projectLine] = (string) BigDecimal::of($delivered[$projectLine] ?? '0')->minus($step['qty']);
            }

            $code = $restored['lot'] === null ? null : DB::table('inventory_lots')->where('id', $restored['lot'])->value('lot_code');

            if (is_string($code)) {
                $lots[] = $code;
            }
        }

        $this->demand->dropship($context, $confirmed, $delivered);
        $this->revalidation->revalidate($context, $project->id, Row::text($confirmation, 'public_id'));
        $first = array_values($cases)[0] ?? throw Refused::rejected('C-14', ['reason' => 'line']);
        $this->audit->record(
            action: $matches === [] ? 'FUL.DropShipReturned' : 'FUL.DropShipReturnDuplicateConfirmed',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $context->request->actorUserId,
            companyId: $project->companyId,
            entityType: 'dropship_confirmation',
            entityId: $confirmationId,
            entityPublicId: Row::text($confirmation, 'public_id'),
            reason: $reason,
            commandId: $context->request->commandId,
            change: AuditChange::created(['kind' => $kind, 'quantity' => (string) $total, 'cases' => array_column($cases, 'public_id'), ...($lots === [] ? [] : ['lots' => $lots]), ...($matches === [] ? [] : ['matches' => $matches, 'duplicate_reason' => $duplicateReason])]),
            linkage: AuditLinkage::make()->link(AuditRelation::Returns, 'dropship_confirmations', Row::text($confirmation, 'public_id'))->correctionCase($first['public_id']),
            businessDate: $businessDate,
        );

        return $first['id'];
    }

    /**
     * BD-13: a reversal or a return of the same confirmation, kind, total quantity and business date already standing (L-49).
     *
     * @return list<string> the standing corrections' commands
     */
    public static function likelyDuplicates(int $confirmationId, string $kind, string $total, string $businessDate): array
    {
        $found = [];

        foreach (DB::table('audit_events')->where('entity_type', 'dropship_confirmation')->where('entity_id', $confirmationId)->whereIn('action', self::CORRECTIONS)
            ->whereRaw("metadata->>'business_date' = ?", [$businessDate])->whereRaw("after->>'kind' = ?", [$kind])->whereRaw("(after->>'quantity')::numeric = ?::numeric", [$total])
            ->orderBy('id')->pluck('command_id') as $command) {
            if (is_string($command)) {
                $found[] = $command;
            }
        }

        return $found;
    }

    /**
     * A confirmation's lines by id with what each holds and the client received (PX-08), read under their guards.
     *
     * @param  list<int>  $ids
     * @return array<int, object>
     */
    public static function figures(array $ids): array
    {
        $found = [];

        foreach (DB::table('dropship_confirmation_lines as d')->join('stock_consumption_balances as b', 'b.dropship_confirmation_line_id', '=', 'd.id')->whereIn('d.id', $ids === [] ? [0] : $ids)
            ->get(['d.id', 'd.dropship_confirmation_id', 'd.purchase_line_id', 'd.project_line_id', 'd.serial_unit_id', 'd.correction_case_id', 'd.quantity_scale', 'd.qty_base',
                DB::raw('b.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base as open'), DB::raw('('.self::DELIVERED.') as delivered')])->all() as $row) {
            $found[(int) Row::text($row, 'id')] = $row;
        }

        return $found;
    }

    /**
     * @param  list<array{line: int, quantity: string}>  $items
     * @return array<int, object>
     */
    private static function linesOf(int $confirmationId, array $items): array
    {
        $ids = array_column($items, 'line');
        $lines = self::figures($ids);

        if ($items === [] || count(array_unique($ids)) !== count($ids)) {
            throw Refused::rejected('C-14', ['reason' => 'line']);
        }

        foreach ($ids as $id) {
            if (! isset($lines[$id]) || (int) Row::text($lines[$id], 'dropship_confirmation_id') !== $confirmationId) {
                throw Refused::rejected('C-14', ['reason' => 'line']);
            }
        }

        return $lines;
    }

    /** A returned or reversed quantity in the base unit, whole in the line's scale, one per serial (C-09; C-02). */
    private static function quantity(object $line, string $entered): BigDecimal
    {
        $qty = ReceivingRecords::base($entered === '' ? '0' : $entered, 1, max(0, (int) Row::text($line, 'quantity_scale')));

        if (! $qty->isPositive()) {
            throw Refused::rejected('C-09', ['reason' => 'nothing']);
        }

        if (Row::nullable($line, 'serial_unit_id') !== null && ! $qty->isEqualTo(1)) {
            throw Refused::rejected('C-02', ['reason' => 'serial_return']);
        }

        return $qty;
    }

    private static function confirmation(int $confirmationId, ProjectRef $project): object
    {
        $row = DB::table('dropship_confirmations')->where('id', $confirmationId)->first(['project_id', 'company_id', 'public_id']);

        if ($row === null || (int) Row::text($row, 'project_id') !== $project->id || (int) Row::text($row, 'company_id') !== $project->companyId) {
            throw Refused::rejected('C-14', ['reason' => 'confirmation']);
        }

        return $row;
    }

    /** The purchase line with its guard, its demand line's mode and project, its product's policy and base unit. */
    private static function purchaseLine(int $lineId): object
    {
        return DB::table('purchase_lines as l')->join('purchase_line_balances as b', 'b.purchase_line_id', '=', 'l.id')->join('project_lines as pl', 'pl.id', '=', 'l.project_line_id')->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.id', $lineId)
            ->first(['l.id', 'l.purchase_id', 'l.line_no', 'l.kind', 'l.delivery_mode', 'l.product_id', 'l.quantity_scale', 'l.entered_unit_id', 'l.ratio_to_base', 'l.project_line_id', 'pl.project_id', 'pl.fulfillment_mode',
                'p.is_serialized', 'p.quantity_scale as product_scale', 'p.base_unit_id', 'b.ordered_qty_base', 'b.normal_received_net_qty_base', 'b.closed_qty_base', 'b.dropship_confirmed_net_qty_base',
                'b.replacement_expected_qty_base', 'b.replacement_received_net_qty_base'])
            ?? throw Refused::rejected('C-14', ['reason' => 'line']);
    }

    /**
     * A replacement's case: an OPEN purchase return of the company whose units the supplier took back from this line's confirmations (C-59).
     *
     * @return array{line: int}
     */
    private static function replacementCase(int $caseId, int $companyId): array
    {
        $case = DB::table('correction_cases')->where('id', $caseId)->first(['state', 'case_kind', 'company_id']);
        $line = DB::table('stock_restorations as r')->join('dropship_confirmation_lines as d', 'd.id', '=', 'r.dropship_confirmation_line_id')->where('r.correction_case_id', $caseId)->where('r.kind', 'DROPSHIP_SUPPLIER_RETURN')->value('d.purchase_line_id');

        if ($case === null || Row::text($case, 'state') !== 'OPEN' || Row::text($case, 'case_kind') !== 'PURCHASE_RETURN' || (int) Row::text($case, 'company_id') !== $companyId || ! is_numeric($line)) {
            throw Refused::rejected('C-59', ['reason' => 'case_not_open']);
        }

        return ['line' => (int) $line];
    }

    /** The proof is the company's own (PJ-08; C-32). */
    private static function ensureEvidence(int $evidenceId, int $companyId): void
    {
        if (! DB::table('evidence_documents')->where('id', $evidenceId)->where('company_id', $companyId)->where('evidence_scope', 'COMPANY')->exists()) {
            throw Refused::rejected('C-14', ['reason' => 'evidence']);
        }
    }
}
