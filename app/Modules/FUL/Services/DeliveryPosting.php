<?php

namespace App\Modules\FUL\Services;

use App\Modules\FUL\Data\ShipmentFigures;
use App\Modules\FUL\Models\Delivery;
use App\Modules\OPS\Services\CorrectionCases;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\DemandPosting;
use App\Modules\PRJ\Services\LineScope;
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
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * AX-09 for a shipment (WF-FUL-02; CM-08; PX-01–PX-08; C-30): "Catat Pengiriman" records what reached the client — the
 * recipient, the date, the condition and the proof, per shipment line the quantity at the shipment's unit snapshot —, a
 * difference or a condition problem opening its DELIVERY_DISCREPANCY case in the same command (SF-CASE), and "Koreksi
 * Catatan Pengiriman" the exact reversal line of an entry error, then the correct line, on the same delivery. Both re-read
 * under the project's and the lines' guards: never above net shipped − net delivered − what a closure holds back, a likely
 * duplicate of the same shipment line, quantity and date REJECTED with its matches until the actor confirms them with a
 * reason (L-49; BD-13). A delivery never touches stock (BR-INV-02; FS-03).
 */
final class DeliveryPosting
{
    /** Per shipment line: net shipped, net delivered, closed for refusal or non-delivery, and returned undelivered since (PX-08). */
    private const FIGURES = <<<'SQL'
        (select coalesce(sum(c.qty_base - b.restored_qty_base - b.reclassified_qty_base - b.substituted_qty_base), 0) from stock_consumptions c join stock_consumption_balances b on b.stock_consumption_id = c.id where c.dispatch_line_id = d.id) as shipped,
        (select coalesce(sum(case when x.reverses_delivery_line_id is null then x.qty_base else -x.qty_base end), 0) from delivery_lines x where x.dispatch_line_id = d.id) as delivered,
        (select coalesce(sum(k.closed_qty_base), 0) from delivery_closures k where k.dispatch_line_id = d.id and k.closure_kind <> 'LOST_IN_TRANSIT' and not exists (select 1 from delivery_closures s where s.supersedes_id = k.id)) as closed,
        (select coalesce(sum(r.qty_base), 0) from stock_restorations r join stock_consumptions c on c.id = r.stock_consumption_id where c.dispatch_line_id = d.id and r.kind = 'UNDELIVERED_RETURN') as returned
        SQL;

    public function __construct(
        private readonly DemandPosting $demand,
        private readonly CorrectionCases $cases,
        private readonly AuditRecorder $audit,
    ) {}

    /** AX-09's set: the project, its lines' guards (LK-19), the proof (LK-29); a discrepancy case is opened under the project's lock. */
    public static function locks(int $projectId, array $projectLineIds, ?int $evidenceId): LockSet
    {
        return LineScope::locks($projectId, array_values(array_unique($projectLineIds)), $evidenceId);
    }

    /** CM-08's set: the project and the guard of the corrected line's project line (LK-19). */
    public static function correctionLocks(int $projectId, int $lineId): LockSet
    {
        $projectLine = DB::table('delivery_lines')->where('id', $lineId)->value('project_line_id');

        return self::locks($projectId, is_numeric($projectLine) ? [(int) $projectLine] : [], null);
    }

