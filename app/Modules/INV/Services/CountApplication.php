<?php

namespace App\Modules\INV\Services;

use App\Modules\INV\Data\CountEntry;
use App\Modules\INV\Data\CountVariance;
use App\Modules\INV\Data\StockPicks;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Terapkan Selisih" (ROUTE-193; AX-06; WF-INV-05; L-14; L-15; L-42; BR-INV-07): every counted scope re-read under its lock — a scope moved after the count started makes it stale with nothing overwritten (ST-03; C-33) —; a shortage named by lot and lost once through SF-LOSS, confirmed by the actor where its lots are one company's, a pending case where they are several's; a surplus reversing the pending quantity first, then the identified losses, and otherwise one open finding whose units are not added to stock (C-34). A registration count registers a serial on every unit in one movement that changes no quantity and no cost (`DIR-101` F2). A count is never applied as an absolute number (FS-11).
 */
final class CountApplication
{
    public const COMMAND = 'INV.ApplyCountVariance';

    public function __construct(
        private readonly LossPosting $losses,
        private readonly UnattributedCases $cases,
        private readonly LossRestorations $restorations,
        private readonly InventoryPosting $inventory,
        private readonly ReceivingRecords $receiving,
        private readonly AuditRecorder $audit,
    ) {}

    /** AX-06's set, re-derived under the locks (§5): the count, its scopes and pending cases, the open finding, the losses named and derived with their bearers, the serials, the cut and the evidence. */
    public function locks(CountEntry $entry): LockSet
    {
        $set = (new LockSet)->add('stock_counts', ['id' => $entry->countId]);
        $count = DB::table('stock_counts')->where('id', $entry->countId)->first(['product_ids', 'purpose']);

        if ($count === null) {
            return $set;
        }

        $product = StockCounts::productOf($count);
        $set->add('stock_product_balances', ['product_id' => $product]);

        foreach (DB::table('stock_count_scopes')->where('stock_count_id', $entry->countId)->orderBy('location_id')->orderBy('condition')->get(['location_id', 'condition']) as $scope) {
            $location = (int) Row::text($scope, 'location_id');
            $condition = Row::text($scope, 'condition');
            $set = $set->union(UnattributedCases::scopeLocks($product, $location));
            $finding = DB::table('stock_count_findings')->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->where('state', 'OPEN')->value('id');

            if (is_numeric($finding)) {
                $set->add('stock_count_findings', ['id' => (int) $finding]);
            }

            if (Row::text($count, 'purpose') === 'SERIAL_REGISTRATION') {
                foreach (DB::table('stock_lot_balances as lb')->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->where('qty', '>', 0)->orderBy('lot_id')->pluck('lot_id') as $lot) {
                    $set->add('stock_lot_balances', ['lot_id' => is_numeric($lot) ? (int) $lot : 0, 'location_id' => $location, 'condition' => $condition]);
                }
            }
        }

        if (Row::text($count, 'purpose') === 'SERIAL_REGISTRATION') {
            $set->add('products', ['id' => $product]);

            foreach (DB::table('stock_count_lines as l')->leftJoin('serial_units as s', 's.id', '=', 'l.serial_unit_id')->where('l.stock_count_id', $entry->countId)->orderBy('l.id')->get(['l.unregistered_serial_text', 's.serial_key']) as $line) {
                $key = Row::nullable($line, 'serial_key') ?? ReceivingRecords::serialKey(Row::nullable($line, 'unregistered_serial_text') ?? '');
                $set->add('serial_units', ['product_id' => $product, 'serial_key' => $key]);
            }

            return $set;
        }

        $picks = [...$entry->picks, ...self::missingSerials($entry->countId, $product)];
        $set = $set->union($this->losses->locks(self::picks($entry, $product, $picks), null));

        foreach ($entry->conversions as $conversion) {
            $set->add('unattributed_loss_cases', ['id' => $conversion['case']]);
        }

        $consumptions = [...array_map(fn (array $item): int => $item['consumption'], $entry->restored), ...array_map(fn (array $item): int => $item['consumption'], self::lostSerials($entry->countId, $product))];
        $set = $set->union(LossRestorations::locks($consumptions));

        foreach ($consumptions as $consumption) {
            $found = LossRestorations::of($consumption);

            if ($found !== null) {
                foreach (['USABLE', 'UNUSABLE'] as $condition) {
                    $set->add('stock_lot_balances', ['lot_id' => $found['lot'], 'location_id' => $found['location'], 'condition' => $condition]);
                }
            }
        }

        foreach (DB::table('stock_count_lines as l')->join('serial_units as s', 's.id', '=', 'l.serial_unit_id')->where('l.stock_count_id', $entry->countId)->orderBy('s.serial_key')->pluck('s.serial_key') as $key) {
            $set->add('serial_units', ['product_id' => $product, 'serial_key' => is_string($key) ? $key : '']);
        }

        return $set;
    }

