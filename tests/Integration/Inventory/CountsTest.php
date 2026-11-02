<?php

use App\Modules\INV\Actions\AdjustStock;
use App\Modules\INV\Actions\ChangeCondition;
use App\Modules\INV\Data\StockPicks;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AdjustmentWorld;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\CountWorld;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DispatchWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\ReceivingWorld;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-05 (TASK-043; WF-INV-05; SF-UNATTRIBUTED; AX-06; AX-07; AX-37; annex 7–9; C-07; C-33–C-37; L-14; L-15; L-42; L-44; L-48; M-04; M-06; M-30; GAP-009; GAP-030; `DIR-027`; `DIR-101`): a count stale when its scope moved, a shortage named or pending, a surplus found first and otherwise a finding, the Owner's resolution and its exact reversal, a registration count; the reconciliation suite after each.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->foreign = InvoiceWorld::make();
    $this->actor = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, ...AdjustmentWorld::CAPABILITIES, ...CountWorld::CAPABILITIES])->id;
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->evidence = AdjustmentWorld::evidence();
    $this->foreignActor = ReceivingWorld::actor($this->foreign->company, [...StockWorld::CAPABILITIES, ...CountWorld::CAPABILITIES])->id;
    $this->both = Authorization::admin([$this->company->id, $this->foreign->company->id], [...CountWorld::CAPABILITIES, 'stock.allocate_intercompany', 'stock.resolve_unattributed_evidence', 'stock.move', 'stock.adjust', 'stock.condition'])->id;
    $this->owner = Authorization::owner();
});

function countClean(): void
{
    expect(StockWorld::ledgerDifferences())->toBe([]);
}

function countGuard(int $product): array
{
    $row = DB::table('stock_product_balances')->where('product_id', $product)->first(['on_hand_qty', 'reserved_qty', 'unusable_qty']);

    return [$row->on_hand_qty, $row->reserved_qty, $row->unusable_qty];
}

function countState(int $count): string
{
    return (string) DB::table('stock_counts')->where('id', $count)->value('state');
}

function countCase(): object
{
    return DB::table('unattributed_loss_cases')->orderByDesc('id')->first();
}

/** A's lot 5 @10,000 and B's lot 5 @12,000 in one scope: P3 scenario 6. */
function countAcross(object $test): array
{
    $own = StockWorld::lot($test->actor, $test->company, $test->product, $test->unit, $test->rack, '5', '10000');
    $other = StockWorld::lot($test->foreignActor, $test->foreign->company, $test->product, $test->unit, $test->rack, '5', '12000');

    return [$own, $other];
}

it('stores each scope\'s version at the start; a movement of the scope after it makes the application CONFLICT, the count stale with its SYSTEM event, no stock changed; a new count follows (ST-03; C-33; L-14; criterion 1)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    $scope = DB::table('stock_count_scopes')->where('stock_count_id', $count)->where('condition', 'USABLE')->sole();
    expect((string) $scope->system_qty_at_start)->toBe('10.000')
        ->and((int) $scope->basis_version)->toBe((int) DB::table('stock_scope_balances')->where('product_id', $this->product)->where('location_id', $this->rack)->where('condition', 'USABLE')->value('version'))
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '9'))->toBe('COMMITTED');

    expect(AdjustmentWorld::move($this->actor, $this->product, $lot, $this->rack, ReceivingWorld::rack(), '1'))->toBe('COMMITTED');
    $guard = countGuard($this->product);
    $word = CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence]));

    expect($word)->toContain('ST-03')->and(countState($count))->toBe('STALE')->and(countGuard($this->product))->toBe($guard)
        ->and(DB::table('stock_consumptions')->where('kind', 'LOSS')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'INV.CountStale')->where('authority_class', 'SYSTEM')->count())->toBe(1);

    // Stale never reverts, and a stale count is only recounted.
    expect(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toContain('ST-03')
        ->and(CountWorld::open($this->actor, $this->product, $this->rack))->toBeGreaterThan($count);
    countClean();
});

it('commits a negative variance on named lots once, at their net cost, on the owning company; the scope stale for a second count (SF-LOSS; L-38; C-24; BR-INV-13; criterion 2)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '10000');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '6'))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '2'))->toBe('COMMITTED');
    $other = CountWorld::open($this->actor, $this->product, $this->rack);

    // A split that does not cover the shortage is refused.
    expect(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toContain('C-07')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(countState($count))->toBe('APPLIED')->and(countGuard($this->product)[0])->toBe('8.000');
    $consumption = DB::table('stock_consumptions')->where('kind', 'LOSS')->sole();
    expect((int) $consumption->bearer_company_id)->toBe($this->company->id)
        ->and((string) DB::table('cost_attributions')->where('stock_consumption_id', $consumption->id)->where('cost_class', 'LOSS')->value('amount'))->toBe('20000.00')
        ->and((int) DB::table('stock_movements')->where('id', $consumption->stock_movement_id)->value('stock_count_id'))->toBe($count)
        ->and(DB::table('stock_movements')->where('id', $consumption->stock_movement_id)->value('movement_type'))->toBe('COUNT_VARIANCE');

    // Applied once; the other count of the scope is stale.
    expect(CountWorld::apply($this->actor, CountWorld::entry($count)))->toContain('ST-02')
        ->and(CountWorld::line($this->actor, $other, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($other)))->toContain('ST-03');
    countClean();
});

it('becomes the case\'s single outcome where a unit is under an open case on the lot (L-38; CM-11; criterion 2)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '50000');
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $project = $this->demand['project']->id;
    $case = CorrectionCase::factory()->create(['company_id' => $this->company->id, 'case_kind' => 'DAMAGED_UNIT', 'causal_project_id' => $project, 'source_lot_id' => $lot])->id;
    $count = CountWorld::open($this->actor, $this->product, $this->rack, 'UNUSABLE');
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '0', null, 'UNUSABLE'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '1', 'UNUSABLE')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $consumption = DB::table('stock_consumptions')->where('kind', 'LOSS')->sole();

    expect((int) $consumption->correction_case_id)->toBe($case)->and((int) $consumption->bearer_project_id)->toBe($project);
    countClean();
});

