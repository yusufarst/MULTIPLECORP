<?php

namespace App\Modules\PRJ\Services;

use App\Modules\PRJ\Contracts\ProjectLifecycleParticipant;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * AX-13, the commercial confirmation, as one PRJ step for every path and kind (WF-PRJ-02; DATABASE §25; BR-PRJ-06): the
 * QUOTATION_APPROVAL kind from the approved revision's lines (ROUTE-157), the NO_QUOTATION kind from the project's own
 * lines on the client's order evidence (ROUTE-158), the MIGRATION kind for an open project carried over (WF-MIG-01),
 * and a superseding confirmation under CM-27 (ROUTE-169). In the caller's transaction, under the project's lock (LK-01):
 * the confirmation; one pin per line with its entered quantity, unit, ratio, base quantity, price, sales value and tax
 * components (BR-INV-03; BR-SN-01) — a confirmed line the new demand leaves out pinned at zero —; the current pin
 * pointers; the reservations adjusted by the lifecycle participants (BR-QUO-02); each line's demand guard and the
 * project's confirmed value (C-05; C-31); the SIPLAH lock (C-51); the evidence linked (LK-29). A demand below what is
 * executed, or a confirmed value below the invoiced, is REJECTED with the path — a return or an invoice revision first
 * (L-20; BR-XD-01).
 */
