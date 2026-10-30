<?php

namespace App\Modules\FUL\Services;

use App\Modules\FUL\Models\ServiceHandover;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Services\DemandPosting;
use App\Modules\PRJ\Services\LineScope;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\DuplicateWarning;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * AX-09 for a service line (WF-FUL-04; CM-27; L-39; C-30): "Catat Serah Terima" records the handover — its date,
 * recipient and per line the scope performed and the quantity at the pinned entered-unit snapshot (BR-INV-03; C-09) —
 * and "Balikkan" its exact reversal line with the reason, at most once. Both re-read under the project's and the
 * lines' guards: Σ handed over ≤ the pinned demand, the excess REJECTED with the remainder, a likely duplicate of the
 * same line, quantity and date REJECTED with its matches until the actor confirms them with a reason (L-49; BD-13).
 * A service never moves stock (BR-INV-11; FS-05). In a constant number of statements whatever the lines (QB-13).
 */
final class HandoverPosting
{
    public function __construct(
        private readonly DemandPosting $demand,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * The handover and its lines, the guards moved, the evidence linked.
     *
     * @param  list<array{line: int, quantity: string, scope: string}>  $entered
     * @param  list<string>  $acknowledged  the likely duplicates the actor confirms
     * @return int the handover
     */
    public function record(CommandContext $context, ProjectRef $project, string $businessDate, bool $prefilled, string $recipient, array $entered, ?int $evidenceId, array $acknowledged, ?string $reason): int
    {
        self::open($project->id);
        BusinessDateRule::check($businessDate, $prefilled);
        $lines = self::lines($project->id, array_column($entered, 'line'));
        $rows = [];

        foreach ($entered as $number => $item) {
            $line = $lines[$item['line']] ?? throw Refused::rejected('L-20', ['reason' => 'unconfirmed']);
            $no = Row::text($line, 'line_no');

            if (Row::text($line, 'fulfillment_mode') !== 'SERVICE') {
                throw Refused::rejected('L-39', ['reason' => 'not_service', 'line' => $no]);
            }

            $quantity = BigDecimal::of($item['quantity'])->multipliedBy(Row::text($line, 'ratio_to_base'));

            if (! $quantity->isEqualTo($quantity->toScale(max(0, (int) Row::text($line, 'quantity_scale')), RoundingMode::Down))) {
                throw Refused::rejected('C-09', ['reason' => 'scale', 'line' => $no, 'scale' => Row::text($line, 'quantity_scale')]);
            }

            if ($quantity->isGreaterThan(Row::text($line, 'remainder'))) {
                throw Refused::rejected('C-30', ['reason' => 'remainder', 'line' => $no, 'remainder' => Row::text($line, 'remainder')]);
            }

            $rows[] = ['item' => $item, 'line' => $line, 'number' => $number + 1, 'base' => (string) $quantity->toScale(3, RoundingMode::Down)];
        }

        DuplicateWarning::check('L-49', self::likelyDuplicates(array_map(fn (array $row): array => ['line' => $row['item']['line'], 'base' => $row['base']], $rows), $businessDate), $acknowledged, $reason);

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $handover = new ServiceHandover;
        $handover->forceFill([
            'company_id' => $project->companyId, 'project_id' => $project->id, 'business_date' => $businessDate, 'recipient_name' => $recipient,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ])->save();
        // The lines in one statement, the guards in one more (GU-01; QB-13).
        DB::table('service_handover_lines')->insert(array_map(fn (array $row): array => [
            'service_handover_id' => $handover->id, 'company_id' => $project->companyId, 'project_line_id' => $row['item']['line'],
            'product_id' => (int) Row::text($row['line'], 'product_id'), 'quantity_scale' => (int) Row::text($row['line'], 'quantity_scale'),
            'entered_qty' => $row['item']['quantity'], 'entered_unit_id' => (int) Row::text($row['line'], 'entered_unit_id'), 'ratio_to_base' => (int) Row::text($row['line'], 'ratio_to_base'),
            'qty_base' => $row['base'], 'scope_note' => $row['item']['scope'], 'reverses_line_id' => null,
            'command_id' => $commandId, 'command_line_no' => $row['number'], 'recorded_at' => $now, 'recorded_by' => $actor,
        ], $rows));
        $deltas = [];
        $audited = [];

        foreach ($rows as $row) {
            $deltas[$row['item']['line']] = $row['base'];
            $audited['line_'.Row::text($row['line'], 'line_no')] = $row['base'];
        }

        $this->demand->handedOver($context, $deltas);

        if ($evidenceId !== null) {
            DB::table('evidence_links')->insert([
                'evidence_document_id' => $evidenceId, 'evidence_scope' => 'COMPANY', 'company_id' => $project->companyId, 'role' => 'SUPPORTING',
                'service_handover_id' => $handover->id, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
        }

        // A confirmed likely duplicate commits under the acknowledged variant, listed in QS-21 and the Owner's review (QS-14).
        $this->audit->record(
            action: $acknowledged === [] ? 'FUL.HandoverRecorded' : 'FUL.HandoverDuplicateConfirmed',
            authorityClass: AuthorityClass::Adm,
            actorUserId: $actor,
            companyId: $project->companyId,
            entityType: 'project',
            entityId: $project->id,
            entityPublicId: $project->publicId,
            reason: $acknowledged === [] ? null : $reason,
            commandId: $commandId,
            change: AuditChange::created(['recipient' => $recipient, 'lines' => $audited, ...($acknowledged === [] ? [] : ['matches' => $acknowledged])]),
            linkage: AuditLinkage::make()->link(AuditRelation::Affects, 'service_handovers', $handover->public_id),
            businessDate: $businessDate,
        );

        return $handover->id;
    }

    /**
     * The exact reversal line of a handover's line recorded in error, with its reason: the same snapshot and quantity,
     * at most once (CM-27; C-30), the guard moved back. An issued BAST it affects is revised or voided on PG-036, which
     * PG-033 then points to (WORKFLOWS §8).
     *
     * @return int the reversal line
     */
    public function reverse(CommandContext $context, ProjectRef $project, int $handoverId, string $handoverPublicId, int $lineId, string $reason): int
    {
        self::open($project->id);
        $line = DB::table('service_handover_lines as l')->where('l.id', $lineId)->where('l.service_handover_id', $handoverId)
            ->join('project_lines as p', 'p.id', '=', 'l.project_line_id')
            ->first(['l.project_line_id', 'l.product_id', 'l.quantity_scale', 'l.entered_qty', 'l.entered_unit_id', 'l.ratio_to_base', 'l.qty_base', 'l.reverses_line_id', 'p.line_no']);

        if ($line === null || Row::nullable($line, 'reverses_line_id') !== null) {
            throw Refused::rejected('C-30', ['reason' => 'not_reversible']);
        }

        if (DB::table('service_handover_lines')->where('reverses_line_id', $lineId)->exists()) {
            throw Refused::rejected('C-30', ['reason' => 'reversed']);
        }

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $reversal = DB::table('service_handover_lines')->insertGetId([
            'service_handover_id' => $handoverId, 'company_id' => $project->companyId, 'project_line_id' => (int) Row::text($line, 'project_line_id'),
            'product_id' => (int) Row::text($line, 'product_id'), 'quantity_scale' => (int) Row::text($line, 'quantity_scale'),
            'entered_qty' => Row::text($line, 'entered_qty'), 'entered_unit_id' => (int) Row::text($line, 'entered_unit_id'), 'ratio_to_base' => (int) Row::text($line, 'ratio_to_base'),
            'qty_base' => Row::text($line, 'qty_base'), 'scope_note' => $reason, 'reverses_line_id' => $lineId,
            'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $this->demand->handedOver($context, [(int) Row::text($line, 'project_line_id') => '-'.Row::text($line, 'qty_base')]);
        $this->audit->record(
            action: 'FUL.HandoverReversed',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $actor,
            companyId: $project->companyId,
            entityType: 'project',
            entityId: $project->id,
            entityPublicId: $project->publicId,
            reason: $reason,
            commandId: $commandId,
            change: AuditChange::updated(['lines' => ['line_'.Row::text($line, 'line_no') => Row::text($line, 'qty_base')]], ['lines' => ['line_'.Row::text($line, 'line_no') => '0']]),
            linkage: AuditLinkage::make()->link(AuditRelation::Reverses, 'service_handovers', $handoverPublicId),
            businessDate: BusinessDate::of($now),
        );

        return $reversal;
    }

    /**
     * The handovers already standing for the same line, quantity and date (BD-13), unreversed ones only: a line reversed
     * as an error is no fact a new handover could duplicate.
     *
     * @param  list<array{line: int, base: string}>  $lines
     * @return list<string> the handovers' public identifiers
     */
    public static function likelyDuplicates(array $lines, string $businessDate): array
    {
        if ($lines === []) {
            return [];
        }

        $pairs = implode(' or ', array_fill(0, count($lines), '(l.project_line_id = ? and l.qty_base = ?::numeric)'));
        $bindings = [];

        foreach ($lines as $line) {
            array_push($bindings, $line['line'], $line['base']);
        }

        $matches = [];

        foreach (DB::select(<<<SQL
            select distinct h.public_id from service_handover_lines l join service_handovers h on h.id = l.service_handover_id
            where ({$pairs}) and h.business_date = ?::date and l.reverses_line_id is null
                and not exists (select 1 from service_handover_lines r where r.reverses_line_id = l.id)
            order by h.public_id
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
     * @param  list<array{line: int, quantity: string, scope: string}>  $entered
     * @return array<string, mixed>|null
     */
    public static function refusalState(?string $code, int $projectId, array $entered, string $businessDate): ?array
    {
        if ($code !== 'L-49' && $code !== 'C-30') {
            return null;
        }

        $lines = self::lines($projectId, array_column($entered, 'line'));
        $bases = [];

        foreach ($entered as $item) {
            $line = $lines[$item['line']] ?? null;

            if ($line === null) {
                continue;
            }

            $base = BigDecimal::of($item['quantity'])->multipliedBy(Row::text($line, 'ratio_to_base'));

            if ($code === 'C-30' && $base->isGreaterThan(Row::text($line, 'remainder'))) {
                return ['reason' => 'remainder', 'line' => Row::text($line, 'line_no'), 'remainder' => Row::text($line, 'remainder')];
            }

            $bases[] = ['line' => $item['line'], 'base' => (string) $base->toScale(3, RoundingMode::Down)];
        }

        return $code === 'L-49' ? ['matches' => self::likelyDuplicates($bases, $businessDate)] : null;
    }

    /** L-11: a closed project takes no handover and no reversal; its correction is a reopening's. */
    private static function open(int $projectId): void
    {
        if (! in_array(DB::table('projects')->where('id', $projectId)->value('state'), ['DRAFT', 'ACTIVE'], true)) {
            throw Refused::rejected('L-11', ['reason' => 'closed']);
        }
    }

    /**
     * The project's confirmed lines by id, with the pin's unit snapshot and the remainder read under their guards.
     *
     * @param  list<int>  $lineIds
     * @return array<int, object>
     */
    private static function lines(int $projectId, array $lineIds): array
    {
        $lines = [];
        $rows = DB::table('project_lines as l')->where('l.project_id', $projectId)->whereIn('l.id', $lineIds)
            ->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')->join('project_line_balances as b', 'b.project_line_id', '=', 'l.id')
            ->get(['l.id', 'l.line_no', 'l.fulfillment_mode', 'l.product_id', 'l.quantity_scale', 'p.entered_unit_id', 'p.ratio_to_base', DB::raw(LineScope::REMAINDER.' as remainder')]);

        foreach ($rows as $row) {
            $lines[(int) Row::text($row, 'id')] = $row;
        }

        return $lines;
    }
}