it('commits an unidentified shortage of one company only with the actor\'s lot confirmation, and across companies only as one pending case with its immutable snapshot, no lot or cost moved, dispatch unblocked (L-42; SF-UNATTRIBUTED; C-36; M-06; criterion 3)', function () {
    [$own, $other] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED');

    // Across companies a confirmation names no lot, and a case is the only way.
    expect(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($own, $this->rack, '2')], unidentified: true, evidence: [$this->evidence])))->toContain('L-42');
    $costs = DB::table('lot_cost_balances')->orderBy('lot_id')->pluck('remaining_cost')->all();
    expect(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = countCase();

    expect((string) $case->pending_qty_base)->toBe('2.000')->and((string) $case->exposure_qty_base)->toBe('10.000')->and($case->case_kind)->toBe('LOSS')
        ->and(countGuard($this->product)[0])->toBe('8.000')
        ->and((string) DB::table('stock_lot_balances')->where('lot_id', $own)->value('qty'))->toBe('5.000')
        ->and(DB::table('lot_cost_balances')->orderBy('lot_id')->pluck('remaining_cost')->all())->toBe($costs)
        ->and(DB::table('stock_consumptions')->count())->toBe(0)->and(DB::table('cost_attributions')->where('cost_class', 'LOSS')->count())->toBe(0)
        ->and(DB::table('unattributed_loss_candidates')->where('unattributed_loss_case_id', $case->id)->orderBy('lot_id')->pluck('net_unit_cost_at_recognition')->all())->toBe(['10000.00', '12000.00'])
        ->and(DB::table('unattributed_loss_exposures')->where('unattributed_loss_case_id', $case->id)->orderBy('company_id')->pluck('exposure_qty_base')->all())->toBe(['5.000', '5.000']);
    countClean();

    // A's other lot of one company alone is confirmed by the actor.
    $rack = ReceivingWorld::rack();
    $single = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $rack, '3', '1000');
    $alone = CountWorld::open($this->actor, $this->product, $rack);
    expect(CountWorld::line($this->actor, $alone, $this->product, $rack, '2'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($alone, pending: '1', evidence: [$this->evidence])))->toContain('L-42')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($alone, [AdjustmentWorld::pick($single, $rack, '1')], unidentified: true, evidence: [$this->evidence])))->toBe('COMMITTED');
    countClean();
});

it('cuts the chosen reservations in the same action, and refuses without them (SF-RSV-CUT; C-06; criterion 4)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '8', '1000');
    expect(ReservationWorld::reserve($this->actor, $this->line, '8'))->toBe('COMMITTED');
    $reservation = (int) DB::table('reservations')->orderByDesc('id')->value('id');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '6'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toContain('C-06')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '2')], cuts: [$reservation => '2'], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(countGuard($this->product))->toBe(['6.000', '6.000', '0.000']);
    countClean();
});

it('refuses and routes a cut of another company\'s project without a grant on it (AZ-08; QS-20; criterion 4)', function () {
    $foreignDemand = DispatchWorld::demand($this->foreign);
    $product = $foreignDemand['product'];
    $lot = StockWorld::lot($this->actor, $this->company, $product, $foreignDemand['unit'], $this->rack, '4', '1000');
    expect(ReservationWorld::reserve($this->foreignActor, $foreignDemand['line'], '4'))->toBe('COMMITTED');
    $reservation = (int) DB::table('reservations')->orderByDesc('id')->value('id');
    $count = CountWorld::open($this->actor, $product, $this->rack);
    $routed = fn (): int => DB::table('audit_events')->where('action', 'INV.ReservationCutRouted')->where('company_id', $this->foreign->company->id)->count();

    expect(CountWorld::line($this->actor, $count, $product, $this->rack, '3'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, [AdjustmentWorld::pick($lot, $this->rack, '1')], cuts: [$reservation => '1'], evidence: [$this->evidence])))->toContain('AZ-08')
        ->and($routed())->toBe(1)->and(countGuard($product)[0])->toBe('4.000');
    countClean();
});

it('reverses a pending case exactly with a surplus first, no lot or company touched; an identified loss only when identified; any other surplus one open finding, its units not added, a second refused (L-15; C-34; BD-09; AX-06; criterion 5)', function () {
    [$own, $other] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = countCase();

    // Found: 8 → 11 reverses 2 exactly and 1 is a finding.
    $found = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $found, $this->product, $this->rack, '11'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($found)))->toBe('COMMITTED');
    $refreshed = DB::table('unattributed_loss_cases')->where('id', $case->id)->first();
    expect((string) $refreshed->found_qty_base)->toBe('2.000')->and((string) $refreshed->pending_qty_base)->toBe('0.000')
        ->and(countGuard($this->product)[0])->toBe('10.000')
        ->and(DB::table('stock_consumptions')->count())->toBe(0);
    $finding = DB::table('stock_count_findings')->where('state', 'OPEN')->sole();
    expect((string) $finding->surplus_qty_base)->toBe('1.000');

    // The next count's basis includes the finding; a further surplus is a second finding, refused.
    $next = CountWorld::open($this->actor, $this->product, $this->rack);
    expect((string) DB::table('stock_count_scopes')->where('stock_count_id', $next)->where('condition', 'USABLE')->value('system_qty_at_start'))->toBe('11.000')
        ->and(CountWorld::line($this->actor, $next, $this->product, $this->rack, '12'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($next)))->toContain('C-34');
    countClean();
});

it('reverses an identified loss when a single company identifies it, and refuses across companies without evidence (L-15; L-31; AX-06; criterion 5)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    $first = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $first, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($first, [AdjustmentWorld::pick($lot, $this->rack, '2')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $consumption = (int) DB::table('stock_consumptions')->where('kind', 'LOSS')->value('id');

    $second = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $second, $this->product, $this->rack, '9'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($second, restored: [['consumption' => $consumption, 'qty' => '1']])))->toBe('COMMITTED')
        ->and(countGuard($this->product)[0])->toBe('9.000')
        ->and((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $consumption)->value('restored_qty_base'))->toBe('1.000')
        ->and(DB::table('stock_count_findings')->count())->toBe(0);
    countClean();
});

it('lets only the Owner attribute an open finding, creating one flagged lot; a count started before it goes stale; an Admin\'s application leaves it open (AX-07; OD-08; C-08; criterion 6)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '5', '1000');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '7'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count)))->toBe('COMMITTED')
        ->and(countGuard($this->product)[0])->toBe('5.000');
    $finding = (int) DB::table('stock_count_findings')->where('state', 'OPEN')->value('id');
    $started = CountWorld::open($this->actor, $this->product, $this->rack);
    expect((string) DB::table('stock_count_scopes')->where('stock_count_id', $started)->where('condition', 'USABLE')->value('system_qty_at_start'))->toBe('7.000')
        ->and(CountWorld::line($this->actor, $started, $this->product, $this->rack, '7'))->toBe('COMMITTED');

    // Refused to an Admin whatever the request says.
    expect(fn () => CountWorld::attribute($this->both, $finding, $this->company->id, '4000'))->toThrow(AuthorizationException::class);
    expect(DB::table('stock_count_findings')->where('id', $finding)->value('state'))->toBe('OPEN');

    expect(CountWorld::attribute($this->owner->id, $finding, $this->company->id, '4000'))->toBe('COMMITTED')
        ->and(countGuard($this->product)[0])->toBe('7.000');
    $surplus = DB::table('inventory_lots')->where('stock_count_finding_id', $finding)->sole();
    expect($surplus->acquisition_kind)->toBe('SURPLUS')->and((bool) $surplus->cost_basis_flagged)->toBeTrue()->and((int) $surplus->source_company_id)->toBe($this->company->id)
        ->and((string) DB::table('lot_cost_balances')->where('lot_id', $surplus->id)->value('remaining_cost'))->toBe('4000.00')
        ->and(CountWorld::attribute($this->owner->id, $finding, $this->company->id, '4000'))->toContain('ST-02')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($started)))->toContain('ST-03');
    countClean();
});

