<?php

namespace App\Modules\INV\Services;

use App\Modules\CST\Services\CostPosting;
use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\INV\Data\CountVariance;
use App\Modules\INV\Data\StockPicks;
use App\Modules\OPS\Services\Corrections;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SF-UNATTRIBUTED (DIR-026; DIR-027; DATABASE §5.8; C-35–C-37): an unexplained fungible loss or condition change across companies corrects the physical truth at once and records one case with its immutable snapshot; no lot, cost, loss or profit moves and nothing is retained. A later surplus finds it exactly; the Owner, or definitive later evidence under OD-10, resolves it within each company's recognition exposure and the overlap group's bound; a resolution is reversed exactly and at most once; a condition-changed unit's disposal converts its case. No rule here decides a company: no FIFO, oldest-first or proportion (FS-09).
 */
final class UnattributedCases
{
    public function __construct(
        private readonly InventoryPosting $inventory,
        private readonly CostPosting $cost,
        private readonly AuditRecorder $audit,
        private readonly LossRestorations $restorations,
        private readonly Corrections $corrections,
        private readonly LossPosting $losses,
    ) {}

    /** A scope's set: the product, both conditions, its pending cases and each candidate lot's cost guard, read for the snapshot. */
    public static function scopeLocks(int $product, int $location): LockSet
    {
        $set = (new LockSet)->add('stock_product_balances', ['product_id' => $product]);

        foreach (['USABLE', 'UNUSABLE'] as $condition) {
            $set->add('stock_scope_balances', ['product_id' => $product, 'location_id' => $location, 'condition' => $condition]);
        }

        foreach (self::pendingCases($product, $location) as $case) {
            $set->add('unattributed_loss_cases', ['id' => $case]);
        }

        foreach (DB::table('stock_lot_balances as lb')->where('product_id', $product)->where('location_id', $location)->where('qty', '>', 0)->distinct()->orderBy('lot_id')->pluck('lot_id') as $lot) {
            $set->add('lot_cost_balances', ['lot_id' => is_numeric($lot) ? (int) $lot : 0]);
        }

        return $set;
    }

    /**
     * The scope's cases still pending, and their group founders.
     *
     * @return list<int>
     */
    public static function pendingCases(int $product, int $location): array
    {
        $ids = [];

        foreach (DB::table('unattributed_loss_cases')->where('product_id', $product)->where('location_id', $location)->where('pending_qty_base', '>', 0)->orderBy('id')->get(['id', 'exposure_group_case_id']) as $row) {
            $ids[(int) Row::text($row, 'id')] = true;
            $founder = Row::nullable($row, 'exposure_group_case_id');

            if ($founder !== null) {
                $ids[(int) $founder] = true;
            }
        }

        return array_keys($ids);
    }

    /** Whether the scope's candidate lots belong to several companies, the only case a pending quantity may be recorded (L-42; DIR-026). */
    public static function acrossCompanies(int $product, int $location, string $condition): bool
    {
        return DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->where('b.product_id', $product)->where('b.location_id', $location)->where('b.condition', $condition)->where('b.qty', '>', 0)->distinct()->count('i.source_company_id') > 1;
    }

    /**
     * A case's recognition, in one transaction (§5.8): its lot-less entries, the snapshot of every candidate lot and company, the overlap group's rows, the cut when this movement carries it, the audit; the case. `$cutReduction` is the command's whole usable reduction when this movement carries its cut.
     *
     * @param  list<int>  $evidence
     * @param  array<int, string>  $cuts
     * @param  array{matches: list<string>, duplicate_reason: ?string}|null  $duplicate  the likely duplicate the actor confirmed (QS-21)
     */
    public function recognize(CommandContext $context, int $product, int $scale, int $location, string $kind, string $condition, BigDecimal $qty, string $reason, string $businessDate, array $evidence, ?int $countId, array $cuts, ?string $cutReduction, string $routedAction, ?array $duplicate = null): int
    {
        if (! self::acrossCompanies($product, $location, $condition)) {
            throw Refused::rejected('L-42', ['reason' => 'one_company']);
        }

        $scope = DB::table('stock_scope_balances as sb')->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->first(['pending_reduction_qty']);
        $prior = $scope === null ? '0' : Row::text($scope, 'pending_reduction_qty');
        [$candidates, $exposures, $total] = self::snapshot($product, $location, $condition);

        if ($qty->isGreaterThan($total->minus($prior))) {
            throw Refused::rejected('C-36', ['reason' => 'within_exposure', 'available' => (string) $total->minus($prior)]);
        }

        $others = DB::table('unattributed_loss_cases')->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->where('pending_qty_base', '>', 0)->orderBy('id')->first(['id', 'exposure_group_case_id']);
        $founder = $others === null ? null : (int) (Row::nullable($others, 'exposure_group_case_id') ?? Row::text($others, 'id'));
        [$movement, $case, $publicId] = $this->header($context, 'UNATTRIBUTED_RECOGNITION', [
            'case_kind' => $kind, 'product_id' => $product, 'quantity_scale' => $scale, 'location_id' => $location, 'condition' => $condition, 'recognized_qty_base' => (string) $qty, 'exposure_qty_base' => (string) $total, 'prior_pending_qty_base' => $prior, 'exposure_group_case_id' => $founder,
        ], $reason, $businessDate, $countId);
        $this->writeSnapshot($context, $case, $candidates, $exposures);
        $this->raiseGroup($context, $founder ?? $case, $exposures);
        $reserved = null;

        if ($cutReduction !== null && BigDecimal::of($cutReduction)->isPositive() && (InventoryPosting::shortfall($product, $cutReduction)->isPositive() || $cuts !== [])) {
            $reserved = $this->inventory->cut($context, $product, $cuts, $cutReduction, $movement, $routedAction);
        }

        $row = ['lot' => null, 'case' => $case, 'product' => $product, 'scale' => $scale, 'location' => $location];
        $entries = [[...$row, 'condition' => $condition, 'qty' => (string) $qty->negated(), 'pending' => ['pending_reduction_qty' => (string) $qty]]];

        if ($kind === 'CONDITION_CHANGE') {
            $entries[] = [...$row, 'condition' => 'UNUSABLE', 'qty' => (string) $qty, 'pending' => ['pending_addition_qty' => (string) $qty]];
        }

        $this->inventory->ledger($context, $movement, 'UNATTRIBUTED_RECOGNITION', $entries, $reserved);
        self::linkEvidence($context, $evidence, $case);
        $this->audit->record(
            action: $duplicate === null ? 'INV.UnattributedRecognized' : 'INV.UnattributedRecognizedDuplicateConfirmed', authorityClass: AuthorityClass::AdmPlus, actorUserId: $context->request->actorUserId, companyId: null, entityType: 'unattributed_loss_case', entityId: $case, entityPublicId: $publicId, reason: $reason, commandId: $context->request->commandId,
            change: AuditChange::created(['kind' => $kind, 'quantity' => (string) $qty, 'evidence' => count($evidence), 'cuts' => count($cuts), ...($duplicate ?? [])]), metadata: ['business_date' => $businessDate],
        );

        return $case;
    }