    /** ROUTE-193; the count. */
    public function apply(CommandContext $context, CountEntry $entry): int
    {
        BusinessDateRule::check($entry->businessDate, $entry->prefilled);
        $context->require($this->locks($entry));
        $count = DB::table('stock_counts')->where('id', $entry->countId)->first(['id', 'public_id', 'purpose', 'state', 'location_id', 'condition', 'product_ids']);

        if ($count === null) {
            throw Refused::rejected('C-14', ['reason' => 'count']);
        }

        // Applied once; a stale one only recounted (C-33).
        if (Row::text($count, 'state') !== 'OPEN') {
            throw Refused::conflict(Row::text($count, 'state') === 'STALE' ? 'ST-03' : 'ST-02', ['state' => Row::text($count, 'state')]);
        }

        $product = StockCounts::productOf($count);
        $publicId = Row::text($count, 'public_id');
        $this->ensureFresh($context, $entry->countId, $product, $publicId);
        $policy = StockPlaces::ensurePolicy(self::picks($entry, $product, []));

        if (Row::text($count, 'purpose') === 'SERIAL_REGISTRATION') {
            // The serial policy is `products.edit`'s (`DIR-101`).
            if ($context->authority()?->has('products.edit') !== true) {
                throw Refused::rejected('AZ-08', ['reason' => 'products_edit']);
            }

            $this->registration($context, $entry, $product);
        } else {
            $this->ordinary($context, $entry, $count, $product, $policy);
        }

        if (DB::table('stock_counts')->where('id', $entry->countId)->where('state', 'OPEN')->update(['state' => 'APPLIED', 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $context->now()]) !== 1) {
            throw Refused::conflict('ST-02', ['state' => 'changed']);
        }

        return $entry->countId;
    }

    /** ST-03: a moved scope commits only the count's OPEN → STALE and its SYSTEM event, after the rollback; stale never reverts (C-33). */
    private function ensureFresh(CommandContext $context, int $count, int $product, string $publicId): void
    {
        foreach (DB::table('stock_count_scopes')->where('stock_count_id', $count)->get(['location_id', 'condition', 'basis_version']) as $scope) {
            $live = DB::table('stock_scope_balances as sb')->where('product_id', $product)->where('location_id', (int) Row::text($scope, 'location_id'))->where('condition', Row::text($scope, 'condition'))->value('version');

            if ((int) (is_numeric($live) ? $live : 0) !== (int) Row::text($scope, 'basis_version')) {
                $actor = $context->request->actorUserId;
                $command = $context->request->commandId;

                throw Refused::conflict('ST-03', ['state' => 'STALE'], function (ConnectionInterface $connection) use ($count, $publicId, $actor, $command): void {
                    if ($connection->table('stock_counts')->where('id', $count)->where('state', 'OPEN')->update(['state' => 'STALE', 'lock_version' => DB::raw('lock_version + 1')]) === 1) {
                        $this->audit->record(action: 'INV.CountStale', authorityClass: AuthorityClass::System, actorUserId: $actor, companyId: null, entityType: 'stock_count', entityId: $count, entityPublicId: $publicId, commandId: $command, change: AuditChange::updated(['state' => 'OPEN'], ['state' => 'STALE']), connection: $connection);
                    }
                });
            }
        }
    }