it('resolves within the exposure and the group bound, the assigned company\'s claims first, a shortfall by the Owner\'s choice with one allocation, SF-LOSS once; an over-assignment refused (C-35; C-37; L-44; C-28; P3 scenario 6; criterion 7)', function () {
    [$own, $other] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = (int) countCase()->id;

    // A's five dispatched: its own claims are gone, nothing was retained.
    $word = DispatchWorld::dispatch($this->actor, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($own, $this->rack, '5')]);
    expect($word)->toBe('COMMITTED');

    $a = $this->company->id;
    $b = $this->foreign->company->id;
    expect(CountWorld::resolve($this->owner->id, $case, [['company' => $b, 'closes' => [['lot' => $other, 'qty' => '3']]]]))->toContain('C-35')
        ->and(CountWorld::resolve($this->owner->id, $case, [['company' => (int) DB::table('companies')->whereNotIn('id', [$a, $b])->value('id'), 'closes' => [['lot' => $other, 'qty' => '1']]]]))->toContain('C-');
    expect(fn () => CountWorld::resolve($this->both, $case, [['company' => $b, 'closes' => [['lot' => $other, 'qty' => '1']]]]))->toThrow(AuthorizationException::class);

    expect(CountWorld::resolve($this->owner->id, $case, [
        ['company' => $a, 'closes' => [['lot' => $other, 'qty' => '1']]],
        ['company' => $b, 'closes' => [['lot' => $other, 'qty' => '1']]],
    ]))->toBe('COMMITTED');
    $rows = DB::table('stock_consumptions')->where('kind', 'UNATTRIBUTED_LOSS')->orderBy('id')->get();
    expect($rows)->toHaveCount(2)->and((int) $rows[0]->bearer_company_id)->toBe($a)->and((int) $rows[0]->source_company_id)->toBe($b)->and($rows[0]->allocation_reason)->not->toBeNull()
        ->and($rows[1]->allocation_reason)->toBeNull()
        ->and(DB::table('cost_attributions')->where('stock_consumption_id', $rows[0]->id)->where('cost_class', 'LOSS')->value('amount'))->toBe('12000.00')
        ->and((string) DB::table('unattributed_loss_cases')->where('id', $case)->value('pending_qty_base'))->toBe('0.000');
    countClean();
});

it('never lets overlapping cases reuse an exposure: the group bound stops what per-case caps would allow (C-37; §5.8)', function () {
    [$own, $other] = countAcross($this);
    $ids = [];

    foreach (['2', '3', '1'] as $qty) {
        $count = CountWorld::open($this->actor, $this->product, $this->rack);
        $system = (string) DB::table('stock_count_scopes')->where('stock_count_id', $count)->where('condition', 'USABLE')->value('system_qty_at_start');
        expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, (string) ((int) $system - (int) $qty)))->toBe('COMMITTED')
            ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: $qty, evidence: [$this->evidence])))->toBe('COMMITTED');
        $ids[] = (int) countCase()->id;
    }

    $a = $this->company->id;
    expect((int) DB::table('unattributed_loss_cases')->where('id', $ids[1])->value('exposure_group_case_id'))->toBe($ids[0])
        ->and((string) DB::table('unattributed_loss_group_exposures')->where('exposure_group_case_id', $ids[0])->where('company_id', $a)->value('bound_qty_base'))->toBe('5.000')
        ->and(CountWorld::resolve($this->owner->id, $ids[1], [['company' => $a, 'closes' => [['lot' => $own, 'qty' => '3']]]]))->toBe('COMMITTED')
        ->and(CountWorld::resolve($this->owner->id, $ids[0], [['company' => $a, 'closes' => [['lot' => $own, 'qty' => '2']]]]))->toBe('COMMITTED')
        // A had five claims: per-case caps alone would let it absorb a sixth unit it never had.
        ->and(CountWorld::resolve($this->owner->id, $ids[2], [['company' => $a, 'closes' => [['lot' => $other, 'qty' => '1']]]]))->toContain('C-37')
        ->and(CountWorld::resolve($this->owner->id, $ids[2], [['company' => $this->foreign->company->id, 'closes' => [['lot' => $other, 'qty' => '1']]]]))->toBe('COMMITTED');
    countClean();
});

it('commits an evidence resolution only when every condition of OD-10 holds, each refused in turn, and it reaches the Owner\'s review (OD-10; QS-14; SM-04; criterion 8)', function () {
    [$own, $other] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = (int) countCase()->id;
    $later = AdjustmentWorld::evidence(['recorded_at' => now()->addMinute()]);
    $outsider = Authorization::admin([$this->foreign->company->id], [...CountWorld::CAPABILITIES, 'stock.resolve_unattributed_evidence'])->id;
    $close = [['lot' => $own, 'qty' => '2']];

    // (a) the reason; (b) no later proof; (c) nothing named, or two companies; (d) more than the lot holds; (e) out of scope; (f) above pending.
    expect(CountWorld::resolveEvidence($this->both, $case, $close, [$later], '   '))->toContain('OD-10')
        ->and(CountWorld::resolveEvidence($this->both, $case, $close, []))->toContain('OD-10')
        ->and(CountWorld::resolveEvidence($this->both, $case, [], [$later]))->toContain('OD-10')
        ->and(CountWorld::resolveEvidence($this->both, $case, [['lot' => $own, 'qty' => '1'], ['lot' => $other, 'qty' => '1']], [$later]))->toContain('OD-10')
        ->and(CountWorld::resolveEvidence($this->both, $case, [['lot' => $own, 'qty' => '6']], [$later]))->toContain('OD-10')
        ->and(CountWorld::resolveEvidence($outsider, $case, $close, [$later]))->toContain('OD-10')
        ->and(CountWorld::resolveEvidence($this->both, $case, [['lot' => $own, 'qty' => '3']], [$later]))->toContain('C-35');

    expect(CountWorld::resolveEvidence($this->both, $case, $close, [$later]))->toBe('COMMITTED')
        ->and(DB::table('unattributed_loss_resolutions')->where('unattributed_loss_case_id', $case)->value('resolution_kind'))->toBe('EVIDENCE_IDENTIFIED')
        ->and(DB::table('audit_events')->where('action', 'INV.UnattributedResolved')->where('authority_class', 'ADM_PLUS')->count())->toBe(1)
        ->and((string) DB::table('cost_attributions')->where('cost_class', 'LOSS')->sum('amount'))->toBe('20000.00');
    countClean();
});