final class CommercialConfirmation
{
    public const KINDS = ['QUOTATION_APPROVAL', 'NO_QUOTATION', 'MIGRATION'];

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly Container $app,
    ) {}

    /** AX-13's locks on existing rows (LK-01, LK-19, LK-20, LK-29): the project, its lines' guards, its commercial balance and the evidence. */
    public static function locks(int $projectId, ?int $evidenceId = null): LockSet
    {
        $set = (new LockSet)->add('projects', ['id' => $projectId]);

        foreach (DB::table('project_line_balances as b')->join('project_lines as l', 'l.id', '=', 'b.project_line_id')->where('l.project_id', $projectId)->pluck('b.project_line_id') as $line) {
            $set = $set->add('project_line_balances', ['project_line_id' => is_numeric($line) ? (int) $line : 0]);
        }

        if (DB::selectOne('select 1 as found from project_commercial_balances where project_id = ?', [$projectId]) !== null) {
            $set = $set->add('project_commercial_balances', ['project_id' => $projectId]);
        }

        return $evidenceId === null ? $set : $set->add('evidence_documents', ['id' => $evidenceId]);
    }

    /** Whether the project holds a confirmation: one root per project on the no-quotation path (BD-05). */
    public static function confirmed(int $projectId): bool
    {
        return DB::table('commercial_confirmations')->where('project_id', $projectId)->exists();
    }

    /** The project's latest confirmation, which a superseding one names (CM-27). */
    public static function latest(int $projectId): ?int
    {
        $id = DB::table('commercial_confirmations')->where('project_id', $projectId)->orderByDesc('id')->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  ?int  $revisionId  the approved revision for QUOTATION_APPROVAL, null otherwise
     * @param  ?int  $evidenceId  the client's order evidence the confirmation links (ROUTE-158)
     * @param  ?int  $supersedes  the confirmation a superseding one replaces (CM-27), null for a first or a re-confirmation
     * @return int the confirmation
     */
    public function confirm(CommandContext $context, int $projectId, int $companyId, string $kind, ?int $revisionId, string $businessDate, ?string $reason, ?int $evidenceId = null, ?int $supersedes = null): int
    {
        // A DRAFT still lacks the unit or PIC its activation needs: the binding demand is an ACTIVE project's (SM:Project).
        if (DB::table('projects')->where('id', $projectId)->value('state') !== 'ACTIVE') {
            throw Refused::rejected('WF-PRJ-02', ['reason' => 'details']);
        }

        $earlier = self::latest($projectId);
        $now = $context->now();
        $actor = $context->request->actorUserId;
        $commandId = $context->request->commandId;
        $confirmation = (int) DB::table('commercial_confirmations')->insertGetId([
            'project_id' => $projectId,
            'company_id' => $companyId,
            'kind' => $kind,
            'quotation_revision_id' => $kind === 'QUOTATION_APPROVAL' ? $revisionId : null,
            'business_date' => $businessDate,
            'decided_by' => $actor,
            'reason' => $reason,
            'supersedes_confirmation_id' => $supersedes,
            'command_id' => $commandId,
            'command_line_no' => 1,
            'recorded_at' => $now,
            'recorded_by' => $actor,
        ]);

        $this->pin($confirmation, $projectId, $kind, $revisionId, $commandId, $now, BusinessDate::of($now));
        $this->guard($projectId);
        DB::statement('update project_lines l set current_pin_id = p.id from confirmation_line_pins p where p.commercial_confirmation_id = ? and p.project_line_id = l.id', [$confirmation]);

        // A re-confirmation's lower demand first moves the reservations it affects (BR-QUO-02; INV's participant, TASK-038).
        if ($earlier !== null) {
            foreach ($this->app->tagged(ProjectLifecycleParticipant::TAG) as $participant) {
                if ($participant instanceof ProjectLifecycleParticipant) {
                    $participant->reconfirmed($context, $projectId);
                }
            }
        }

        // The guards through the shared writer (ARCHITECTURE §7; GU-01): a first confirmation's rows born (FU-09), then each
        // line's demand and the project's confirmed value moved by their deltas to the current pins.
        $lines = DB::select(<<<'SQL'
            select l.id, l.fulfillment_mode, b.project_line_id is not null as guarded, p.qty_base - coalesce(b.demand_qty_base, 0) as delta
            from project_lines l join confirmation_line_pins p on p.id = l.current_pin_id
            left join project_line_balances b on b.project_line_id = l.id
            where l.project_id = ? order by l.id
            SQL, [$projectId]);
        $deltas = [];

        foreach ($lines as $line) {
            if (! is_object($line)) {
                continue;
            }

            if (($line->guarded ?? false) !== true) {
                GuardUpdate::bear($context->connection, 'project_line_balances', ['project_line_id' => (int) Row::text($line, 'id')], ['fulfillment_mode' => Row::text($line, 'fulfillment_mode'), 'recorded_at' => $now]);
            }

            $deltas[] = ['key' => ['project_line_id' => (int) Row::text($line, 'id')], 'deltas' => ['demand_qty_base' => Row::text($line, 'delta')]];
        }

        if ($deltas !== []) {
            GuardUpdate::apply($context->connection, 'project_line_balances', $deltas);
        }

        $value = DB::selectOne(<<<'SQL'
            select (select count(*) from project_commercial_balances c where c.project_id = ?) as guarded,
                coalesce(sum(p.sales_value), 0) - coalesce((select c.confirmed_sales_value from project_commercial_balances c where c.project_id = ?), 0) as delta
            from project_lines l join confirmation_line_pins p on p.id = l.current_pin_id where l.project_id = ?
            SQL, [$projectId, $projectId, $projectId]);

        if (is_object($value) && Row::text($value, 'guarded') === '0') {
            GuardUpdate::bear($context->connection, 'project_commercial_balances', ['project_id' => $projectId], ['recorded_at' => $now]);
        }

        GuardUpdate::apply($context->connection, 'project_commercial_balances', [['key' => ['project_id' => $projectId], 'deltas' => ['confirmed_sales_value' => is_object($value) ? Row::text($value, 'delta') : '0']]]);
        // C-51: the channel and its SIPLAH details are fixed by the first channel-dependent commit (BR-PRJ-05).
        DB::table('project_siplah_details')->where('project_id', $projectId)->whereNull('locked_at')->update(['locked_at' => $now]);

        if ($evidenceId !== null) {
            DB::table('evidence_links')->insert([
                'evidence_document_id' => $evidenceId,
                'evidence_scope' => 'COMPANY',
                'company_id' => $companyId,
                'role' => 'CLIENT_ORIGINAL',
                'commercial_confirmation_id' => $confirmation,
                'command_id' => $commandId,
                'command_line_no' => 1,
                'recorded_at' => $now,
                'recorded_by' => $actor,
            ]);
        }

        $pins = DB::table('confirmation_line_pins')->where('commercial_confirmation_id', $confirmation)->count();
        $this->audit->record(
            action: $supersedes !== null ? 'PRJ.ConfirmationSuperseded' : ($earlier !== null ? 'PRJ.ProjectReconfirmed' : 'PRJ.ProjectConfirmed'),
            authorityClass: AuthorityClass::Adm,
            actorUserId: $actor,
            companyId: $companyId,
            entityType: 'project',
            entityId: $projectId,
            entityPublicId: self::text(DB::table('projects')->where('id', $projectId)->value('public_id')),
            reason: $reason,
            commandId: $commandId,
            change: AuditChange::updated(['confirmation' => $earlier], ['confirmation' => $confirmation, 'kind' => $kind, 'pins' => $pins]),
            businessDate: $businessDate,
        );

        return $confirmation;
    }

    /**
     * The pins of one confirmation: the approved revision's lines as the quotation priced them, or the project's lines
     * priced now with the output taxes in force; a line confirmed before and left out now pinned at zero.
     */
    private function pin(int $confirmation, int $projectId, string $kind, ?int $revisionId, string $commandId, CarbonImmutable $now, string $today): void
    {
        if ($kind === 'QUOTATION_APPROVAL') {
            // A re-planned line (L-21) has no line in the quotation, whose revisions copy the earlier one: a re-confirmation would
            // zero it and give its remainder back to its origin — refused; the re-plan stands until a superseding decision (CM-27).
            $split = DB::table('project_lines as l')->join('confirmation_line_pins as p', 'p.id', '=', 'l.current_pin_id')
                ->where('l.project_id', $projectId)->whereNotNull('l.split_from_line_id')->where('p.qty_base', '>', 0)
                ->whereNotExists(fn (Builder $lines) => $lines->from('quotation_revision_lines as q')->whereColumn('q.project_line_id', 'l.id')->where('q.quotation_revision_id', $revisionId))
                ->exists();

            if ($split) {
                throw Refused::rejected('L-21', ['reason' => 'split']);
            }

            DB::statement(<<<'SQL'
                insert into confirmation_line_pins (commercial_confirmation_id, company_id, project_line_id, product_id, quantity_scale, description,
                    entered_qty, entered_unit_id, ratio_to_base, qty_base, unit_price, sales_value, tax_components, command_id, command_line_no, recorded_at)
                select ?, q.company_id, q.project_line_id, q.product_id, q.quantity_scale, q.description, q.entered_qty, q.entered_unit_id, q.ratio_to_base,
                    q.qty_base, q.unit_price, q.sales_value, q.tax_components, ?, row_number() over (order by q.id), ?
                from quotation_revision_lines q where q.quotation_revision_id = ?
                SQL, [$confirmation, $commandId, $now, $revisionId]);
        } else {
            if (DB::table('project_lines')->where('project_id', $projectId)->whereNull('unit_price')->exists()) {
                // A confirmation pins a price for every line (BR-INV-03): a line without one is priced on PG-013 first.
                throw Refused::rejected('WF-PRJ-02', ['reason' => 'unpriced']);
            }

            $company = DB::table('projects')->where('id', $projectId)->value('company_id');
            $company = is_numeric($company) ? (int) $company : 0;
            DB::statement('with taxes as ('.QuotationRecords::TAXES.') '.<<<'SQL'
                insert into confirmation_line_pins (commercial_confirmation_id, company_id, project_line_id, product_id, quantity_scale, description,
                    entered_qty, entered_unit_id, ratio_to_base, qty_base, unit_price, sales_value, tax_components, command_id, command_line_no, recorded_at)
                select ?, l.company_id, l.id, l.product_id, l.quantity_scale, l.description, l.entered_qty, l.entered_unit_id, l.ratio_to_base, l.qty_base,
                    l.unit_price, round(l.entered_qty * l.unit_price, 2),
                    coalesce((select jsonb_agg(jsonb_build_object(
                        'type', taxes.code, 'rate', taxes.rate_percent::text, 'base', round(l.entered_qty * l.unit_price, 2)::text,
                        'amount', (case when taxes.rounding_mode = 'DOWN'
                            then trunc(round(l.entered_qty * l.unit_price, 2) * taxes.base_numerator / taxes.base_denominator * taxes.rate_percent / 100, 2)
                            else round(round(l.entered_qty * l.unit_price, 2) * taxes.base_numerator / taxes.base_denominator * taxes.rate_percent / 100, 2) end)::text,
                        'treatment', taxes.treatment) order by taxes.code) from taxes), '[]'::jsonb),
                    ?, row_number() over (order by l.line_no), ?
                from project_lines l where l.project_id = ?
                SQL, [$today, $today, $company, $confirmation, $commandId, $now, $projectId]);
        }

        // A confirmed line the new demand leaves out — a partial acceptance's revision (L-20) — keeps a pin at zero.
        DB::statement(<<<'SQL'
            insert into confirmation_line_pins (commercial_confirmation_id, company_id, project_line_id, product_id, quantity_scale, description,
                entered_qty, entered_unit_id, ratio_to_base, qty_base, unit_price, sales_value, tax_components, command_id, command_line_no, recorded_at)
            select ?, p.company_id, p.project_line_id, p.product_id, p.quantity_scale, p.description, 0, p.entered_unit_id, p.ratio_to_base, 0, p.unit_price, 0, '[]'::jsonb,
                ?, 1000 + row_number() over (order by l.line_no), ?
            from project_lines l join confirmation_line_pins p on p.id = l.current_pin_id
            where l.project_id = ? and not exists (select 1 from confirmation_line_pins n where n.commercial_confirmation_id = ? and n.project_line_id = l.id)
            SQL, [$confirmation, $commandId, $now, $projectId, $confirmation]);
    }

    /**
     * L-20, C-05 and C-31 before anything moves: no line's new demand below what is executed or cancelled on it, and
     * the confirmed value never below the invoiced (BR-XD-01; DATABASE §9) — the path a return or an invoice revision first.
     */
    private function guard(int $projectId): void
    {
        // The pins are read through the confirmation just written, the pointers not moved yet.
        $below = DB::selectOne(<<<'SQL'
            select l.line_no from project_lines l
            join project_line_balances b on b.project_line_id = l.id
            join confirmation_line_pins p on p.project_line_id = l.id
                and p.commercial_confirmation_id = (select max(c.id) from commercial_confirmations c where c.project_id = l.project_id)
            where l.project_id = ?
                and p.qty_base < b.dispatched_net_qty_base + b.dropship_confirmed_net_qty_base + b.handed_over_qty_base + b.cancelled_qty_base
            order by l.line_no limit 1
            SQL, [$projectId]);

        if (is_object($below)) {
            throw Refused::rejected('C-05', ['reason' => 'executed', 'line' => Row::text($below, 'line_no')]);
        }

        $value = DB::selectOne(<<<'SQL'
            select coalesce(sum(p.sales_value), 0) < coalesce((select invoiced_sales_value from project_commercial_balances where project_id = ?), 0) as below
            from confirmation_line_pins p where p.commercial_confirmation_id = (select max(c.id) from commercial_confirmations c where c.project_id = ?)
            SQL, [$projectId, $projectId]);

        if (is_object($value) && ($value->below ?? false) === true) {
            throw Refused::rejected('C-31', ['reason' => 'invoiced']);
        }
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