    private function ordinary(CommandContext $context, CountEntry $entry, object $count, int $product, int $scale): void
    {
        $location = (int) Row::text($count, 'location_id');
        $condition = Row::text($count, 'condition');
        $scope = DB::table('stock_count_scopes')->where('stock_count_id', $entry->countId)->where('location_id', $location)->where('condition', $condition)->first(['system_qty_at_start']);

        if ($scope === null || ! DB::table('stock_count_lines')->where('stock_count_id', $entry->countId)->exists()) {
            throw Refused::rejected('C-33', ['reason' => 'no_lines']);
        }

        $open = DB::table('stock_count_findings')->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->where('state', 'OPEN')->first(['id', 'stock_count_id', 'surplus_qty_base']);
        $finding = BigDecimal::of($open === null ? '0' : Row::text($open, 'surplus_qty_base'));
        $serialized = (bool) DB::table('products')->where('id', $product)->value('is_serialized');
        $missing = $serialized ? self::missingSerials($entry->countId, $product) : [];
        $lost = $serialized ? self::lostSerials($entry->countId, $product) : [];
        $system = BigDecimal::of(Row::text($scope, 'system_qty_at_start'));

        // A serialized finding is its serials: one the latest count no longer sees leaves it withdrawn, and this count's unregistered serials become a finding of their own (L-48; C-34).
        if ($serialized && $open !== null && array_diff(self::unregisteredKeys((int) Row::text($open, 'stock_count_id')), self::unregisteredKeys($entry->countId)) !== []) {
            $this->settleFinding($context, $entry, $open, $finding, BigDecimal::zero(), $product, $scale, $location, $condition);
            $open = null;
            $finding = BigDecimal::zero();
        }

        if ($serialized) {
            // A serial's lines say who is missing and who was never registered; a count never nets them (L-48). A serial recorded elsewhere — dispatched, lost, at another rack — stays what the lines show, corrected through the CM rows and no surplus.
            $shortage = BigDecimal::of(count($missing));
            $variance = BigDecimal::of(self::unregisteredLines($entry->countId))->minus($finding);
        } else {
            $counted = DB::table('stock_count_lines')->where('stock_count_id', $entry->countId)->sum('counted_qty');
            $variance = BigDecimal::of((string) $counted)->minus($system);
            $shortage = BigDecimal::zero();
        }

        $actorMatches = [];
        $reduced = BigDecimal::zero();
        $picks = [...$entry->picks, ...$missing];

        if ($variance->isNegative() || $shortage->isPositive()) {
            $split = CountVariance::shortage($variance->isNegative() ? $variance->negated() : BigDecimal::zero(), $finding);
            $this->settleFinding($context, $entry, $open, $split['withdrawn'], $split['remaining'], $product, $scale, $location, $condition);
            $needed = $serialized ? $shortage : $split['shortage'];
            [$actorMatches, $reduced] = $this->shortage($context, $entry, $picks, $needed, $product, $scale, $location, $condition);
        }

        if ($variance->isPositive()) {
            $this->surplus($context, $entry, $variance, $open !== null, $product, $scale, $location, $condition, $lost);
        } elseif ($lost !== []) {
            $this->giveBack($context, $entry, $lost);
        }

        $this->audit->record(
            action: $actorMatches === [] ? 'INV.CountApplied' : 'INV.CountAppliedDuplicateConfirmed', authorityClass: AuthorityClass::AdmPlus, actorUserId: $context->request->actorUserId, companyId: null, entityType: 'stock_count', entityId: $entry->countId, entityPublicId: Row::text($count, 'public_id'), reason: $entry->reason, commandId: $context->request->commandId,
            change: AuditChange::updated(['state' => 'OPEN'], ['state' => 'APPLIED', 'variance' => (string) $variance, 'reduced' => (string) $reduced, 'cuts' => count($entry->cuts), ...($actorMatches === [] ? [] : ['matches' => $actorMatches, 'duplicate_reason' => $entry->duplicateReason])]), metadata: ['business_date' => $entry->businessDate],
        );
    }

    /**
     * A shortage as the actor split it: named lots lost once, a pending case across companies, conversions of pending condition-changed units; the cut on the first movement. The likely duplicates and the usable reduction.
     *
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     * @return array{list<string>, BigDecimal}
     */
    private function shortage(CommandContext $context, CountEntry $entry, array $picks, BigDecimal $needed, int $product, int $scale, int $location, string $condition): array
    {
        $named = array_reduce($picks, fn (BigDecimal $sum, array $pick): BigDecimal => $sum->plus($pick['qty']), BigDecimal::zero());
        $pending = BigDecimal::of($entry->pending === '' ? '0' : $entry->pending);
        $converted = array_reduce($entry->conversions, fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['qty']), BigDecimal::zero());

        if (! CountVariance::covers($needed, $named, $pending, $converted)) {
            throw Refused::rejected('C-07', ['reason' => 'split', 'needed' => (string) $needed]);
        }