it('reverses a resolution exactly and at most once, the same pending quantity re-opened, the snapshot and later movements unchanged; a corrected attribution cites it; a found surplus after resolution restores through the loss reversal (CM-27; C-35; SF-LOSS; criterion 9)', function () {
    [$own, $other] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = (int) countCase()->id;
    $b = $this->foreign->company->id;
    $snapshot = DB::table('unattributed_loss_candidates')->where('unattributed_loss_case_id', $case)->orderBy('id')->get()->toJson();
    expect(CountWorld::resolve($this->owner->id, $case, [['company' => $b, 'closes' => [['lot' => $other, 'qty' => '2']]]]))->toBe('COMMITTED');
    $resolution = (int) DB::table('unattributed_loss_resolutions')->where('unattributed_loss_case_id', $case)->value('id');
    countClean();

    expect(fn () => CountWorld::reverse($this->both, $resolution))->toThrow(AuthorizationException::class);
    expect(CountWorld::reverse($this->owner->id, $resolution))->toBe('COMMITTED')
        ->and((string) DB::table('unattributed_loss_cases')->where('id', $case)->value('pending_qty_base'))->toBe('2.000')
        ->and((string) DB::table('unattributed_loss_exposures')->where('unattributed_loss_case_id', $case)->where('company_id', $b)->value('assigned_net_qty_base'))->toBe('0.000')
        ->and((string) DB::table('cost_attributions')->where('cost_class', 'LOSS')->sum('amount'))->toBe('0.00')
        ->and(DB::table('unattributed_loss_candidates')->where('unattributed_loss_case_id', $case)->orderBy('id')->get()->toJson())->toBe($snapshot)
        ->and(CountWorld::reverse($this->owner->id, $resolution))->toContain('C-37');
    countClean();

    expect(CountWorld::resolve($this->owner->id, $case, [['company' => $b, 'closes' => [['lot' => $other, 'qty' => '2']]]]))->toBe('COMMITTED')
        ->and((int) DB::table('unattributed_loss_resolutions')->where('unattributed_loss_case_id', $case)->orderByDesc('id')->value('supersedes_resolution_id'))->toBe($resolution);
    $consumption = (int) DB::table('stock_consumptions')->where('kind', 'UNATTRIBUTED_LOSS')->orderByDesc('id')->value('id');

    // Found after resolution: the exact loss reversal, never a second recognition.
    $found = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $found, $this->product, $this->rack, '9'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->both, CountWorld::entry($found, restored: [['consumption' => $consumption, 'qty' => '1']], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and((string) DB::table('cost_attributions')->where('cost_class', 'LOSS')->sum('amount'))->toBe('12000.00')
        ->and(DB::table('stock_count_findings')->count())->toBe(0);
    countClean();
});

it('counts a repeated serial once, keeps an unexpected one as seen without registering it, names a missing one by its lot; replays with the recorded outcome (SF-SCAN; L-48; CI-05; AC-16; criterion 11)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $one = StockWorld::serial($this->product, $lot, $this->rack);
    $two = StockWorld::serial($this->product, $lot, $this->rack);
    $first = (string) DB::table('serial_units')->where('id', $one)->value('serial_key');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    $command = (string) Str::uuid();

    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', $first, commandId: $command))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', $first, commandId: $command))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', $first))->toContain('C-02')
        ->and(DB::table('stock_count_lines')->where('stock_count_id', $count)->count())->toBe(1)
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', 'SN-TAK-TERDAFTAR'))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('serial_key', 'sn-tak-terdaftar')->exists())->toBeFalse();

    // The missing serial lost by its own lot; the unexpected one a finding, not in stock.
    expect(CountWorld::apply($this->actor, CountWorld::entry($count, evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $two)->value('state'))->toBe('LOST')
        ->and((string) DB::table('stock_count_findings')->where('state', 'OPEN')->value('surplus_qty_base'))->toBe('1.000')
        ->and(countGuard($this->product)[0])->toBe('1.000');

    // Only the Owner's attribution registers it.
    $finding = (int) DB::table('stock_count_findings')->where('state', 'OPEN')->value('id');
    expect(CountWorld::attribute($this->owner->id, $finding, $this->company->id, '500', [], true))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('serial_key', 'sn-tak-terdaftar')->value('state'))->toBe('IN_STOCK');
    countClean();
});

it('opens a pending case through PG-027\'s adjustment and condition change, never a named loss; the unit\'s later disposal converts it; replayed with the recorded outcome (QS-22; SF-UNATTRIBUTED; J-INV-04; criterion 13)', function () {
    countAcross($this);
    $entry = fn (string $qty, array $pending = [], array $conversions = []) => new StockPicks($this->product, [], [], 'Tidak jelas milik siapa', [$this->evidence], true, [], null, BusinessDate::today(), false, false, 0, $pending, $conversions);
    $changed = $entry('1', [['location' => $this->rack, 'condition' => 'USABLE', 'qty' => '2']]);
    $command = (string) Str::uuid();
    $run = fn (): string => InvoiceWorld::word(app(ChangeCondition::class)->run($command, $this->both, $changed, ['pending' => $changed->pending]));

    expect($run())->toBe('COMMITTED')->and($run())->toBe('COMMITTED');
    $case = countCase();
    expect($case->case_kind)->toBe('CONDITION_CHANGE')->and((string) $case->pending_qty_base)->toBe('2.000')
        ->and(countGuard($this->product))->toBe(['10.000', '0.000', '2.000'])
        ->and(DB::table('unattributed_loss_cases')->count())->toBe(1);
    countClean();

    // The disposal converts it: ON HAND and UNUSABLE both fall.
    $disposal = $entry('1', [], [['case' => $case->id, 'qty' => '2']]);
    expect(InvoiceWorld::word(app(AdjustStock::class)->run((string) Str::uuid(), $this->both, $disposal, 'DISPOSAL', null, null, ['conversions' => $disposal->conversions])))->toBe('COMMITTED')
        ->and(countGuard($this->product))->toBe(['8.000', '0.000', '0.000']);
    $successor = countCase();
    expect($successor->case_kind)->toBe('LOSS')->and((int) $successor->converted_from_case_id)->toBe((int) $case->id)->and((string) $successor->pending_qty_base)->toBe('2.000')
        ->and((string) DB::table('unattributed_loss_cases')->where('id', $case->id)->value('converted_qty_base'))->toBe('2.000')
        ->and(DB::table('unattributed_loss_candidates')->where('unattributed_loss_case_id', $successor->id)->count())->toBe(2);
    countClean();
});

