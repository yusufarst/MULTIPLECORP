<?php

namespace App\Modules\PRJ\Services;

use App\Modules\PRJ\Contracts\ProjectLifecycleParticipant;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * AX-14, a confirmed line's scope (SF-CANCEL-LINE; WF-FUL-01; L-21; PX-04; PX-08): "Batalkan Sisa" cancels the unfilled
 * remainder of lines in place — one `line_scope_cancellations` row per line, with its reason —, "Ubah Cara Pemenuhan"
 * splits a line's unstarted remainder into a new line with the same commercial snapshot, its pin and another mode,
 * `split_from_line_id` naming its origin. Each re-reads the remainder under the lines' guards (LK-19): demand less
 * what is dispatched, confirmed to the client, handed over and already cancelled — never below net shipped, confirmed
 * or handed-over quantity —, a quantity above it REJECTED with the current state and never CONFLICT (ST-06). The
 * reservation above the new remainder is released by the lifecycle participants first (IP-20; INV, TASK-038).
 */
final class LineScope
{
    private const REMAINDER = 'b.demand_qty_base - b.dispatched_net_qty_base - b.dropship_confirmed_net_qty_base - b.handed_over_qty_base - b.cancelled_qty_base';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly Container $app,
    ) {}

    /**
     * AX-14's locks on existing rows: the project (LK-01), the lines' guards (LK-19), the evidence where linked (LK-29).
     *
     * @param  list<int>  $lineIds
     */
    public static function locks(int $projectId, array $lineIds, ?int $evidenceId = null): LockSet
    {
        $set = (new LockSet)->add('projects', ['id' => $projectId]);

        foreach ($lineIds as $line) {
            $set = $set->add('project_line_balances', ['project_line_id' => $line]);
        }

        return $evidenceId === null ? $set : $set->add('evidence_documents', ['id' => $evidenceId]);
    }

    /** A confirmed line's unfilled remainder in base units, read under its guard; null when the line is not confirmed in the project. */
    public static function remainder(int $projectId, int $lineId): ?string
    {
        $row = DB::table('project_line_balances as b')->join('project_lines as l', 'l.id', '=', 'b.project_line_id')
            ->where('l.project_id', $projectId)->where('l.id', $lineId)->whereNotNull('l.current_pin_id')
            ->selectRaw(self::REMAINDER.' as remainder')
            ->first();

        return $row === null ? null : Row::text($row, 'remainder');
    }

    /**
     * "Batalkan Sisa": each line's remainder cancelled by the given base quantity.
     *
     * @param  array<int, string>  $quantities  base quantity by project line
     */
    public function cancel(CommandContext $context, int $projectId, int $companyId, array $quantities, string $reason, string $businessDate, ?int $evidenceId): void
    {
        $this->within($projectId, $quantities);
        $this->release($context, $projectId, $quantities);
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $number = 0;
        $deltas = [];

        foreach ($quantities as $line => $quantity) {
            $number++;
            DB::insert(<<<'SQL'
                insert into line_scope_cancellations (project_line_id, company_id, product_id, quantity_scale, qty_base, reason, decided_by, business_date,
                    command_id, command_line_no, recorded_at, recorded_by)
                select l.id, l.company_id, l.product_id, l.quantity_scale, ?::numeric, ?, ?, ?, ?, ?, ?, ? from project_lines l where l.id = ?
                SQL, [$quantity, $reason, $actor, $businessDate, $commandId, $number, $now, $actor, $line]);
            $deltas[] = ['key' => ['project_line_id' => $line], 'deltas' => ['cancelled_qty_base' => $quantity]];
        }

        // The lines' guards through the shared writer, one statement (GU-01).
        GuardUpdate::apply($context->connection, 'project_line_balances', $deltas);

        if ($evidenceId !== null) {
            DB::table('evidence_links')->insert([
                'evidence_document_id' => $evidenceId, 'evidence_scope' => 'COMPANY', 'company_id' => $companyId, 'role' => 'SUPPORTING',
                'project_id' => $projectId, 'command_id' => $commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
        }

        $lines = [];

        foreach ($quantities as $line => $quantity) {
            $number = DB::table('project_lines')->where('id', $line)->value('line_no');
            $lines['line_'.(is_numeric($number) ? (string) $number : (string) $line)] = $quantity;
        }

        $this->record($context, 'PRJ.RemainingScopeCancelled', AuthorityClass::AdmPlus, $projectId, $companyId, $reason, $businessDate, AuditChange::updated([], ['lines' => $lines]));
    }

    /**
     * "Ubah Cara Pemenuhan": the line's unstarted remainder split into a new line of another mode with the same commercial
     * snapshot and its pin under the current confirmation, the original line's demand lowered by it (L-21; no re-approval).
     * The remainder keeps its entered unit when it is whole in it, else it is entered in the base unit at the base price.
     *
     * @return int the new line
     */
    public function split(CommandContext $context, int $projectId, int $companyId, int $lineId, string $mode, string $reason): int
    {
        $remainder = self::remainder($projectId, $lineId);
        $line = DB::table('project_lines')->where('id', $lineId)->first(['kind', 'fulfillment_mode', 'line_no']);

        if ($remainder === null || ! self::positive($remainder)) {
            throw Refused::rejected('L-21', ['reason' => 'started']);
        }

        if ($line === null || Row::text($line, 'kind') === 'SERVICE' || $mode === 'SERVICE' || Row::text($line, 'fulfillment_mode') === $mode) {
            throw Refused::rejected('L-21', ['reason' => 'mode']);
        }

        $this->release($context, $projectId, [$lineId => $remainder]);
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        // The new line follows the highest number, under the project's lock (line_no is never reused for a cited line).
        $highest = DB::table('project_lines')->where('project_id', $projectId)->max('line_no');
        $next = (is_numeric($highest) ? (int) $highest : 0) + 1;
        $inserted = DB::selectOne(<<<'SQL'
            insert into project_lines (project_id, company_id, line_no, kind, product_id, quantity_scale, description, entered_qty, entered_unit_id,
                ratio_to_base, qty_base, unit_price, fulfillment_mode, split_from_line_id, command_id, command_line_no, recorded_at, recorded_by)
            select o.project_id, o.company_id, ?::integer, o.kind, o.product_id,
                o.quantity_scale, p.description,
                case when mod(r.qty, p.ratio_to_base) = 0 then r.qty / p.ratio_to_base else r.qty end,
                case when mod(r.qty, p.ratio_to_base) = 0 then p.entered_unit_id else (select pu.unit_id from product_units pu where pu.product_id = o.product_id and pu.is_base) end,
                case when mod(r.qty, p.ratio_to_base) = 0 then p.ratio_to_base else 1 end,
                r.qty,
                case when mod(r.qty, p.ratio_to_base) = 0 then p.unit_price else round(p.unit_price / p.ratio_to_base, 2) end,
                ?, o.id, ?, 1, ?, ?
            from project_lines o join confirmation_line_pins p on p.id = o.current_pin_id cross join (select ?::numeric as qty) r
            where o.id = ?
            returning id, line_no
            SQL, [$next, $mode, $commandId, $now, $actor, $remainder, $lineId]);
        $id = is_object($inserted) ? (int) Row::text($inserted, 'id') : 0;
        $this->repin($context, $projectId, $lineId, $id, $remainder, $reason);
        // The new line's guard born (FU-09), then the remainder moved from the origin's demand to it in one statement (GU-01).
        GuardUpdate::bear($context->connection, 'project_line_balances', ['project_line_id' => $id], ['fulfillment_mode' => $mode, 'recorded_at' => $now]);
        GuardUpdate::apply($context->connection, 'project_line_balances', [
            ['key' => ['project_line_id' => $lineId], 'deltas' => ['demand_qty_base' => '-'.$remainder]],
            ['key' => ['project_line_id' => $id], 'deltas' => ['demand_qty_base' => $remainder]],
        ]);
        $this->record($context, 'PRJ.LineReplanned', AuthorityClass::Adm, $projectId, $companyId, $reason, null, AuditChange::updated(
            ['line' => Row::text($line, 'line_no'), 'mode' => Row::text($line, 'fulfillment_mode')],
            ['line' => is_object($inserted) ? Row::text($inserted, 'line_no') : '', 'mode' => $mode, 'qty_base' => $remainder],
        ));

        return $id;
    }

    /**
     * The re-plan's pins (BR-SN-01; DATABASE §9): a confirmation of the same kind superseding the current one in its chain
     * (CM-27's link) — the re-plan's record, no re-approval —, pinning the new line at its quantity and price with its share
     * of the tax, the origin at its pinned quantity less the remainder with the rest of the value and tax, and every other
     * line as it stood; the pointers moved to them. The current pins' sum stays the confirmed value, and each line's pin its demand.
     */
    private function repin(CommandContext $context, int $projectId, int $originId, int $newId, string $remainder, string $reason): void
    {
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $created = DB::selectOne(<<<'SQL'
            insert into commercial_confirmations (project_id, company_id, kind, quotation_revision_id, business_date, decided_by, reason, supersedes_confirmation_id,
                command_id, command_line_no, recorded_at, recorded_by)
            select c.project_id, c.company_id, c.kind, c.quotation_revision_id, (?::timestamptz at time zone 'Asia/Jakarta')::date, ?, ?, c.id, ?, 1, ?, ?
            from commercial_confirmations c where c.project_id = ? order by c.id desc limit 1
            returning id
            SQL, [$now, $actor, $reason, $commandId, $now, $actor, $projectId]);
        $confirmation = is_object($created) ? (int) Row::text($created, 'id') : 0;
        $columns = 'commercial_confirmation_id, company_id, project_line_id, product_id, quantity_scale, description, entered_qty, entered_unit_id, ratio_to_base, '
            .'qty_base, unit_price, sales_value, tax_components, command_id, command_line_no, recorded_at';
        // The new line: its own quantity and price, its value from them, the origin pin's tax in proportion to that value.
        DB::insert('insert into confirmation_line_pins ('.$columns.') '.<<<'SQL'
            select ?, l.company_id, l.id, l.product_id, l.quantity_scale, l.description, l.entered_qty, l.entered_unit_id, l.ratio_to_base, l.qty_base, l.unit_price,
                round(l.entered_qty * l.unit_price, 2),
                coalesce((select jsonb_agg(c || jsonb_build_object('base', round(l.entered_qty * l.unit_price, 2)::text,
                    'amount', round((c->>'amount')::numeric * round(l.entered_qty * l.unit_price, 2) / nullif(p.sales_value, 0), 2)::text) order by n)
                    from jsonb_array_elements(p.tax_components) with ordinality as t(c, n)), '[]'::jsonb),
                ?, 1, ?
            from project_lines l join project_lines o on o.id = l.split_from_line_id join confirmation_line_pins p on p.id = o.current_pin_id
            where l.id = ?
            SQL, [$confirmation, $commandId, $now, $newId]);
        // The origin: its pinned quantity less the remainder — in its entered unit when whole, else in the base unit —, the rest of its value and tax.
        DB::insert('insert into confirmation_line_pins ('.$columns.') '.<<<'SQL'
            select ?, o.company_id, o.id, p.product_id, p.quantity_scale, p.description,
                case when mod(r.qty, p.ratio_to_base) = 0 then p.entered_qty - r.qty / p.ratio_to_base else p.qty_base - r.qty end,
                case when mod(r.qty, p.ratio_to_base) = 0 then p.entered_unit_id else (select pu.unit_id from product_units pu where pu.product_id = p.product_id and pu.is_base) end,
                case when mod(r.qty, p.ratio_to_base) = 0 then p.ratio_to_base else 1 end,
                p.qty_base - r.qty,
                case when mod(r.qty, p.ratio_to_base) = 0 then p.unit_price else round(p.unit_price / p.ratio_to_base, 2) end,
                p.sales_value - s.sales_value,
                coalesce((select jsonb_agg(c || jsonb_build_object('base', (p.sales_value - s.sales_value)::text,
                    'amount', ((c->>'amount')::numeric - coalesce((sc->>'amount')::numeric, 0))::text) order by n)
                    from jsonb_array_elements(p.tax_components) with ordinality as t(c, n)
                    left join jsonb_array_elements(s.tax_components) with ordinality as u(sc, m) on m = n), '[]'::jsonb),
                ?, 2, ?
            from project_lines o join confirmation_line_pins p on p.id = o.current_pin_id
            join confirmation_line_pins s on s.commercial_confirmation_id = ? and s.project_line_id = ?
            cross join (select ?::numeric as qty) r
            where o.id = ?
            SQL, [$confirmation, $commandId, $now, $confirmation, $newId, $remainder, $originId]);
        // Every other line as it stood.
        DB::insert('insert into confirmation_line_pins ('.$columns.') '.<<<'SQL'
            select ?, p.company_id, p.project_line_id, p.product_id, p.quantity_scale, p.description, p.entered_qty, p.entered_unit_id, p.ratio_to_base, p.qty_base,
                p.unit_price, p.sales_value, p.tax_components, ?, 2 + row_number() over (order by l.line_no), ?
            from project_lines l join confirmation_line_pins p on p.id = l.current_pin_id
            where l.project_id = ? and l.id <> ? and l.id <> ?
            SQL, [$confirmation, $commandId, $now, $projectId, $originId, $newId]);
        DB::update('update project_lines l set current_pin_id = p.id from confirmation_line_pins p where p.commercial_confirmation_id = ? and p.project_line_id = l.id', [$confirmation]);
    }

    /**
     * ST-06 and PX-08: each quantity within its line's remainder re-read under the guard, else REJECTED with it.
     *
     * @param  array<int, string>  $quantities
     */
    private function within(int $projectId, array $quantities): void
    {
        foreach ($quantities as $line => $quantity) {
            $remainder = self::remainder($projectId, $line);

            if ($remainder === null) {
                throw Refused::rejected('L-20', ['reason' => 'unconfirmed']);
            }

            $above = DB::selectOne('select ?::numeric > ?::numeric as above', [$quantity, $remainder]);

            if (! self::positive($quantity) || (is_object($above) && ($above->above ?? false) === true)) {
                $number = DB::table('project_lines')->where('id', $line)->value('line_no');

                throw Refused::rejected('ST-06', ['line' => is_numeric($number) ? (string) $number : '', 'remainder' => $remainder]);
            }
        }
    }

    /** @param  array<int, string>  $released */
    private function release(CommandContext $context, int $projectId, array $released): void
    {
        foreach ($this->app->tagged(ProjectLifecycleParticipant::TAG) as $participant) {
            if ($participant instanceof ProjectLifecycleParticipant) {
                $participant->scopeReduced($context, $projectId, $released);
            }
        }
    }

    private function record(CommandContext $context, string $action, AuthorityClass $class, int $projectId, int $companyId, string $reason, ?string $businessDate, AuditChange $change): void
    {
        $publicId = DB::table('projects')->where('id', $projectId)->value('public_id');
        $this->audit->record(
            action: $action,
            authorityClass: $class,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'project',
            entityId: $projectId,
            entityPublicId: is_string($publicId) ? $publicId : null,
            reason: $reason,
            commandId: $context->request->commandId,
            change: $change,
            businessDate: $businessDate,
        );
    }

    private static function positive(string $quantity): bool
    {
        return preg_match('/^\d+(\.\d+)?$/', $quantity) === 1 && preg_match('/^0+(\.0+)?$/', $quantity) !== 1;
    }
}