        foreach ($picks as $pick) {
            if ($pick['location'] !== $location || $pick['condition'] !== $condition) {
                throw Refused::rejected('C-14', ['reason' => 'scope']);
            }

            // Every lost unit is named from evidence, or confirmed by the actor where its lots are one company's (BR-INV-04; L-42).
            if ($pick['serial'] === null && ! $entry->unidentified && $entry->evidence === []) {
                throw Refused::rejected('L-42', ['reason' => 'named_evidence']);
            }
        }

        foreach ($entry->conversions as $conversion) {
            $case = DB::table('unattributed_loss_cases')->where('id', $conversion['case'])->first(['product_id', 'location_id']);

            if ($condition !== 'UNUSABLE' || $case === null || (int) Row::text($case, 'product_id') !== $product || (int) Row::text($case, 'location_id') !== $location) {
                throw Refused::rejected('C-14', ['reason' => 'scope']);
            }
        }

        $usable = $condition === 'USABLE' ? $named->plus($pending) : BigDecimal::zero();
        $matches = [];
        $carried = false;

        if ($picks !== []) {
            $stock = self::picks($entry, $product, $picks);
            StockPlaces::ensureHeld($picks, $product, $scale, $entry->serializedSeen);

            if ($entry->unidentified) {
                StockPlaces::ensureConfirmable($picks, $product);
            }

            $parts = LossPosting::parts($picks, null);
            $this->losses->ensureBearers($context, $parts, $entry->allocationReason, $product);
            $matches = StockPlaces::likelyDuplicates('COUNT_VARIANCE', $picks, $entry->businessDate);
        }

        if ($pending->isPositive()) {
            $matches = [...$matches, ...UnattributedCases::likelyPending($product, $location, $condition, $pending, $entry->businessDate)];
        }

        DuplicateWarning::check('L-49', $matches, $entry->acknowledged, $entry->duplicateReason);

        if ($picks !== []) {
            $this->losses->post($context, $stock, 'COUNT_VARIANCE', $parts, $scale, (string) $usable, self::COMMAND, $entry->countId, $entry->allocationReason);
            $carried = true;
        }

        if ($pending->isPositive()) {
            $this->cases->recognize($context, $product, $scale, $location, 'LOSS', $condition, $pending, $entry->reason, $entry->businessDate, $picks === [] ? $entry->evidence : [], $entry->countId, $entry->cuts, $carried ? null : (string) $usable, self::COMMAND);
        }

        foreach ($entry->conversions as $conversion) {
            $this->cases->convert($context, $conversion['case'], BigDecimal::of($conversion['qty']), $entry->reason, $entry->businessDate, [], $entry->countId);
        }