it('leaves a dispatch racing an application of its scope committed, the application stale with nothing overwritten, in both orders on real sessions (M-04; GAP-009; criterion 1)', function (bool $dispatchFirst) {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '10'))->toBe('COMMITTED');
    [$actor, $company, $line, $unit, $rack, $product] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack, $this->product];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $dispatchFirst
            ? DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')])
            : CountWorld::apply($actor, CountWorld::entry($count)), 'the first holds the scope'),
        'second' => static function () use ($dispatchFirst, $actor, $company, $line, $unit, $lot, $rack, $count): string {
            Barrier::await('the first holds the scope');

            return $dispatchFirst
                ? CountWorld::apply($actor, CountWorld::entry($count))
                : DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED');

    if ($dispatchFirst) {
        expect($results['second'])->toContain('ST-03')->and(countState($count))->toBe('STALE');
    } else {
        expect($results['second'])->toBe('COMMITTED')->and(countState($count))->toBe('APPLIED');
    }

    expect(countGuard($product)[0])->toBe('9.000');
    countClean();
})->with(['the dispatch first' => true, 'the application first' => false]);

it('leaves a resolution whose lots a racing dispatch consumed refused with C-37, nothing retained, in both orders on real sessions (M-06; DIR-027; criterion 3)', function (bool $dispatchFirst) {
    [$own, $other] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = (int) countCase()->id;
    [$actor, $company, $line, $unit, $rack, $owner] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack, $this->owner->id];

    // The resolution closes A's lot, so the dispatch and it want the same claims.
    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $dispatchFirst
            ? DispatchWorld::dispatch($actor, $company, $line, $unit, '5', [DispatchWorld::pick($own, $rack, '5')])
            : CountWorld::resolve($owner, $case, [['company' => $company, 'closes' => [['lot' => $own, 'qty' => '2']]]]), 'the first holds the lot'),
        'second' => static function () use ($dispatchFirst, $actor, $company, $line, $unit, $own, $rack, $owner, $case): string {
            Barrier::await('the first holds the lot');

            return $dispatchFirst
                ? CountWorld::resolve($owner, $case, [['company' => $company, 'closes' => [['lot' => $own, 'qty' => '2']]]])
                : DispatchWorld::dispatch($actor, $company, $line, $unit, '5', [DispatchWorld::pick($own, $rack, '5')]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED');
    expect($results['second'])->toContain($dispatchFirst ? 'C-37' : 'C-0');
    countClean();
})->with(['the dispatch first' => true, 'the resolution first' => false]);

it('founds one overlap group when two cases first use a scope together, its rows born once (M-30; FU-06; C-37)', function () {
    countAcross($this);
    [$both, $product, $rack, $evidence] = [$this->both, $this->product, $this->rack, $this->evidence];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => CountWorld::pendingShrinkage($both, $product, $rack, '2', $evidence), 'the first holds the scope'),
        'second' => static function () use ($both, $product, $rack, $evidence): string {
            Barrier::await('the first holds the scope');

            return CountWorld::pendingShrinkage($both, $product, $rack, '3', $evidence);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])
        ->and(DB::table('unattributed_loss_group_exposures')->count())->toBe(2)
        ->and(DB::table('unattributed_loss_cases')->whereNotNull('exposure_group_case_id')->count())->toBe(1);
    countClean();
});

it('resolves a condition-change case only onto the assigned company\'s own usable claims, reversed exactly; a later disposal converts what stays pending (SF-UNATTRIBUTED; C-37; CM-27)', function () {
    [$own, $other] = countAcross($this);
    expect(CountWorld::pendingCondition($this->both, $this->product, $this->rack, '2', $this->evidence))->toBe('COMMITTED');
    $case = (int) countCase()->id;
    $a = $this->company->id;

    expect(CountWorld::resolve($this->owner->id, $case, [['company' => $a, 'closes' => [['lot' => $other, 'qty' => '1']]]]))->toContain('C-37')
        ->and(CountWorld::resolve($this->owner->id, $case, [['company' => $a, 'closes' => [['lot' => $own, 'qty' => '1']]]]))->toBe('COMMITTED')
        ->and(countGuard($this->product))->toBe(['10.000', '0.000', '2.000'])
        ->and(DB::table('stock_consumptions')->count())->toBe(0)
        ->and((string) DB::table('stock_lot_balances')->where('lot_id', $own)->where('condition', 'UNUSABLE')->value('qty'))->toBe('1.000');
    countClean();

    $resolution = (int) DB::table('unattributed_loss_resolutions')->value('id');
    expect(CountWorld::reverse($this->owner->id, $resolution))->toBe('COMMITTED')
        ->and((string) DB::table('unattributed_loss_cases')->where('id', $case)->value('pending_qty_base'))->toBe('2.000')
        ->and(countGuard($this->product))->toBe(['10.000', '0.000', '2.000'])
        ->and((string) DB::table('stock_lot_balances')->where('lot_id', $own)->where('condition', 'UNUSABLE')->value('qty'))->toBe('0.000');
    countClean();
});

it('refuses a pending quantity beside named units or a case: nothing is silently dropped (SF-LOSS; BR-INV-13)', function () {
    [$own] = countAcross($this);
    $entry = new StockPicks($this->product, [AdjustmentWorld::pick($own, $this->rack, '1')], [], 'Campur', [$this->evidence], false, [], null, BusinessDate::today(), false, false, 0, [['location' => $this->rack, 'condition' => 'USABLE', 'qty' => '1']]);

    expect(InvoiceWorld::word(app(AdjustStock::class)->run((string) Str::uuid(), $this->both, $entry, 'SHRINKAGE', null, null, ['mixed' => true])))->toContain('C-07')
        ->and(countGuard($this->product)[0])->toBe('10.000');
    countClean();
});