    /**
     * ROUTE-194's and ROUTE-195's pending branch's set (Part 4): each scope, the conversions' cases, the cut and the evidence.
     */
    public static function entryLocks(StockPicks $entry): LockSet
    {
        $set = StockPlaces::locks($entry->productId, [], $entry->cuts, $entry->evidence);

        foreach ($entry->pending as $pending) {
            $set = $set->union(self::scopeLocks($entry->productId, $pending['location']));
        }

        foreach ($entry->conversions as $conversion) {
            $location = DB::table('unattributed_loss_cases')->where('id', $conversion['case'])->value('location_id');
            $set->add('unattributed_loss_cases', ['id' => $conversion['case']]);

            if (is_numeric($location)) {
                $set = $set->union(self::scopeLocks($entry->productId, (int) $location));
            }
        }

        return $set;
    }

    /**
     * An unexplained change or loss recorded through PG-027 (QS-22; J-INV-04): a pending case, never a named loss; a condition-changed unit's disposal converts its case. The first movement.
     */
    public function recordEntry(CommandContext $context, StockPicks $entry, string $kind, ?int $case = null): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $context->require(self::entryLocks($entry));
        $scale = StockPlaces::ensurePolicy($entry);

        // A pending quantity names no lot and no case: what does goes as its own command.
        if ($entry->picks !== [] || $case !== null) {
            throw Refused::rejected('C-07', ['reason' => 'mixed']);
        }

        if ($entry->serializedSeen) {
            throw Refused::rejected('C-02', ['reason' => 'serial']);
        }

        $matches = [];
        $usable = BigDecimal::zero();

        foreach ($entry->pending as $pending) {
            $matches = [...$matches, ...self::likelyPending($entry->productId, $pending['location'], $kind === 'CONDITION_CHANGE' ? 'USABLE' : $pending['condition'], BigDecimal::of($pending['qty']), $entry->businessDate)];
            $usable = $kind === 'CONDITION_CHANGE' || $pending['condition'] === 'USABLE' ? $usable->plus($pending['qty']) : $usable;
        }

        DuplicateWarning::check('L-49', $matches, $entry->acknowledged, $entry->duplicateReason);
        $carried = false;
        $first = null;

        foreach ($entry->pending as $pending) {
            $case = $this->recognize($context, $entry->productId, $scale, $pending['location'], $kind, $kind === 'CONDITION_CHANGE' ? 'USABLE' : $pending['condition'], BigDecimal::of($pending['qty']), $entry->reason, $entry->businessDate, $entry->evidence, null, $entry->cuts, $carried ? null : (string) $usable, $context->request->commandType, $matches === [] ? null : ['matches' => $matches, 'duplicate_reason' => $entry->duplicateReason]);
            $carried = true;
            $first ??= $case;
        }

        foreach ($entry->conversions as $conversion) {
            $case = $this->convert($context, $conversion['case'], BigDecimal::of($conversion['qty']), $entry->reason, $entry->businessDate, $entry->evidence, null);
            $first ??= $case;
        }

        $movement = $first === null ? null : DB::table('unattributed_loss_cases')->where('id', $first)->value('recognition_movement_id');