        return [$matches, $usable];
    }

    /**
     * A surplus: the scope's pending cases found first, exactly; the losses it identifies; the rest one open finding, its units not added to stock (AX-06; L-15; C-34).
     *
     * @param  list<array{consumption: int, qty: string}>  $lost
     */
    private function surplus(CommandContext $context, CountEntry $entry, BigDecimal $variance, bool $hasFinding, int $product, int $scale, int $location, string $condition, array $lost): void
    {
        $pending = [];

        foreach (DB::table('unattributed_loss_cases')->where('product_id', $product)->where('location_id', $location)->where('condition', $condition)->where('case_kind', 'LOSS')->where('pending_qty_base', '>', 0)->orderBy('id')->get(['id', 'pending_qty_base']) as $row) {
            $pending[(int) Row::text($row, 'id')] = BigDecimal::of(Row::text($row, 'pending_qty_base'));
        }

        $identified = array_reduce($entry->restored, fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['qty']), BigDecimal::zero());
        $order = CountVariance::surplus($variance, $pending, $identified);

        if ($order === null) {
            throw Refused::rejected('C-27', ['reason' => 'above_variance']);
        }

        if ($order['finding']->isPositive() && $hasFinding) {
            throw Refused::rejected('C-34', ['reason' => 'one_open']);
        }

        foreach ($order['found'] as $case => $qty) {
            $this->cases->found($context, $case, $qty, $entry->reason, $entry->businessDate, $entry->countId);
        }

        foreach ($entry->restored as $item) {
            $this->ensureIdentified($item['consumption'], $product, $location, $condition, $entry->evidence !== []);
        }

        $this->giveBack($context, $entry, [...$lost, ...$entry->restored]);

        if ($order['finding']->isPositive()) {
            $this->writeFinding($context, $entry, $order['finding'], $product, $scale, $location, $condition);
        }
    }

    /** A prior loss reversed only when a serial, a single candidate lot or company among the losses it could reverse, or entry-error evidence identifies the units (AX-06; L-15). */
    private function ensureIdentified(int $consumption, int $product, int $location, string $condition, bool $evidence): void
    {
        $found = LossRestorations::of($consumption);

        // A serial identifies itself only by being seen: the count's lines bring its loss back, never a quantity entered (L-48).
        if ($found === null || $found['serial'] !== null || $found['product'] !== $product || $found['location'] !== $location || $found['condition'] !== $condition) {
            throw Refused::rejected('C-14', ['reason' => 'consumption']);
        }

        if (! $evidence && LossRestorations::acrossCompanies($product, $location, $condition)) {
            throw Refused::rejected('L-15', ['reason' => 'not_identified']);
        }
    }

    /**
     * LOSS_REVERSAL restorations on one movement of the count (L-07; L-31; C-27); their bearers within the actor's grants, otherwise refused and routed (AZ-08).
     *
     * @param  list<array{consumption: int, qty: string}>  $items
     */
    private function giveBack(CommandContext $context, CountEntry $entry, array $items): void
    {
        if ($items === []) {
            return;
        }

        $parts = [];

        foreach ($items as $item) {
            $found = LossRestorations::of($item['consumption']) ?? throw Refused::rejected('C-14', ['reason' => 'consumption']);
            $parts[] = ['company' => $found['bearer'], 'source' => $found['bearer']];
        }

        $this->losses->ensureBearers($context, $parts, null, (int) (LossRestorations::of($items[0]['consumption'])['product'] ?? 0));
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => (string) Str::uuid7(), 'movement_type' => 'RESTORATION', 'business_date' => $entry->businessDate, 'reason' => $entry->reason, 'stock_count_id' => $entry->countId,
            'command_id' => $context->request->commandId, 'command_line_no' => InventoryPosting::lineNo('stock_movements', $context), 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        $rows = $this->restorations->restore($context, $movement, $items, $entry->businessDate);
        LossRestorations::serialsBack($rows, $this->inventory->ledger($context, $movement, 'RESTORATION', $rows));
        StockPlaces::linkEvidence($context, $entry->evidence, $movement);
    }

    /** A count that finds less than a finding the basis carried: the finding withdrawn, what remains of it a new one (C-34); the scope moves, so an open count of it goes stale. */
    private function settleFinding(CommandContext $context, CountEntry $entry, ?object $open, BigDecimal $withdrawn, BigDecimal $remaining, int $product, int $scale, int $location, string $condition): void
    {
        if ($open === null || ! $withdrawn->isPositive()) {
            return;
        }

        DB::table('stock_count_findings')->where('id', (int) Row::text($open, 'id'))->where('state', 'OPEN')->update(['state' => 'WITHDRAWN', 'decided_by' => $context->request->actorUserId, 'reason' => $entry->reason, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $context->now()]);

        if ($remaining->isPositive()) {
            $this->writeFinding($context, $entry, $remaining, $product, $scale, $location, $condition);
        } else {
            self::touchScope($context, $product, $location, $condition);
        }
    }

    private function writeFinding(CommandContext $context, CountEntry $entry, BigDecimal $qty, int $product, int $scale, int $location, string $condition): void
    {
        DB::table('stock_count_findings')->insert([
            'stock_count_id' => $entry->countId, 'product_id' => $product, 'quantity_scale' => $scale, 'location_id' => $location, 'condition' => $condition, 'surplus_qty_base' => (string) $qty, 'reason' => $entry->reason,
            'command_id' => $context->request->commandId, 'command_line_no' => InventoryPosting::lineNo('stock_count_findings', $context), 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        self::touchScope($context, $product, $location, $condition);
    }

    /** A finding changes the basis of every later count, so the scope's version moves though no unit does (WF-INV-05; L-14). */
    private static function touchScope(CommandContext $context, int $product, int $location, string $condition): void
    {
        $key = ['product_id' => $product, 'location_id' => $location, 'condition' => $condition];
        $context->connection->table('stock_scope_balances')->insertOrIgnore([...$key, 'recorded_at' => $context->now()]);
        GuardUpdate::apply($context->connection, 'stock_scope_balances', [['key' => $key, 'deltas' => ['version' => '1']]]);
    }

    /** F2: every place's lines equal its balance, whole units, each serial not in stock; one − and one + entry a unit, no cost and no reservation moved; the policy turned on with it (L-35; DIR-101). */
    private function registration(CommandContext $context, CountEntry $entry, int $product): void
    {
        $found = DB::table('products')->where('id', $product)->value('quantity_scale');
        $scale = is_numeric($found) ? (int) $found : 0;

        if ($scale !== 0) {
            throw Refused::rejected('C-09', ['reason' => 'whole_units']);
        }

        // An ordinary count settles these first (`DIR-101` F2).
        if (DB::table('unattributed_loss_cases')->where('product_id', $product)->where('pending_qty_base', '>', 0)->exists() || DB::table('stock_count_findings')->where('product_id', $product)->where('state', 'OPEN')->exists()) {
            throw Refused::rejected('C-07', ['reason' => 'registration_pending']);
        }

        $counted = [];

        foreach (DB::table('stock_count_lines')->where('stock_count_id', $entry->countId)->orderBy('id')->get(['id', 'lot_id', 'location_id', 'condition', 'serial_unit_id', 'unregistered_serial_text']) as $line) {
            $place = Row::text($line, 'lot_id').'|'.Row::text($line, 'location_id').'|'.Row::text($line, 'condition');
            $counted[$place][] = $line;
        }

        $balances = DB::table('stock_lot_balances as lb')->where('product_id', $product)->where('qty', '>', 0)->get(['lot_id', 'location_id', 'condition', 'qty']);
        $held = [];

        foreach ($balances as $row) {
            $place = Row::text($row, 'lot_id').'|'.Row::text($row, 'location_id').'|'.Row::text($row, 'condition');
            $held[$place] = true;

            if (! BigDecimal::of(count($counted[$place] ?? []))->isEqualTo(Row::text($row, 'qty'))) {
                throw Refused::rejected('C-03', ['reason' => 'registration_difference']);
            }
        }

        if (array_diff_key($counted, $held) !== []) {
            throw Refused::rejected('C-03', ['reason' => 'registration_difference']);
        }

        $publicId = (string) Str::uuid7();
        $movement = (int) DB::table('stock_movements')->insertGetId([
            'public_id' => $publicId, 'movement_type' => 'SERIAL_REGISTRATION', 'business_date' => $entry->businessDate, 'reason' => $entry->reason, 'stock_count_id' => $entry->countId,
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ]);
        $rows = [];
        $number = 0;

        foreach ($counted as $lines) {
            foreach ($lines as $line) {
                $lot = (int) Row::text($line, 'lot_id');
                $location = (int) Row::text($line, 'location_id');
                $condition = Row::text($line, 'condition');
                $known = Row::nullable($line, 'serial_unit_id');
                $stored = DB::table('serial_units')->where('id', (int) $known)->value('serial_number');
                $text = Row::nullable($line, 'unregistered_serial_text') ?? (is_string($stored) ? $stored : '');
                $serial = $this->receiving->registerSerial($context, $product, ReceivingRecords::serialKey($text), $text, $lot, $location, $condition, ++$number);
                $base = ['lot' => $lot, 'product' => $product, 'scale' => $scale, 'location' => $location, 'condition' => $condition];
                $rows[] = [...$base, 'qty' => '-1'];
                $rows[] = [...$base, 'qty' => '1', 'serial' => $serial];
            }
        }

        $ids = $this->inventory->ledger($context, $movement, 'SERIAL_REGISTRATION', $rows);

        foreach ($rows as $index => $row) {
            if (isset($row['serial'])) {
                DB::table('serial_units')->where('id', $row['serial'])->update(['last_entry_id' => $ids[$index]]);
            }
        }

        DB::table('products')->where('id', $product)->where('is_serialized', false)->update(['is_serialized' => true, 'lock_version' => DB::raw('lock_version + 1')]);
        $this->audit->record(
            action: 'INV.SerialsRegistered', authorityClass: AuthorityClass::AdmPlus, actorUserId: $context->request->actorUserId, companyId: null, entityType: 'stock_movement', entityId: $movement, entityPublicId: $publicId, reason: $entry->reason, commandId: $context->request->commandId,
            change: AuditChange::updated(['is_serialized' => false], ['is_serialized' => true, 'units' => $number]), metadata: ['business_date' => $entry->businessDate],
        );
    }

    /**
     * @param  list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>  $picks
     */
    private static function picks(CountEntry $entry, int $product, array $picks): StockPicks
    {
        return new StockPicks($product, $picks, $entry->cuts, $entry->reason, $entry->evidence, $entry->unidentified, $entry->acknowledged, $entry->duplicateReason, $entry->businessDate, $entry->prefilled, $entry->serializedSeen, $entry->scaleSeen);
    }

    /**
     * The serials in stock in the scope the count did not see: each a named loss at its own lot (BR-INV-13).
     *
     * @return list<array{lot: int, location: int, condition: string, qty: string, serial: ?int}>
     */
    private static function missingSerials(int $count, int $product): array
    {
        $row = DB::table('stock_counts')->where('id', $count)->first(['location_id', 'condition', 'purpose']);

        if ($row === null || Row::text($row, 'purpose') !== 'ORDINARY') {
            return [];
        }

        $location = (int) Row::text($row, 'location_id');
        $condition = Row::text($row, 'condition');
        $missing = [];

        foreach (DB::table('serial_units as s')->where('s.product_id', $product)->where('s.state', 'IN_STOCK')->where('s.current_location_id', $location)->where('s.current_condition', $condition)
            ->whereNotExists(fn (Builder $seen) => $seen->from('stock_count_lines as l')->where('l.stock_count_id', $count)->whereColumn('l.serial_unit_id', 's.id'))->orderBy('s.id')->get(['s.id', 's.current_lot_id']) as $serial) {
            $missing[] = ['lot' => (int) Row::text($serial, 'current_lot_id'), 'location' => $location, 'condition' => $condition, 'qty' => '1', 'serial' => (int) Row::text($serial, 'id')];
        }

        return $missing;
    }

    /**
     * Serials the count saw that were recorded lost at this scope: the serial identifies the unit, so its loss is reversed (AX-06).
     *
     * @return list<array{consumption: int, qty: string}>
     */
    private static function lostSerials(int $count, int $product): array
    {
        $row = DB::table('stock_counts')->where('id', $count)->first(['location_id', 'condition', 'purpose']);

        if ($row === null || Row::text($row, 'purpose') !== 'ORDINARY') {
            return [];
        }

        $items = [];

        foreach (DB::table('stock_count_lines as l')->join('serial_units as s', 's.id', '=', 'l.serial_unit_id')->join('stock_movement_entries as e', 'e.serial_unit_id', '=', 's.id')->join('stock_consumption_balances as b', 'b.stock_consumption_id', '=', 'e.stock_consumption_id')->join('stock_consumptions as c', 'c.id', '=', 'b.stock_consumption_id')
            ->where('l.stock_count_id', $count)->where('s.product_id', $product)->where('s.state', 'LOST')->whereIn('c.kind', ['LOSS', 'UNATTRIBUTED_LOSS'])->where('e.location_id', (int) Row::text($row, 'location_id'))->where('e.condition', Row::text($row, 'condition'))->whereRaw('b.qty_base - b.restored_qty_base > 0')->orderBy('c.id')->get(['c.id']) as $found) {
            $items[] = ['consumption' => (int) Row::text($found, 'id'), 'qty' => '1'];
        }

        return $items;
    }

    /** The serials the count saw that no one ever received: what a surplus finding is, by the Owner's decision alone (L-48). */
    private static function unregisteredLines(int $count): int
    {
        return count(self::unregisteredKeys($count));
    }

    /**
     * The serials a count saw that no one ever received, by key; an observation of none names no serial.
     *
     * @return list<string>
     */
    private static function unregisteredKeys(int $count): array
    {
        $keys = [];

        foreach (DB::table('stock_count_lines as l')->leftJoin('serial_units as s', 's.id', '=', 'l.serial_unit_id')->where('l.stock_count_id', $count)
            ->where(fn (Builder $seen) => $seen->whereNotNull('l.unregistered_serial_text')->orWhere('s.state', 'NEVER_RECEIVED'))->orderBy('l.id')->get(['l.unregistered_serial_text', 's.serial_key']) as $line) {
            $text = Row::nullable($line, 'unregistered_serial_text');
            $keys[] = $text === null ? Row::text($line, 'serial_key') : ReceivingRecords::serialKey($text);
        }

        return $keys;
    }
}