it('refuses an evidence holder reversing a resolution charged to a company outside its scope, routed; refuses a forged conversion from another rack (AZ-08; XL-1; §5.8)', function () {
    [$own, $other] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = (int) countCase()->id;
    $later = AdjustmentWorld::evidence(['recorded_at' => now()->addMinute()]);
    expect(CountWorld::resolveEvidence($this->both, $case, [['lot' => $other, 'qty' => '2']], [$later]))->toBe('COMMITTED');
    $resolution = (int) DB::table('unattributed_loss_resolutions')->value('id');
    $aOnly = Authorization::admin([$this->company->id], [...CountWorld::CAPABILITIES, 'stock.resolve_unattributed_evidence'])->id;

    expect(CountWorld::reverse($aOnly, $resolution, 'stock.resolve_unattributed_evidence'))->toContain('AZ-08')
        ->and((string) DB::table('unattributed_loss_cases')->where('id', $case)->value('pending_qty_base'))->toBe('0.000');

    // A conversion of another rack cannot cover this count's shortage.
    $rack = ReceivingWorld::rack();
    StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $rack, '2', '1000');
    StockWorld::lot($this->foreignActor, $this->foreign->company, $this->product, $this->unit, $rack, '2', '1000');
    expect(CountWorld::pendingCondition($this->both, $this->product, $rack, '1', $this->evidence))->toBe('COMMITTED');
    $change = (int) countCase()->id;
    expect(AdjustmentWorld::change($this->actor, AdjustmentWorld::entry($this->product, [AdjustmentWorld::pick($own, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    $unusable = CountWorld::open($this->actor, $this->product, $this->rack, 'UNUSABLE');
    expect(CountWorld::line($this->actor, $unusable, $this->product, $this->rack, '0', null, 'UNUSABLE'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($unusable, conversions: [['case' => $change, 'qty' => '1']], evidence: [$this->evidence])))->toContain('C-14');
    countClean();
});

it('withdraws what a later shortage takes back of an open finding, the rest a new finding; a named lot needs its proof (WF-INV-05; C-34; L-42)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '10', '1000');
    $first = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $first, $this->product, $this->rack, '12'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($first)))->toBe('COMMITTED');
    $second = CountWorld::open($this->actor, $this->product, $this->rack);

    // 12 on the basis, 11 seen: one of the finding's two gives way and nothing is lost.
    expect(CountWorld::line($this->actor, $second, $this->product, $this->rack, '11'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($second)))->toBe('COMMITTED')
        ->and(DB::table('stock_count_findings')->where('state', 'WITHDRAWN')->count())->toBe(1)
        ->and((string) DB::table('stock_count_findings')->where('state', 'OPEN')->value('surplus_qty_base'))->toBe('1.000')
        ->and(countGuard($this->product)[0])->toBe('10.000')
        ->and(DB::table('stock_consumptions')->count())->toBe(0);

    $third = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $third, $this->product, $this->rack, '9'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($third, [AdjustmentWorld::pick($lot, $this->rack, '1')])))->toContain('L-42')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($third, [AdjustmentWorld::pick($lot, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('stock_count_findings')->where('state', 'OPEN')->count())->toBe(0)
        ->and(countGuard($this->product)[0])->toBe('9.000');
    countClean();
});

it('leaves a serial recorded dispatched or at another rack to the CM rows, no surplus and never re-registered; a repeated scan by any spelling once (L-48; C-02; criterion 11)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    $elsewhere = ReceivingWorld::rack();
    $otherLot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $elsewhere, '1', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $here = StockWorld::serial($this->product, $lot, $this->rack);
    $away = StockWorld::serial($this->product, $otherLot, $elsewhere);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    $keyHere = (string) DB::table('serial_units')->where('id', $here)->value('serial_key');
    $keyAway = (string) DB::table('serial_units')->where('id', $away)->value('serial_key');

    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', $keyHere))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', $keyAway))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', 'SN-baru'))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', 'SN-BARU '))->toContain('C-02')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count)))->toBe('COMMITTED')
        ->and((string) DB::table('stock_count_findings')->where('state', 'OPEN')->value('surplus_qty_base'))->toBe('1.000')
        ->and(DB::table('serial_units')->where('id', $away)->value('current_location_id'))->toBe($elsewhere);
    countClean();
});

it('cuts reservations for a missing serial, the same action (SF-RSV-CUT; criterion 4)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    StockWorld::serial($this->product, $lot, $this->rack);
    expect(ReservationWorld::reserve($this->actor, $this->line, '1'))->toBe('COMMITTED');
    $reservation = (int) DB::table('reservations')->orderByDesc('id')->value('id');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);

    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1', 'SN-LAIN'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, evidence: [$this->evidence])))->toContain('C-06')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, cuts: [$reservation => '1'], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(countGuard($this->product))->toBe(['0.000', '0.000', '0.000']);
    countClean();
});

it('registers a serial on every unit in one movement that changes no quantity, lot or cost, the policy on with it; a difference, a serial in stock and a non-integral quantity refused (`DIR-101` F2; L-35; criterion 14)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1000');
    $registrar = Authorization::admin([$this->company->id], [...CountWorld::CAPABILITIES, 'products.edit'])->id;
    expect(CountWorld::openRegistration($registrar, $this->product))->toBe('COMMITTED');
    $count = (int) DB::table('stock_counts')->orderByDesc('id')->value('id');
    $cost = (string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
    $guard = countGuard($this->product);

    expect(CountWorld::line($registrar, $count, $this->product, $this->rack, '1', 'SN-1', lot: $lot))->toBe('COMMITTED')
        ->and(CountWorld::apply($registrar, CountWorld::entry($count)))->toContain('C-03')
        ->and(DB::table('products')->where('id', $this->product)->value('is_serialized'))->toBeFalse();

    expect(CountWorld::line($registrar, $count, $this->product, $this->rack, '1', 'SN-1', lot: $lot))->toContain('C-02')
        ->and(CountWorld::line($registrar, $count, $this->product, $this->rack, '1', 'SN-2', lot: $lot))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count)))->toContain('AZ-08')
        ->and(CountWorld::apply($registrar, CountWorld::entry($count)))->toBe('COMMITTED');

    expect((bool) DB::table('products')->where('id', $this->product)->value('is_serialized'))->toBeTrue()
        ->and(countGuard($this->product))->toBe($guard)
        ->and((string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe($cost)
        ->and(DB::table('serial_units')->where('product_id', $this->product)->where('state', 'IN_STOCK')->where('current_lot_id', $lot)->count())->toBe(2)
        ->and(DB::table('stock_movements')->where('movement_type', 'SERIAL_REGISTRATION')->count())->toBe(1)
        ->and(DB::table('stock_consumptions')->count())->toBe(0);
    countClean();
});