        return is_numeric($movement) ? (int) $movement : throw Refused::rejected('C-03', ['reason' => 'nothing']);
    }

    /**
     * BD-13 on a pending case: the same scope, quantity and business date, still pending (L-49; QS-21).
     *
     * @return list<string>
     */
    public static function likelyPending(int $product, int $location, string $condition, BigDecimal $qty, string $businessDate): array
    {
        return array_values(array_map(fn ($id): string => is_string($id) ? $id : '', DB::table('unattributed_loss_cases as k')->join('stock_movements as m', 'm.id', '=', 'k.recognition_movement_id')->where('k.product_id', $product)->where('k.location_id', $location)->where('k.condition', $condition)->where('k.recognized_qty_base', (string) $qty)->where('k.business_date', $businessDate)->where('k.pending_qty_base', '>', 0)->orderBy('m.public_id')->pluck('m.public_id')->all()));
    }

    /** A later surplus reversing a case's pending quantity exactly: no lot or company touched, no loss ever recognized (UNATTRIBUTED_FOUND; DIR-027 item 11). */
    public function found(CommandContext $context, int $case, BigDecimal $qty, string $reason, string $businessDate, ?int $countId): int
    {
        $row = DB::table('unattributed_loss_cases')->where('id', $case)->first(['case_kind', 'product_id', 'quantity_scale', 'location_id', 'condition', 'pending_qty_base']);

        if ($row === null || Row::text($row, 'case_kind') !== 'LOSS' || $qty->isGreaterThan(Row::text($row, 'pending_qty_base'))) {
            throw Refused::rejected('C-35', ['reason' => 'found']);
        }

        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'UNATTRIBUTED_FOUND', 'business_date' => $businessDate, 'reason' => $reason, 'stock_count_id' => $countId, 'unattributed_case_id' => $case,
            'command_id' => $context->request->commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        $this->inventory->ledger($context, $movement, 'UNATTRIBUTED_FOUND', [[
            'lot' => null, 'case' => $case, 'product' => (int) Row::text($row, 'product_id'), 'scale' => (int) Row::text($row, 'quantity_scale'), 'location' => (int) Row::text($row, 'location_id'), 'condition' => Row::text($row, 'condition'),
            'qty' => (string) $qty, 'pending' => ['pending_reduction_qty' => (string) $qty->negated()],
        ]]);
        $context->connection->update('update unattributed_loss_cases set found_qty_base = found_qty_base + ?::numeric where id = ?', [(string) $qty, $case]);

        return $movement;
    }

    /**
     * A condition-changed unit's disposal (§5.8): one UNATTRIBUTED_CONVERSION, the old case's rows inverted for q and a new LOSS case carrying its snapshot, group and exposure; the new case. A case converts once (UNIQUE `converted_from_case_id`).
     *
     * @param  list<int>  $evidence
     */
    public function convert(CommandContext $context, int $old, BigDecimal $qty, string $reason, string $businessDate, array $evidence, ?int $countId): int
    {
        $row = DB::table('unattributed_loss_cases')->where('id', $old)->first(['case_kind', 'product_id', 'quantity_scale', 'location_id', 'exposure_qty_base', 'prior_pending_qty_base', 'exposure_group_case_id', 'pending_qty_base']);

        if ($row === null || Row::text($row, 'case_kind') !== 'CONDITION_CHANGE' || ! $qty->isPositive() || $qty->isGreaterThan(Row::text($row, 'pending_qty_base'))) {
            throw Refused::rejected('C-35', ['reason' => 'conversion']);
        }

        if (DB::table('unattributed_loss_cases')->where('converted_from_case_id', $old)->exists()) {
            throw Refused::rejected('C-35', ['reason' => 'converted']);
        }

        $product = (int) Row::text($row, 'product_id');
        $scale = (int) Row::text($row, 'quantity_scale');
        $location = (int) Row::text($row, 'location_id');
        [$movement, $case, $publicId] = $this->header($context, 'UNATTRIBUTED_CONVERSION', [
            'case_kind' => 'LOSS', 'product_id' => $product, 'quantity_scale' => $scale, 'location_id' => $location, 'condition' => 'USABLE', 'recognized_qty_base' => (string) $qty, 'exposure_qty_base' => Row::text($row, 'exposure_qty_base'), 'prior_pending_qty_base' => Row::text($row, 'prior_pending_qty_base'),
            'exposure_group_case_id' => (int) (Row::nullable($row, 'exposure_group_case_id') ?? $old), 'converted_from_case_id' => $old,
        ], $reason, $businessDate, $countId);
        $copied = [];

        foreach (DB::table('unattributed_loss_candidates')->where('unattributed_loss_case_id', $old)->orderBy('id')->get(['candidate_company_id', 'lot_id', 'qty_in_scope_at_recognition', 'net_unit_cost_at_recognition']) as $candidate) {
            $copied[] = ['company' => (int) Row::text($candidate, 'candidate_company_id'), 'lot' => (int) Row::text($candidate, 'lot_id'), 'qty' => Row::text($candidate, 'qty_in_scope_at_recognition'), 'cost' => Row::text($candidate, 'net_unit_cost_at_recognition')];
        }

        $exposures = [];

        foreach (DB::table('unattributed_loss_exposures')->where('unattributed_loss_case_id', $old)->get(['company_id', 'exposure_qty_base']) as $exposure) {
            $exposures[(int) Row::text($exposure, 'company_id')] = BigDecimal::of(Row::text($exposure, 'exposure_qty_base'));
        }

        $this->writeSnapshot($context, $case, $copied, $exposures);
        $base = ['lot' => null, 'product' => $product, 'scale' => $scale, 'location' => $location];
        $this->inventory->ledger($context, $movement, 'UNATTRIBUTED_CONVERSION', [
            [...$base, 'case' => $old, 'condition' => 'USABLE', 'qty' => (string) $qty, 'pending' => ['pending_reduction_qty' => (string) $qty->negated()]],
            [...$base, 'case' => $old, 'condition' => 'UNUSABLE', 'qty' => (string) $qty->negated(), 'pending' => ['pending_addition_qty' => (string) $qty->negated()]],
            [...$base, 'case' => $case, 'condition' => 'USABLE', 'qty' => (string) $qty->negated(), 'pending' => ['pending_reduction_qty' => (string) $qty]],
        ]);
        $context->connection->update('update unattributed_loss_cases set converted_qty_base = converted_qty_base + ?::numeric where id = ?', [(string) $qty, $old]);
        self::linkEvidence($context, $evidence, $case);
        $this->audit->record(
            action: 'INV.UnattributedConverted', authorityClass: AuthorityClass::AdmPlus, actorUserId: $context->request->actorUserId, companyId: null, entityType: 'unattributed_loss_case', entityId: $case, entityPublicId: $publicId, reason: $reason, commandId: $context->request->commandId,
            change: AuditChange::created(['quantity' => (string) $qty, 'from_case' => $old]), metadata: ['business_date' => $businessDate],
        );

        return $case;
    }

    /**
     * AX-37's set: the case and its group, the scope's guards, each closed lot's balance and cost guard, each bearer project, the evidence.
     *
     * @param  list<array{company: ?int, closes: list<array{lot: int, qty: string}>}>  $lines
     * @param  list<int>  $evidence
     */
    public static function resolutionLocks(int $case, array $lines, array $evidence): LockSet
    {
        $row = DB::table('unattributed_loss_cases')->where('id', $case)->first(['product_id', 'location_id', 'exposure_group_case_id', 'causal_project_id']);
        $set = (new LockSet)->add('unattributed_loss_cases', ['id' => $case]);

        if ($row === null) {
            return $set;
        }

        $product = (int) Row::text($row, 'product_id');
        $location = (int) Row::text($row, 'location_id');
        $set = $set->union(self::scopeLocks($product, $location));
        $founder = Row::nullable($row, 'exposure_group_case_id');
        $project = Row::nullable($row, 'causal_project_id');

        if ($founder !== null) {
            $set->add('unattributed_loss_cases', ['id' => (int) $founder]);
        }

        if ($project !== null) {
            $set->add('projects', ['id' => (int) $project]);
        }

        foreach ($lines as $line) {
            foreach ($line['closes'] as $close) {
                foreach (['USABLE', 'UNUSABLE'] as $condition) {
                    $set->add('stock_lot_balances', ['lot_id' => $close['lot'], 'location_id' => $location, 'condition' => $condition]);
                }

                $set->add('lot_cost_balances', ['lot_id' => $close['lot']]);
            }
        }

        foreach ($evidence as $id) {
            $set = $set->union(EvidenceGuard::locks($id));
        }

        return $set;
    }

    /**
     * An Owner's or an evidence resolution (AX-37): each line within its bounds, its closures on the assigned company's own lots first and a shortfall only from other lots the Owner chose, each with one allocation; SF-LOSS once at the closed claims' current net cost; the corrected attribution citing the one it replaces. The resolution.
     *
     * @param  list<array{company: int, closes: list<array{lot: int, qty: string}>}>  $lines
     */
    public function resolve(CommandContext $context, int $caseId, string $kind, array $lines, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $case = DB::table('unattributed_loss_cases')->where('id', $caseId)->first();

        if ($case === null) {
            throw Refused::rejected('C-14', ['reason' => 'case']);
        }

        $pending = BigDecimal::of(Row::text($case, 'pending_qty_base'));

        if (! $pending->isPositive()) {
            throw Refused::conflict('ST-02', ['state' => 'resolved']);
        }

        $product = (int) Row::text($case, 'product_id');
        $scale = (int) Row::text($case, 'quantity_scale');
        $location = (int) Row::text($case, 'location_id');
        $condition = Row::text($case, 'condition');
        $caseKind = Row::text($case, 'case_kind');
        $group = (int) (Row::nullable($case, 'exposure_group_case_id') ?? $caseId);
        $causal = Row::nullable($case, 'causal_project_id');
        $causalCompany = $causal === null ? null : DB::table('projects')->where('id', (int) $causal)->value('company_id');
        $assigned = BigDecimal::zero();
        $plan = [];

        foreach ($lines as $line) {
            $total = array_reduce($line['closes'], fn (BigDecimal $sum, array $close): BigDecimal => $sum->plus($close['qty']), BigDecimal::zero());

            if (! $total->isPositive()) {
                throw Refused::rejected('C-37', ['reason' => 'nothing']);
            }

            $this->ensureBounds($caseId, $group, $line['company'], $total);
            $plan[] = self::closures($line, $product, $location, $condition, $total, $caseKind);
            $assigned = $assigned->plus($total);
        }

        if ($assigned->isGreaterThan($pending)) {
            throw Refused::rejected('C-35', ['reason' => 'above_pending', 'pending' => (string) $pending]);
        }

        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $now = $context->now();
        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'UNATTRIBUTED_RESOLUTION', 'business_date' => $businessDate, 'reason' => $reason, 'unattributed_case_id' => $caseId,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        // The corrected attribution after an exact reversal cites what it replaces (CM-27).
        $supersedes = DB::table('unattributed_loss_resolutions as r')->join('stock_movements as x', 'x.reverses_movement_id', '=', 'r.stock_movement_id')->where('r.unattributed_loss_case_id', $caseId)
            ->whereNotExists(fn (Builder $later) => $later->from('unattributed_loss_resolutions as n')->whereColumn('n.supersedes_resolution_id', 'r.id'))->orderByDesc('r.id')->value('r.id');
        $resolution = (int) DB::table('unattributed_loss_resolutions')->insertGetId([
            'unattributed_loss_case_id' => $caseId, 'resolution_kind' => $kind, 'stock_movement_id' => $movement, 'supersedes_resolution_id' => is_numeric($supersedes) ? (int) $supersedes : null, 'decided_by' => $actor, 'business_date' => $businessDate, 'reason' => $reason,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $base = ['product' => $product, 'scale' => $scale, 'location' => $location];
        $rows = [[...$base, 'lot' => null, 'case' => $caseId, 'condition' => $condition, 'qty' => (string) $assigned, 'pending' => ['pending_reduction_qty' => (string) $assigned->negated()]]];

        if ($caseKind === 'CONDITION_CHANGE') {
            $rows[] = [...$base, 'lot' => null, 'case' => $caseId, 'condition' => 'UNUSABLE', 'qty' => (string) $assigned->negated(), 'pending' => ['pending_addition_qty' => (string) $assigned->negated()]];
        }

        $consumptions = [];
        $number = 0;
        $consumed = 0;

        foreach ($lines as $index => $line) {
            $company = $line['company'];
            $total = $plan[$index]['total'];
            // The bearer: the causal project only where it is the assigned company's, otherwise the company (L-38; C-32).
            $bearer = $causal !== null && is_numeric($causalCompany) && (int) $causalCompany === $company ? (int) $causal : null;
            $lineId = (int) DB::table('unattributed_loss_resolution_lines')->insertGetId([
                'unattributed_loss_resolution_id' => $resolution, 'unattributed_loss_case_id' => $caseId, 'product_id' => $product, 'quantity_scale' => $scale, 'assigned_company_id' => $company, 'assigned_qty_base' => (string) $total, 'bearer_project_id' => $bearer,
                'command_id' => $commandId, 'command_line_no' => ++$number, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $context->connection->update('update unattributed_loss_exposures set assigned_net_qty_base = assigned_net_qty_base + ?::numeric where unattributed_loss_case_id = ? and company_id = ?', [(string) $total, $caseId, $company]);
            $context->connection->update('update unattributed_loss_group_exposures set assigned_net_qty_base = assigned_net_qty_base + ?::numeric where exposure_group_case_id = ? and company_id = ?', [(string) $total, $group, $company]);

            foreach ($plan[$index]['closes'] as $close) {
                $row = [...$base, 'lot' => $close['lot'], 'condition' => $condition, 'qty' => (string) BigDecimal::of($close['qty'])->negated()];

                if ($caseKind === 'CONDITION_CHANGE') {
                    // A condition-change case takes the units' condition from the lot, and creates no consumption (§5.8).
                    $rows[] = $row;
                    $rows[] = [...$base, 'lot' => $close['lot'], 'condition' => 'UNUSABLE', 'qty' => $close['qty']];

                    continue;
                }

                $crossed = $close['source'] !== $company;
                $consumption = (int) DB::table('stock_consumptions')->insertGetId([
                    'stock_movement_id' => $movement, 'lot_id' => $close['lot'], 'source_company_id' => $close['source'], 'product_id' => $product, 'quantity_scale' => $scale, 'qty_base' => $close['qty'], 'kind' => 'UNATTRIBUTED_LOSS',
                    'bearer_company_id' => $company, 'bearer_project_id' => $bearer, 'allocation_reason' => $crossed ? $reason : null, 'allocation_authorized_by' => $crossed ? $actor : null, 'unattributed_resolution_line_id' => $lineId,
                    'command_id' => $commandId, 'command_line_no' => ++$consumed, 'recorded_at' => $now, 'recorded_by' => $actor,
                ]);
                $context->connection->table('stock_consumption_balances')->insert(['stock_consumption_id' => $consumption, 'qty_base' => $close['qty']]);
                $consumptions[] = $consumption;
                $rows[] = [...$row, 'consumption' => $consumption];
            }
        }

        $this->inventory->ledger($context, $movement, 'UNATTRIBUTED_RESOLUTION', $rows);
        $context->connection->update('update unattributed_loss_cases set resolved_qty_base = resolved_qty_base + ?::numeric where id = ?', [(string) $assigned, $caseId]);

        foreach ($consumptions as $consumption) {
            $this->cost->draw($context, $consumption, $businessDate);
        }

        $this->audit->record(
            action: 'INV.UnattributedResolved', authorityClass: $kind === 'EVIDENCE_IDENTIFIED' ? AuthorityClass::AdmPlus : AuthorityClass::OwnerOnly, actorUserId: $actor, companyId: count($lines) === 1 ? $lines[0]['company'] : null, entityType: 'unattributed_loss_case', entityId: $caseId, entityPublicId: Row::text($case, 'public_id'), reason: $reason, commandId: $commandId,
            change: AuditChange::updated(['pending' => (string) $pending], ['pending' => (string) $pending->minus($assigned), 'kind' => $kind, 'lines' => array_map(fn (array $line): array => ['company' => $line['company'], 'lots' => $line['closes']], $lines)]), metadata: ['business_date' => $businessDate],
        );

        return $resolution;
    }

    /**
     * OD-10's conditions, each refused by its letter: the reason; a later, authentic pool proof the actor may see linked to the case; every unit named by lot, each a candidate, all of one source company, which becomes the assigned company; each lot still holding its quantity; that company in the actor's scope. (f) is resolve()'s bounds. The assigned company.
     *
     * @param  list<array{lot: int, qty: string}>  $closes
     * @param  list<int>  $evidence
     */
    public function ensureEvidence(CommandContext $context, int $caseId, array $closes, array $evidence, string $reason): int
    {
        if (trim($reason) === '') {
            throw Refused::rejected('OD-10', ['condition' => 'a']);
        }

        $case = DB::table('unattributed_loss_cases')->where('id', $caseId)->first(['location_id', 'condition', 'recorded_at']);

        if ($case === null) {
            throw Refused::rejected('C-14', ['reason' => 'case']);
        }

        self::linkEvidence($context, $evidence, $caseId);

        if (! self::laterProof($context, $caseId, Row::text($case, 'recorded_at'))) {
            throw Refused::rejected('OD-10', ['condition' => 'b']);
        }

        if ($closes === []) {
            throw Refused::rejected('OD-10', ['condition' => 'c']);
        }

        $companies = [];

        foreach ($closes as $close) {
            $candidate = DB::table('unattributed_loss_candidates')->where('unattributed_loss_case_id', $caseId)->where('lot_id', $close['lot'])->value('candidate_company_id');

            if (! is_numeric($candidate)) {
                throw Refused::rejected('OD-10', ['condition' => 'c']);
            }

            $companies[(int) $candidate] = true;
            $held = DB::table('stock_lot_balances as lb')->where('lot_id', $close['lot'])->where('location_id', (int) Row::text($case, 'location_id'))->where('condition', Row::text($case, 'condition'))->value('qty');

            if (! is_string($held) || BigDecimal::of($close['qty'])->isGreaterThan($held)) {
                throw Refused::rejected('OD-10', ['condition' => 'd', 'available' => is_string($held) ? $held : '0']);
            }
        }

        if (count($companies) !== 1) {
            throw Refused::rejected('OD-10', ['condition' => 'c']);
        }

        $company = (int) array_key_first($companies);
        $authority = $context->authority();

        if ($authority === null || ! $authority->scope->includes($company)) {
            throw Refused::rejected('OD-10', ['condition' => 'e']);
        }

        return $company;
    }

    /**
     * The exact reversal of a resolution, at most once (CM-27; C-35; C-37): LOSS_REVERSAL restorations and contras of what is still open, the same pending quantity re-opened with the snapshot and every later movement unchanged, the bounds given back.
     */
    public function reverse(CommandContext $context, int $resolutionId, string $reason, string $businessDate, bool $prefilled): int
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $resolution = DB::table('unattributed_loss_resolutions')->where('id', $resolutionId)->first(['stock_movement_id', 'unattributed_loss_case_id', 'resolution_kind']);

        if ($resolution === null) {
            throw Refused::rejected('C-14', ['reason' => 'resolution']);
        }

        $original = (int) Row::text($resolution, 'stock_movement_id');
        $caseId = (int) Row::text($resolution, 'unattributed_loss_case_id');
        // A reversal needs the company it charged in scope, otherwise refused and routed (AZ-08; XL-1).
        $parts = array_map(fn ($company): array => ['company' => is_numeric($company) ? (int) $company : 0, 'source' => is_numeric($company) ? (int) $company : 0], DB::table('unattributed_loss_resolution_lines')->where('unattributed_loss_resolution_id', $resolutionId)->pluck('assigned_company_id')->all());
        $this->losses->ensureBearers($context, array_values($parts), null, 0);

        if (DB::table('stock_movements')->where('reverses_movement_id', $original)->where('movement_type', 'UNATTRIBUTED_RESOLUTION_REVERSAL')->exists()) {
            throw Refused::rejected('C-37', ['reason' => 'reversed']);
        }

        $case = DB::table('unattributed_loss_cases')->where('id', $caseId)->first();

        if ($case === null) {
            throw Refused::rejected('C-14', ['reason' => 'case']);
        }

        $product = (int) Row::text($case, 'product_id');
        $scale = (int) Row::text($case, 'quantity_scale');
        $location = (int) Row::text($case, 'location_id');
        $condition = Row::text($case, 'condition');
        $group = (int) (Row::nullable($case, 'exposure_group_case_id') ?? $caseId);
        $publicId = (string) Str::uuid7();
        $commandId = $context->request->commandId;
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'UNATTRIBUTED_RESOLUTION_REVERSAL', 'business_date' => $businessDate, 'reason' => $reason, 'unattributed_case_id' => $caseId, 'reverses_movement_id' => $original, 'reverses_movement_type' => 'UNATTRIBUTED_RESOLUTION',
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        $base = ['product' => $product, 'scale' => $scale, 'location' => $location];
        $rows = [];
        $given = [];
        $items = [];

        foreach (DB::table('stock_consumptions')->where('stock_movement_id', $original)->orderBy('id')->pluck('id') as $id) {
            $consumption = is_numeric($id) ? (int) $id : 0;
            $found = LossRestorations::of($consumption);

            if ($found !== null && $found['open']->isPositive()) {
                $items[] = ['consumption' => $consumption, 'qty' => (string) $found['open']];
                $given[$found['bearer']] = ($given[$found['bearer']] ?? BigDecimal::zero())->plus($found['open']);
            }
        }

        $rows = $this->restorations->restore($context, $movement, $items, $businessDate);
        $total = BigDecimal::zero();

        if (Row::text($case, 'case_kind') === 'CONDITION_CHANGE') {
            foreach (DB::table('stock_movement_entries')->where('stock_movement_id', $original)->whereNotNull('lot_id')->orderBy('id')->get(['lot_id', 'condition', 'qty_delta_base']) as $entry) {
                $rows[] = [...$base, 'lot' => (int) Row::text($entry, 'lot_id'), 'condition' => Row::text($entry, 'condition'), 'qty' => (string) BigDecimal::of(Row::text($entry, 'qty_delta_base'))->negated()];
            }

            foreach (DB::table('unattributed_loss_resolution_lines')->where('unattributed_loss_resolution_id', $resolutionId)->get(['assigned_company_id', 'assigned_qty_base']) as $line) {
                $company = (int) Row::text($line, 'assigned_company_id');
                $given[$company] = ($given[$company] ?? BigDecimal::zero())->plus(Row::text($line, 'assigned_qty_base'));
            }
        }

        foreach ($given as $qty) {
            $total = $total->plus($qty);
        }

        if (! $total->isPositive()) {
            throw Refused::rejected('C-37', ['reason' => 'nothing_open']);
        }

        $rows[] = [...$base, 'lot' => null, 'case' => $caseId, 'condition' => $condition, 'qty' => (string) $total->negated(), 'pending' => ['pending_reduction_qty' => (string) $total]];

        if (Row::text($case, 'case_kind') === 'CONDITION_CHANGE') {
            $rows[] = [...$base, 'lot' => null, 'case' => $caseId, 'condition' => 'UNUSABLE', 'qty' => (string) $total, 'pending' => ['pending_addition_qty' => (string) $total]];
        }

        $this->inventory->ledger($context, $movement, 'UNATTRIBUTED_RESOLUTION_REVERSAL', $rows);
        $context->connection->update('update unattributed_loss_cases set resolved_qty_base = resolved_qty_base - ?::numeric where id = ?', [(string) $total, $caseId]);

        foreach ($given as $company => $qty) {
            $context->connection->update('update unattributed_loss_exposures set assigned_net_qty_base = assigned_net_qty_base - ?::numeric where unattributed_loss_case_id = ? and company_id = ?', [(string) $qty, $caseId, $company]);
            $context->connection->update('update unattributed_loss_group_exposures set assigned_net_qty_base = assigned_net_qty_base - ?::numeric where exposure_group_case_id = ? and company_id = ?', [(string) $qty, $group, $company]);
        }

        $kind = Row::text($resolution, 'resolution_kind');
        $this->corrections->record($context, 'INV.UnattributedResolutionReversed', $kind === 'EVIDENCE_IDENTIFIED' ? AuthorityClass::AdmPlus : AuthorityClass::OwnerOnly, null, 'unattributed_loss_case', $caseId, Row::text($case, 'public_id'), AuditRelation::Reverses, 'stock_movement', self::text(DB::table('stock_movements')->where('id', $original)->value('public_id')), $reason,
            AuditChange::updated(['resolved' => (string) $total], ['pending_reopened' => (string) $total]), null, ['business_date' => $businessDate]);

        return $movement;
    }

    /**
     * AX-37's reversal set: the case and its group, the scope, the resolution's lots, its consumptions' guards and bearers.
     */
    public static function reversalLocks(int $resolutionId): LockSet
    {
        $resolution = DB::table('unattributed_loss_resolutions')->where('id', $resolutionId)->first(['stock_movement_id', 'unattributed_loss_case_id']);

        if ($resolution === null) {
            return new LockSet;
        }

        $caseId = (int) Row::text($resolution, 'unattributed_loss_case_id');
        $movement = (int) Row::text($resolution, 'stock_movement_id');
        $case = DB::table('unattributed_loss_cases')->where('id', $caseId)->first(['location_id', 'exposure_group_case_id']);
        $closes = [];

        foreach (DB::table('stock_movement_entries')->where('stock_movement_id', $movement)->whereNotNull('lot_id')->distinct()->orderBy('lot_id')->pluck('lot_id') as $lot) {
            $closes[] = ['lot' => is_numeric($lot) ? (int) $lot : 0, 'qty' => '0'];
        }

        $set = self::resolutionLocks($caseId, [['company' => null, 'closes' => $closes]], []);
        $consumptions = array_values(array_map(fn ($id): int => is_numeric($id) ? (int) $id : 0, DB::table('stock_consumptions')->where('stock_movement_id', $movement)->orderBy('id')->pluck('id')->all()));

        return $case === null ? $set : $set->union(LossRestorations::locks($consumptions));
    }

    /** C-37 read before the guards refuse it, with the figure the page says. */
    private function ensureBounds(int $case, int $group, int $company, BigDecimal $qty): void
    {
        $exposure = DB::table('unattributed_loss_exposures')->where('unattributed_loss_case_id', $case)->where('company_id', $company)->first(['exposure_qty_base', 'assigned_net_qty_base']);

        if ($exposure === null) {
            throw Refused::rejected('C-37', ['reason' => 'not_candidate']);
        }

        $bound = DB::table('unattributed_loss_group_exposures')->where('exposure_group_case_id', $group)->where('company_id', $company)->first(['bound_qty_base', 'assigned_net_qty_base']);

        if ($bound === null || ! CountVariance::withinBounds($qty, BigDecimal::of(Row::text($exposure, 'exposure_qty_base')), BigDecimal::of(Row::text($exposure, 'assigned_net_qty_base')), BigDecimal::of(Row::text($bound, 'bound_qty_base')), BigDecimal::of(Row::text($bound, 'assigned_net_qty_base')))) {
            throw Refused::rejected('C-37', ['reason' => 'above_bound']);
        }
    }

    /**
     * A line's closures read under the locks: each lot holds its quantity in the scope; the assigned company's own lots first, another's only for what they cannot hold; a condition-change case only on the company's own usable claims (DIR-027; L-44).
     *
     * @param  array{company: int, closes: list<array{lot: int, qty: string}>}  $line
     * @return array{total: BigDecimal, closes: list<array{lot: int, qty: string, source: int}>}
     */
    private static function closures(array $line, int $product, int $location, string $condition, BigDecimal $total, string $caseKind): array
    {
        $closes = [];
        $own = BigDecimal::zero();
        $other = false;
        $seen = [];

        foreach ($line['closes'] as $close) {
            $source = DB::table('inventory_lots')->where('id', $close['lot'])->where('product_id', $product)->value('source_company_id');
            $held = DB::table('stock_lot_balances as lb')->where('lot_id', $close['lot'])->where('location_id', $location)->where('condition', $condition)->value('qty');
            $seen[$close['lot']] = BigDecimal::of($seen[$close['lot']] ?? '0')->plus($close['qty']);

            if (! is_numeric($source) || ! is_string($held) || $seen[$close['lot']]->isGreaterThan($held) || ! BigDecimal::of($close['qty'])->isPositive()) {
                throw Refused::rejected('C-37', ['reason' => 'lot_short', 'available' => is_string($held) ? $held : '0']);
            }

            if ((int) $source === $line['company']) {
                $own = $own->plus($close['qty']);
            } else {
                $other = true;
            }

            $closes[] = ['lot' => $close['lot'], 'qty' => $close['qty'], 'source' => (int) $source];
        }

        if ($other) {
            if ($caseKind === 'CONDITION_CHANGE') {
                throw Refused::rejected('C-37', ['reason' => 'condition_own_only']);
            }

            $claims = DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->where('b.product_id', $product)->where('b.location_id', $location)->where('b.condition', $condition)->where('i.source_company_id', $line['company'])->sum('b.qty');

            if (BigDecimal::of((string) $claims)->isGreaterThan($own)) {
                throw Refused::rejected('C-37', ['reason' => 'own_first', 'own' => (string) $claims]);
            }
        }

        return ['total' => $total, 'closes' => $closes];
    }

    /**
     * Every lot holding claims in the scope now, with its current net unit cost; each company's exposure; their sum.
     *
     * @return array{list<array{company: int, lot: int, qty: string, cost: string}>, array<int, BigDecimal>, BigDecimal}
     */
    private static function snapshot(int $product, int $location, string $condition): array
    {
        $candidates = [];
        $exposures = [];
        $total = BigDecimal::zero();

        foreach (DB::table('stock_lot_balances as b')->join('inventory_lots as i', 'i.id', '=', 'b.lot_id')->leftJoin('lot_cost_balances as c', 'c.lot_id', '=', 'b.lot_id')->where('b.product_id', $product)->where('b.location_id', $location)->where('b.condition', $condition)->where('b.qty', '>', 0)->orderBy('b.lot_id')
            ->get(['b.lot_id', 'b.qty', 'i.source_company_id', 'c.remaining_cost', 'c.remaining_qty_base']) as $row) {
            $company = (int) Row::text($row, 'source_company_id');
            $qty = BigDecimal::of(Row::text($row, 'qty'));
            $remaining = BigDecimal::of(Row::nullable($row, 'remaining_qty_base') ?? '0');
            $cost = $remaining->isPositive() ? BigDecimal::of(Row::nullable($row, 'remaining_cost') ?? '0')->dividedBy($remaining, 2, RoundingMode::HalfUp) : BigDecimal::zero();
            $candidates[] = ['company' => $company, 'lot' => (int) Row::text($row, 'lot_id'), 'qty' => (string) $qty, 'cost' => (string) $cost];
            $exposures[$company] = ($exposures[$company] ?? BigDecimal::zero())->plus($qty);
            $total = $total->plus($qty);
        }

        return [$candidates, $exposures, $total];
    }

    /**
     * The header naming its case and the case naming its header: both keys immediate and the header never updated, so one statement writes them (§5.8; C-07).
     *
     * @param  array<string, mixed>  $case
     * @return array{int, int, string} the movement, the case and the case's public id
     */
    private function header(CommandContext $context, string $type, array $case, string $reason, string $businessDate, ?int $countId): array
    {
        // C-36 reads the snapshot written with the case: deferred, held to the commit's `set constraints all immediate` whatever an earlier statement set.
        $context->connection->statement('set constraints unattributed_loss_cases_c36_snapshot_sums, unattributed_loss_candidates_c36_snapshot_sums, unattributed_loss_exposures_c36_snapshot_sums deferred');
        $movement = self::nextId('stock_movements');
        $id = self::nextId('unattributed_loss_cases');
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $publicId = (string) Str::uuid7();
        $row = [...$case, 'id' => $id, 'public_id' => $publicId, 'recognition_movement_id' => $movement, 'business_date' => $businessDate, 'recognized_by' => $actor, 'reason' => $reason, 'command_id' => $commandId, 'command_line_no' => InventoryPosting::lineNo('unattributed_loss_cases', $context), 'recorded_at' => $now, 'recorded_by' => $actor];
        $header = [$movement, (string) Str::uuid7(), $type, $businessDate, $reason, $countId, $id, $commandId, InventoryPosting::lineNo('stock_movements', $context), $now, $actor];
        $context->connection->statement('with header as (insert into stock_movements (id, public_id, movement_type, business_date, reason, stock_count_id, unattributed_case_id, command_id, command_line_no, recorded_at, recorded_by) overriding system value values (?, ?::uuid, ?, ?::date, ?, ?, ?, ?::uuid, ?, ?, ?)) insert into unattributed_loss_cases ('.implode(', ', array_keys($row)).') overriding system value values ('.implode(', ', array_fill(0, count($row), '?')).')', [...$header, ...array_values($row)]);

        return [$movement, $id, $publicId];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function nextId(string $table): int
    {
        $row = DB::selectOne("select nextval(pg_get_serial_sequence('{$table}', 'id')) as id");

        return is_object($row) ? (int) Row::text($row, 'id') : 0;
    }

    /**
     * The immutable snapshot's rows, insertable only here (C-36).
     *
     * @param  list<array{company: int, lot: int, qty: string, cost: string}>  $candidates
     * @param  array<int, BigDecimal>  $exposures
     */
    private function writeSnapshot(CommandContext $context, int $case, array $candidates, array $exposures): void
    {
        $now = $context->now();

        foreach ($candidates as $candidate) {
            DB::table('unattributed_loss_candidates')->insert(['unattributed_loss_case_id' => $case, 'candidate_company_id' => $candidate['company'], 'lot_id' => $candidate['lot'], 'qty_in_scope_at_recognition' => $candidate['qty'], 'net_unit_cost_at_recognition' => $candidate['cost'], 'recorded_at' => $now]);
        }

        foreach ($exposures as $company => $qty) {
            DB::table('unattributed_loss_exposures')->insert(['unattributed_loss_case_id' => $case, 'company_id' => $company, 'exposure_qty_base' => (string) $qty, 'recorded_at' => $now]);
        }
    }

    /**
     * Each company's group bound raised at a member's recognition, never lowered (FU-06; C-37).
     *
     * @param  array<int, BigDecimal>  $exposures
     */
    private function raiseGroup(CommandContext $context, int $group, array $exposures): void
    {
        foreach ($exposures as $company => $qty) {
            $context->connection->table('unattributed_loss_group_exposures')->insertOrIgnore(['exposure_group_case_id' => $group, 'company_id' => $company, 'bound_qty_base' => '0', 'recorded_at' => $context->now()]);
            $row = DB::table('unattributed_loss_group_exposures')->where('exposure_group_case_id', $group)->where('company_id', $company)->first(['bound_qty_base', 'assigned_net_qty_base']);
            $bound = CountVariance::groupBound(BigDecimal::of($row === null ? '0' : Row::text($row, 'bound_qty_base')), $qty, BigDecimal::of($row === null ? '0' : Row::text($row, 'assigned_net_qty_base')));
            $context->connection->update('update unattributed_loss_group_exposures set bound_qty_base = ?::numeric where exposure_group_case_id = ? and company_id = ?', [(string) $bound, $group, $company]);
        }
    }

    /**
     * SF-EVIDENCE on the case: pool evidence only (C-58; §4.10).
     *
     * @param  list<int>  $evidence
     */
    private static function linkEvidence(CommandContext $context, array $evidence, int $case): void
    {
        foreach (array_values(array_unique($evidence)) as $number => $id) {
            $scope = DB::table('evidence_documents')->where('id', $id)->value('evidence_scope');

            if ($scope !== 'POOL') {
                throw Refused::rejected('C-58', ['reason' => 'pool_only']);
            }

            DB::table('evidence_links')->insert(['evidence_document_id' => $id, 'evidence_scope' => 'POOL', 'company_id' => null, 'role' => 'PRIMARY_PROOF', 'unattributed_loss_case_id' => $case, 'command_id' => $context->request->commandId, 'command_line_no' => InventoryPosting::lineNo('evidence_links', $context), 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
        }
    }

    /** OD-10 (b): a pool proof linked to the case with its role and still linked, recorded or versioned after the recognition, the actor may see it, and it is no rendition the company issued. */
    private static function laterProof(CommandContext $context, int $case, string $recognizedAt): bool
    {
        $authority = $context->authority();

        foreach (DB::table('evidence_links as l')->join('evidence_documents as e', 'e.id', '=', 'l.evidence_document_id')->leftJoin('evidence_document_versions as v', 'v.id', '=', 'e.current_version_id')->where('l.unattributed_loss_case_id', $case)->where('l.role', 'PRIMARY_PROOF')->whereNull('l.unlinked_at')->where('e.evidence_scope', 'POOL')->get(['e.id', 'e.evidence_type', 'e.recorded_at', 'v.recorded_at as versioned_at']) as $row) {
            $later = Row::text($row, 'recorded_at') > $recognizedAt || (Row::nullable($row, 'versioned_at') ?? '') > $recognizedAt;

            if ($later && $authority !== null && EvidenceTypes::mayView($authority, Row::text($row, 'evidence_type'), null) && ! EvidenceGuard::companyIssued([(int) Row::text($row, 'id')])) {
                return true;
            }
        }

        return false;
    }
}