    /**
     * The delivery and its lines, the guards moved, the proof linked, a discrepancy case opened when the actor names one.
     *
     * @param  list<array{shipment: int, quantity: string}>  $entered
     * @param  array{inspector: string, result: string}|null  $inspection  the client's inspection facts, for DOC-10
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms
     * @return int the delivery
     */
    public function record(CommandContext $context, ProjectRef $project, string $businessDate, bool $prefilled, string $recipient, ?string $condition, ?string $discrepancy, ?array $inspection, array $entered, int $evidenceId, array $acknowledged, ?string $reason): int
    {
        self::open($project->id);
        BusinessDateRule::check($businessDate, $prefilled);
        $shipments = self::shipments(array_column($entered, 'shipment'));
        $rows = [];
        $taken = [];

        foreach ($entered as $number => $item) {
            $line = $shipments[$item['shipment']] ?? null;

            if ($line === null || (int) Row::text($line, 'project_id') !== $project->id) {
                throw Refused::rejected('C-14', ['reason' => 'shipment']);
            }

            $base = ShipmentFigures::base($item['quantity'], (int) Row::text($line, 'ratio_to_base'), (int) Row::text($line, 'quantity_scale'))
                ?? throw Refused::rejected('C-09', ['reason' => 'scale', 'line' => Row::text($line, 'line_no')]);
            $remainder = self::remainder($line)->minus($taken[$item['shipment']] ?? BigDecimal::zero());

            if ($base->isGreaterThan($remainder)) {
                throw Refused::rejected('C-30', ['reason' => 'remainder', 'line' => Row::text($line, 'line_no'), 'remainder' => (string) $remainder]);
            }

            $taken[$item['shipment']] = ($taken[$item['shipment']] ?? BigDecimal::zero())->plus($base);
            $rows[] = ['item' => $item, 'line' => $line, 'number' => $number + 1, 'base' => (string) $base];
        }

        DuplicateWarning::check('L-49', self::likelyDuplicates(array_map(fn (array $row): array => ['shipment' => $row['item']['shipment'], 'base' => $row['base']], $rows), $businessDate), $acknowledged, $reason);

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $delivery = new Delivery;
        $delivery->forceFill([
            'company_id' => $project->companyId, 'project_id' => $project->id, 'business_date' => $businessDate, 'recipient_name' => $recipient, 'condition_note' => $condition,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ])->save();
        // The lines in one statement, the guards in one more (GU-01; QB-13).
        DB::table('delivery_lines')->insert(array_map(fn (array $row): array => self::lineRow($delivery->id, $project->companyId, $row['line'], $row['item']['quantity'], $row['base'], null, $commandId, $row['number'], $now, $actor), $rows));
        $deltas = [];
        $audited = [];

        foreach ($rows as $row) {
            $projectLine = (int) Row::text($row['line'], 'project_line_id');
            $deltas[$projectLine] = (string) BigDecimal::of($deltas[$projectLine] ?? '0')->plus($row['base']);
            $audited['line_'.Row::text($row['line'], 'line_no')] = $row['base'];
        }

        $this->demand->delivered($context, $deltas);
        DB::table('evidence_links')->insert([
            'evidence_document_id' => $evidenceId, 'evidence_scope' => 'COMPANY', 'company_id' => $project->companyId, 'role' => 'PRIMARY_PROOF',
            'delivery_id' => $delivery->id, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $case = $discrepancy === null ? null : $this->cases->open($context, AuthorityClass::Adm, $project->companyId, 'DELIVERY_DISCREPANCY', $discrepancy, $project->id);

        if ($case !== null) {
            $context->created((new LockSet)->add('correction_cases', ['id' => $case->id]));
        }

        // A confirmed likely duplicate commits under the acknowledged variant, listed in QS-21 and the Owner's review (QS-14).
        $this->audit->record(
            action: $acknowledged === [] ? 'FUL.DeliveryRecorded' : 'FUL.DeliveryDuplicateConfirmed',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $actor,
            companyId: $project->companyId,
            entityType: 'project',
            entityId: $project->id,
            entityPublicId: $project->publicId,
            reason: $acknowledged === [] ? null : $reason,
            commandId: $commandId,
            change: AuditChange::created([
                'recipient' => $recipient, 'condition' => $condition, 'lines' => $audited,
                ...($inspection === null ? [] : ['inspection' => $inspection]),
                ...($case === null ? [] : ['case' => $case->public_id]),
                ...($acknowledged === [] ? [] : ['matches' => $acknowledged]),
            ]),
            linkage: AuditLinkage::make()->link(AuditRelation::Affects, 'deliveries', $delivery->public_id),
            businessDate: $businessDate,
        );

        return $delivery->id;
    }

    /**
     * An entry error corrected (CM-08): the exact reversal of the delivery's line, at most once, then the correct quantity
     * on the same delivery when there is one — stock untouched, the guard moved by the net difference. An issued BAST or
     * inspection record of the delivery is revised on PG-036 from the facts that stand after it (WORKFLOWS §8).
     *
     * @return int the delivery
     */
    public function correct(CommandContext $context, ProjectRef $project, int $deliveryId, int $lineId, ?string $quantity, string $reason, string $businessDate, bool $prefilled): int
    {
        self::open($project->id);
        BusinessDateRule::check($businessDate, $prefilled);
        $line = DB::table('delivery_lines as l')->join('deliveries as d', 'd.id', '=', 'l.delivery_id')->where('l.id', $lineId)->where('l.delivery_id', $deliveryId)->where('d.project_id', $project->id)
            ->first(['l.dispatch_line_id', 'l.entered_qty', 'l.qty_base', 'l.reverses_delivery_line_id', 'd.public_id']);

        if ($line === null || Row::nullable($line, 'reverses_delivery_line_id') !== null || Row::nullable($line, 'dispatch_line_id') === null) {
            throw Refused::rejected('C-30', ['reason' => 'not_reversible']);
        }

        if (DB::table('delivery_lines')->where('reverses_delivery_line_id', $lineId)->exists()) {
            throw Refused::rejected('C-30', ['reason' => 'reversed']);
        }

        $dispatchLine = (int) Row::text($line, 'dispatch_line_id');
        $shipment = self::shipments([$dispatchLine])[$dispatchLine] ?? throw Refused::rejected('C-14', ['reason' => 'shipment']);
        $wrong = BigDecimal::of(Row::text($line, 'qty_base'));
        $right = $quantity === null ? null : (ShipmentFigures::base($quantity, (int) Row::text($shipment, 'ratio_to_base'), (int) Row::text($shipment, 'quantity_scale'))
            ?? throw Refused::rejected('C-09', ['reason' => 'scale', 'line' => Row::text($shipment, 'line_no')]));
        // The reversal frees what the wrong line took before the correct one is held to the remainder.
        $remainder = self::remainder($shipment)->plus($wrong);

        if ($right !== null && $right->isGreaterThan($remainder)) {
            throw Refused::rejected('C-30', ['reason' => 'remainder', 'line' => Row::text($shipment, 'line_no'), 'remainder' => (string) $remainder]);
        }

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $rows = [self::lineRow($deliveryId, $project->companyId, $shipment, Row::text($line, 'entered_qty'), (string) $wrong, $lineId, $commandId, 1, $now, $actor)];

        if ($right !== null) {
            $rows[] = self::lineRow($deliveryId, $project->companyId, $shipment, (string) $quantity, (string) $right, null, $commandId, 2, $now, $actor);
        }

        DB::table('delivery_lines')->insert($rows);
        $net = ($right ?? BigDecimal::zero())->minus($wrong);

        if (! $net->isZero()) {
            $this->demand->delivered($context, [(int) Row::text($shipment, 'project_line_id') => (string) $net]);
        }

        $key = 'line_'.Row::text($shipment, 'line_no');
        $this->audit->record(
            action: 'FUL.DeliveryCorrected',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $actor,
            companyId: $project->companyId,
            entityType: 'project',
            entityId: $project->id,
            entityPublicId: $project->publicId,
            reason: $reason,
            commandId: $commandId,
            change: AuditChange::updated(['lines' => [$key => (string) $wrong]], ['lines' => [$key => (string) ($right ?? '0')]]),
            linkage: AuditLinkage::make()->link(AuditRelation::Reverses, 'deliveries', Row::text($line, 'public_id')),
            businessDate: $businessDate,
        );

        return $deliveryId;
    }

    /**
     * The shipment lines by id with their snapshot and figures, read under their project line's guard.
     *
     * @param  list<int>  $dispatchLineIds
     * @return array<int, object>
     */
    public static function shipments(array $dispatchLineIds): array
    {
        if ($dispatchLineIds === []) {
            return [];
        }

        $found = [];

        foreach (DB::table('dispatch_lines as d')->join('project_lines as pl', 'pl.id', '=', 'd.project_line_id')->whereIn('d.id', array_values(array_unique($dispatchLineIds)))
            ->get(['d.id', 'd.project_id', 'd.company_id', 'd.project_line_id', 'd.product_id', 'd.quantity_scale', 'd.entered_unit_id', 'd.ratio_to_base', 'pl.line_no', DB::raw(self::FIGURES)])->all() as $row) {
            $found[(int) Row::text($row, 'id')] = $row;
        }

        return $found;
    }

    /** What a shipment line can still deliver or close: net shipped − net delivered − what a closure holds back (PX-08). */
    public static function remainder(object $shipment): BigDecimal
    {
        return ShipmentFigures::remainder(BigDecimal::of(Row::text($shipment, 'shipped')), BigDecimal::of(Row::text($shipment, 'delivered')), self::held($shipment));
    }

    /** The closed quantity of refusals and non-deliveries the dispatch's restoration has not yet brought back (SF-RESTORE). */
    public static function held(object $shipment): BigDecimal
    {
        return ShipmentFigures::held(BigDecimal::of(Row::text($shipment, 'closed')), BigDecimal::of(Row::text($shipment, 'returned')));
    }

    /** The project open for fulfilment (L-11). */
    public static function open(int $projectId): void
    {
        if (! in_array(DB::table('projects')->where('id', $projectId)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
            throw Refused::rejected('L-11', ['reason' => 'closed']);
        }
    }

    /**
     * The deliveries already standing for the same shipment line, quantity and date (BD-13), unreversed lines only.
     *
     * @param  list<array{shipment: int, base: string}>  $lines
     * @return list<string> the deliveries' public identifiers
     */
    public static function likelyDuplicates(array $lines, string $businessDate): array
    {
        if ($lines === []) {
            return [];
        }

        $pairs = implode(' or ', array_fill(0, count($lines), '(l.dispatch_line_id = ? and l.qty_base = ?::numeric)'));
        $bindings = [];

        foreach ($lines as $line) {
            array_push($bindings, $line['shipment'], $line['base']);
        }

        $matches = [];

        foreach (DB::select(<<<SQL
            select distinct d.public_id from delivery_lines l join deliveries d on d.id = l.delivery_id
            where ({$pairs}) and d.business_date = ?::date and l.reverses_delivery_line_id is null
                and not exists (select 1 from delivery_lines r where r.reverses_delivery_line_id = l.id)
            order by d.public_id
            SQL, [...$bindings, $businessDate]) as $row) {
            if (is_object($row)) {
                $matches[] = Row::text($row, 'public_id');
            }
        }

        return $matches;
    }

    /**
     * A replayed refusal's current state (CI-05): the likely duplicates, or the line now above its remainder.
     *
     * @param  list<array{shipment: int, quantity: string}>  $entered
     * @return array<string, mixed>|null
     */
    public static function refusalState(?string $code, array $entered, string $businessDate): ?array
    {
        if ($code !== 'L-49' && $code !== 'C-30') {
            return null;
        }

        $shipments = self::shipments(array_column($entered, 'shipment'));
        $bases = [];

        foreach ($entered as $item) {
            $line = $shipments[$item['shipment']] ?? null;
            $base = $line === null ? null : ShipmentFigures::base($item['quantity'], (int) Row::text($line, 'ratio_to_base'), (int) Row::text($line, 'quantity_scale'));

            if ($line === null || $base === null) {
                continue;
            }

            if ($code === 'C-30' && $base->isGreaterThan(self::remainder($line))) {
                return ['reason' => 'remainder', 'line' => Row::text($line, 'line_no'), 'remainder' => (string) self::remainder($line)];
            }

            $bases[] = ['shipment' => $item['shipment'], 'base' => (string) $base];
        }

        return $code === 'L-49' ? ['matches' => self::likelyDuplicates($bases, $businessDate)] : null;
    }

    /** @return array<string, mixed> a delivery line at the shipment's unit snapshot */
    private static function lineRow(int $deliveryId, int $companyId, object $shipment, string $entered, string $base, ?int $reverses, string $commandId, int $number, mixed $now, int $actor): array
    {
        return [
            'delivery_id' => $deliveryId, 'company_id' => $companyId, 'project_line_id' => (int) Row::text($shipment, 'project_line_id'),
            'product_id' => (int) Row::text($shipment, 'product_id'), 'quantity_scale' => (int) Row::text($shipment, 'quantity_scale'),
            'dispatch_line_id' => (int) Row::text($shipment, 'id'), 'dropship_confirmation_line_id' => null,
            'entered_qty' => $entered, 'entered_unit_id' => (int) Row::text($shipment, 'entered_unit_id'), 'ratio_to_base' => (int) Row::text($shipment, 'ratio_to_base'),
            'qty_base' => $base, 'reverses_delivery_line_id' => $reverses,
            'command_id' => $commandId, 'command_line_no' => $number, 'recorded_at' => $now, 'recorded_by' => $actor,
        ];
    }
}