it('refuses a registration count while the product has an open finding, which an ordinary count settles first (`DIR-101` F2)', function () {
    StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    $registrar = Authorization::admin([$this->company->id], [...CountWorld::CAPABILITIES, 'products.edit'])->id;
    $ordinary = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $ordinary, $this->product, $this->rack, '2'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($ordinary)))->toBe('COMMITTED')
        ->and(CountWorld::openRegistration($registrar, $this->product))->toBe('COMMITTED');
    $registration = (int) DB::table('stock_counts')->orderByDesc('id')->value('id');

    expect(CountWorld::apply($registrar, CountWorld::entry($registration)))->toContain('C-07')
        ->and((bool) DB::table('products')->where('id', $this->product)->value('is_serialized'))->toBeFalse();
    countClean();
});

it('leaves a dispatch racing a registration count: one of them, the guards exact, in both orders on real sessions (`DIR-101` F2; LK-14)', function (bool $dispatchFirst) {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    $registrar = Authorization::admin([$this->company->id], [...CountWorld::CAPABILITIES, 'products.edit'])->id;
    expect(CountWorld::openRegistration($registrar, $this->product))->toBe('COMMITTED');
    $count = (int) DB::table('stock_counts')->orderByDesc('id')->value('id');
    expect(CountWorld::line($registrar, $count, $this->product, $this->rack, '1', 'SN-1', lot: $lot))->toBe('COMMITTED');
    [$actor, $company, $line, $unit, $rack, $product] = [$this->actor, $this->company->id, $this->line, $this->unit, $this->rack, $this->product];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $dispatchFirst
            ? DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')])
            : CountWorld::apply($registrar, CountWorld::entry($count)), 'the first holds the scope'),
        'second' => static function () use ($dispatchFirst, $actor, $company, $line, $unit, $lot, $rack, $registrar, $count): string {
            Barrier::await('the first holds the scope');

            return $dispatchFirst
                ? CountWorld::apply($registrar, CountWorld::entry($count))
                : DispatchWorld::dispatch($actor, $company, $line, $unit, '1', [DispatchWorld::pick($lot, $rack, '1')]);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED');

    if ($dispatchFirst) {
        expect($results['second'])->toContain('ST-03')->and((bool) DB::table('products')->where('id', $product)->value('is_serialized'))->toBeFalse();
    } else {
        // The policy changed under the dispatch: it reads it again and refuses or conflicts, the serial stays in stock.
        expect($results['second'])->not->toBe('COMMITTED')->and((bool) DB::table('products')->where('id', $product)->value('is_serialized'))->toBeTrue();
    }

    countClean();
})->with(['the dispatch first' => true, 'the registration first' => false]);

it('leaves a receipt racing an application of its scope committed, the application stale (M-04; GAP-009; criterion 1)', function () {
    StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '3', '1000');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '3'))->toBe('COMMITTED');
    // A session reads its company itself: a model does not cross into another process.
    [$actor, $companyId, $unit, $rack, $product] = [$this->actor, $this->company->id, $this->unit, $this->rack, $this->product];

    $results = Sessions::run([
        'first' => static fn (): int => InvoiceWorld::hold(static fn (): int => StockWorld::lot($actor, Company::query()->findOrFail($companyId), $product, $unit, $rack, '1', '1000'), 'the receipt holds the scope'),
        'second' => static function () use ($actor, $count): string {
            Barrier::await('the receipt holds the scope');

            return CountWorld::apply($actor, CountWorld::entry($count));
        },
    ]);

    expect($results['first'])->toBeInt()->and($results['second'])->toContain('ST-03')->and(countState($count))->toBe('STALE')->and(countGuard($product)[0])->toBe('4.000');
    countClean();
});

it('cancels an open count with its lines kept and no stock changed; a cancelled count takes no line or application (ROUTE-215; `DIR-100`)', function () {
    StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1000');
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '2'))->toBe('COMMITTED')
        ->and(CountWorld::cancel($this->actor, $count, $this->product))->toBe('COMMITTED')
        ->and(countState($count))->toBe('CANCELLED')
        ->and(DB::table('stock_count_lines')->where('stock_count_id', $count)->count())->toBe(1)
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '2'))->toContain('C-33')
        ->and(CountWorld::cancel($this->actor, $count, $this->product))->toContain('C-33')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count)))->toContain('ST-02');
    countClean();
});

it('warns of a likely duplicate pending quantity on PG-027 and commits it only with the actor\'s reason, under the acknowledged action (L-49; BD-13; QS-21; ER-06 point 13)', function () {
    countAcross($this);
    $entry = fn (array $acknowledged = [], ?string $reason = null) => new StockPicks($this->product, [], [], 'Tidak jelas milik siapa', [$this->evidence], true, $acknowledged, $reason, BusinessDate::today(), false, false, 0, [['location' => $this->rack, 'condition' => 'USABLE', 'qty' => '1']]);
    $run = fn (StockPicks $picks) => app(AdjustStock::class)->run((string) Str::uuid(), $this->both, $picks, 'SHRINKAGE', null, null, ['acknowledged' => $picks->acknowledged, 'reason' => $picks->duplicateReason]);

    expect(InvoiceWorld::word($run($entry())))->toBe('COMMITTED');
    $warned = $run($entry());
    $matches = $warned->state['matches'] ?? [];
    expect($warned->code)->toBe('L-49')->and($matches)->toHaveCount(1)
        ->and(InvoiceWorld::word($run($entry($matches, 'Dua kehilangan berbeda'))))->toBe('COMMITTED')
        ->and(DB::table('unattributed_loss_cases')->count())->toBe(2)
        ->and(DB::table('audit_events')->where('action', 'INV.UnattributedRecognizedDuplicateConfirmed')->count())->toBe(1);
    countClean();
});

it('decides whether a surplus identifies a loss by the losses it could reverse, not by the stock left: two companies\' open losses need evidence (WF-INV-05; L-15; AX-06)', function () {
    $own = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1000');
    $other = StockWorld::lot($this->foreignActor, $this->foreign->company, $this->product, $this->unit, $this->rack, '1', '1000');
    $first = CountWorld::open($this->both, $this->product, $this->rack);
    expect(CountWorld::line($this->both, $first, $this->product, $this->rack, '1'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->both, CountWorld::entry($first, [AdjustmentWorld::pick($own, $this->rack, '1'), AdjustmentWorld::pick($other, $this->rack, '1')], evidence: [$this->evidence])))->toBe('COMMITTED');
    // Only the first company's lot still holds units; both companies' losses are open.
    $consumption = (int) DB::table('stock_consumptions')->where('kind', 'LOSS')->where('source_company_id', $this->foreign->company->id)->value('id');

    $second = CountWorld::open($this->both, $this->product, $this->rack);
    expect(CountWorld::line($this->both, $second, $this->product, $this->rack, '2'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->both, CountWorld::entry($second, restored: [['consumption' => $consumption, 'qty' => '1']])))->toContain('L-15')
        ->and((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $consumption)->value('restored_qty_base'))->toBe('0.000')
        ->and(CountWorld::apply($this->both, CountWorld::entry($second, restored: [['consumption' => $consumption, 'qty' => '1']], evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $consumption)->value('restored_qty_base'))->toBe('1.000')
        ->and(countGuard($this->product)[0])->toBe('2.000');
    countClean();
});

it('withdraws a serialized finding whose serial the latest count no longer sees; this count\'s unregistered serial a finding of its own, the one the Owner registers (L-48; C-34; AX-07)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '1', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $key = (string) DB::table('serial_units')->where('id', StockWorld::serial($this->product, $lot, $this->rack))->value('serial_key');
    $first = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $first, $this->product, $this->rack, '1', $key))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $first, $this->product, $this->rack, '1', 'SN-A'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($first)))->toBe('COMMITTED');
    $old = (int) DB::table('stock_count_findings')->where('state', 'OPEN')->value('id');

    $second = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $second, $this->product, $this->rack, '1', $key))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $second, $this->product, $this->rack, '1', 'SN-B'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($second)))->toBe('COMMITTED')
        ->and(DB::table('stock_count_findings')->where('id', $old)->value('state'))->toBe('WITHDRAWN');
    $finding = DB::table('stock_count_findings')->where('state', 'OPEN')->first(['id', 'stock_count_id', 'surplus_qty_base']);

    expect((int) $finding->stock_count_id)->toBe($second)->and((string) $finding->surplus_qty_base)->toBe('1.000')
        ->and(CountWorld::attribute($this->owner->id, (int) $finding->id, $this->company->id, '500', [], true))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('serial_key', 'sn-b')->value('state'))->toBe('IN_STOCK')
        ->and(DB::table('serial_units')->where('serial_key', 'sn-a')->exists())->toBeFalse();
    countClean();
});

it('applies a serialized count where no unit was seen through its observation of none, each missing serial lost by its lot, never an invented serial (WF-INV-05; L-48; SF-LOSS)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $serials = [StockWorld::serial($this->product, $lot, $this->rack), StockWorld::serial($this->product, $lot, $this->rack)];
    $count = CountWorld::open($this->actor, $this->product, $this->rack);

    expect(CountWorld::apply($this->actor, CountWorld::entry($count, evidence: [$this->evidence])))->toContain('C-33')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '1'))->toContain('C-02')
        ->and(CountWorld::line($this->actor, $count, $this->product, $this->rack, '0'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->whereIn('id', $serials)->pluck('state')->unique()->values()->all())->toBe(['LOST'])
        ->and(DB::table('stock_count_findings')->count())->toBe(0)
        ->and(countGuard($this->product)[0])->toBe('0.000');
    countClean();
});

it('commits a serialized count that loses an unseen serial and brings a lost one back with its proof on both movements (L-48; C-48; BR-INV-04)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $keys = array_map(fn (int $serial): string => (string) DB::table('serial_units')->where('id', $serial)->value('serial_key'), [StockWorld::serial($this->product, $lot, $this->rack), StockWorld::serial($this->product, $lot, $this->rack)]);
    $first = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $first, $this->product, $this->rack, '1', $keys[0]))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($first, evidence: [$this->evidence])))->toBe('COMMITTED');

    // The first serial is gone now, the lost one is back.
    $second = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $second, $this->product, $this->rack, '1', $keys[1]))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($second, evidence: [$this->evidence])))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('serial_key', $keys[0])->value('state'))->toBe('LOST')
        ->and(DB::table('serial_units')->where('serial_key', $keys[1])->value('state'))->toBe('IN_STOCK')
        ->and(DB::table('evidence_links')->where('evidence_document_id', $this->evidence)->whereNotNull('stock_movement_id')->count())->toBe(3);
    countClean();
});

it('never restores a serial\'s loss through ROUTE-197: the finding\'s serial is what the count saw (L-48; AX-07)', function () {
    $lot = StockWorld::lot($this->actor, $this->company, $this->product, $this->unit, $this->rack, '2', '1000');
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    $kept = (string) DB::table('serial_units')->where('id', StockWorld::serial($this->product, $lot, $this->rack))->value('serial_key');
    StockWorld::serial($this->product, $lot, $this->rack);
    $first = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $first, $this->product, $this->rack, '1', $kept))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($first, evidence: [$this->evidence])))->toBe('COMMITTED');
    $lost = (int) DB::table('stock_consumptions')->where('kind', 'LOSS')->value('id');
    $second = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $second, $this->product, $this->rack, '1', $kept))->toBe('COMMITTED')
        ->and(CountWorld::line($this->actor, $second, $this->product, $this->rack, '1', 'SN-B'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($second)))->toBe('COMMITTED');
    $finding = (int) DB::table('stock_count_findings')->where('state', 'OPEN')->value('id');

    expect(CountWorld::attribute($this->owner->id, $finding, $this->company->id, '500', [['consumption' => $lost, 'qty' => '1']], true))->toContain('C-14')
        ->and((string) DB::table('stock_consumption_balances')->where('stock_consumption_id', $lost)->value('restored_qty_base'))->toBe('0.000')
        ->and(DB::table('stock_count_findings')->where('id', $finding)->value('state'))->toBe('OPEN');
    countClean();
});

it('reads OD-10 (b) from the proofs still linked to the case: an unlinked one authorizes nothing (OD-10; DOC.UnlinkEvidence)', function () {
    [$own] = countAcross($this);
    $count = CountWorld::open($this->actor, $this->product, $this->rack);
    expect(CountWorld::line($this->actor, $count, $this->product, $this->rack, '8'))->toBe('COMMITTED')
        ->and(CountWorld::apply($this->actor, CountWorld::entry($count, pending: '2', evidence: [$this->evidence])))->toBe('COMMITTED');
    $case = (int) countCase()->id;
    $later = AdjustmentWorld::evidence(['recorded_at' => now()->addMinute()]);
    DB::table('evidence_links')->insert(['evidence_document_id' => $later, 'evidence_scope' => 'POOL', 'company_id' => null, 'role' => 'PRIMARY_PROOF', 'unattributed_loss_case_id' => $case, 'recorded_at' => now()->addMinute(), 'recorded_by' => $this->owner->id, 'unlinked_at' => now()->addMinutes(2), 'unlinked_by' => $this->owner->id, 'unlink_reason' => 'Bukti keliru']);

    expect(CountWorld::resolveEvidence($this->both, $case, [['lot' => $own, 'qty' => '2']], []))->toContain('OD-10')
        ->and(DB::table('unattributed_loss_resolutions')->where('unattributed_loss_case_id', $case)->exists())->toBeFalse();
    countClean();
});
