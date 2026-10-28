<?php

namespace App\Modules\PRJ\Services;

use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\BusinessDate;
use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * The quotation's revision chain (DATABASE §4.5 `quotation_revisions`, `quotation_revision_lines`; §9; WF-QUO-01;
 * SM:Quotation): a DRAFT revision written from the project's lines — or from the revision it revises —, its lines'
 * quantities, prices and descriptions edited while it is a DRAFT at the version the page read (ST-01; M-38), each line
 * carrying the tax components the company's configured treatment gives it on the day (DIR-024 D-1); the issue making it
 * SENT with its validity, its lines then frozen by trigger; the client's answer a final decision with its account and
 * time. EXPIRED is derived — SENT and past its validity at the WIB day boundary — never stored (L-25). A quotation is never
 * voided (BR-CR-06) and alone moves no stock or money (BR-QUO-03). Each write a business audit event (LG-01).
 */
final class QuotationRecords
{
    /** The output taxes in force on a day with their SALES_INVOICE treatment, the company's rule over the global one; bound: day, day, company. */
    private const TAXES = <<<'SQL'
        select t.code, r.rate_percent, r.base_numerator, r.base_denominator, r.rounding_mode, u.treatment
        from tax_types t
        join tax_rate_versions r on r.tax_type_id = t.id and r.effective @> ?::date
        join lateral (
            select treatment from tax_treatment_rules u
            where u.tax_type_id = t.id and u.transaction_context = 'SALES_INVOICE' and u.effective @> ?::date and (u.company_id = ? or u.company_id is null)
            order by u.company_id nulls last limit 1
        ) u on true
        where t.category = 'OUTPUT_TAX' and t.is_active
        SQL;

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * The project's latest revision, or null before its first.
     *
     * @return array{id: int, public_id: string, revision_no: int, state: string, valid_until: ?string, lock_version: int, supersedes: ?int}|null
     */
    public static function latest(int $projectId): ?array
    {
        $row = DB::table('quotation_revisions')->where('project_id', $projectId)->orderByDesc('revision_no')
            ->first(['id', 'public_id', 'revision_no', 'state', 'valid_until', 'lock_version', 'supersedes_revision_id']);

        return $row === null ? null : [
            'id' => (int) Row::text($row, 'id'),
            'public_id' => Row::text($row, 'public_id'),
            'revision_no' => (int) Row::text($row, 'revision_no'),
            'state' => Row::text($row, 'state'),
            'valid_until' => Row::nullable($row, 'valid_until'),
            'lock_version' => (int) Row::text($row, 'lock_version'),
            'supersedes' => Row::nullable($row, 'supersedes_revision_id') === null ? null : (int) Row::text($row, 'supersedes_revision_id'),
        ];
    }

    /** L-25: a SENT revision past its validity presents as *Kedaluwarsa* from the next WIB day. */
    public static function expired(string $state, ?string $validUntil, ?string $today = null): bool
    {
        return $state === 'SENT' && $validUntil !== null && $validUntil < ($today ?? BusinessDate::today());
    }

    /** The derived state a page shows: EXPIRED for a SENT revision past its validity, the stored state otherwise. */
    public static function shown(string $state, ?string $validUntil, ?string $today = null): string
    {
        return self::expired($state, $validUntil, $today) ? 'EXPIRED' : $state;
    }

    /**
     * A new DRAFT revision: the project's first from its lines, or — "Buat Revisi" — a revision of a non-draft one with
     * its lines (WF-QUO-01), the chain kept linear (`supersedes_revision_id`).
     *
     * @throws Refused ST-02 while a DRAFT revision exists, or the revised one is no longer the latest
     */
    public function draft(CommandContext $context, int $projectId, int $companyId, ?int $revises): int
    {
        $latest = self::latest($projectId);

        if ($latest !== null && ($latest['state'] === 'DRAFT' || $latest['id'] !== $revises)) {
            throw Refused::conflict('ST-02', ['state' => $latest['state']]);
        }

        $now = $context->now();
        $id = DB::table('quotation_revisions')->insertGetId([
            'project_id' => $projectId,
            'company_id' => $companyId,
            'revision_no' => ($latest['revision_no'] ?? 0) + 1,
            'state' => 'DRAFT',
            'valid_until' => $latest['valid_until'] ?? null,
            'supersedes_revision_id' => $revises,
            'command_id' => $context->request->commandId,
            'command_line_no' => 1,
            'recorded_at' => $now,
            'recorded_by' => $context->request->actorUserId,
        ]);
        $columns = 'company_id, project_line_id, product_id, quantity_scale, description, entered_qty, entered_unit_id, ratio_to_base, qty_base';

        if ($revises === null) {
            // The project's lines, in their order, each priced as the project states it (O-01).
            DB::statement("insert into quotation_revision_lines (quotation_revision_id, {$columns}, unit_price, sales_value, tax_components, command_id, command_line_no, recorded_at, recorded_by)
                select ?, l.company_id, l.id, l.product_id, l.quantity_scale, l.description, l.entered_qty, l.entered_unit_id, l.ratio_to_base, l.qty_base,
                    coalesce(l.unit_price, 0), round(l.entered_qty * coalesce(l.unit_price, 0), 2), '[]'::jsonb, ?, row_number() over (order by l.line_no), ?, ?
                from project_lines l where l.project_id = ?", [$id, $context->request->commandId, $now, $context->request->actorUserId, $projectId]);
        } else {
            DB::statement("insert into quotation_revision_lines (quotation_revision_id, {$columns}, unit_price, sales_value, tax_components, command_id, command_line_no, recorded_at, recorded_by)
                select ?, {$columns}, unit_price, sales_value, '[]'::jsonb, ?, row_number() over (order by id), ?, ?
                from quotation_revision_lines where quotation_revision_id = ?", [$id, $context->request->commandId, $now, $context->request->actorUserId, $revises]);
        }

        $this->taxes($id, $companyId);
        $this->record($context, $revises === null ? 'PRJ.QuotationDrafted' : 'PRJ.QuotationRevised', $id, $companyId, AuditChange::created(['revision_no' => ($latest['revision_no'] ?? 0) + 1, 'revises' => $latest['public_id'] ?? null]));

        return $id;
    }

    /**
     * The DRAFT's validity and lines saved at the version the page read (ST-01): a line's quantity, price and
     * description changed, a line left out removed — the accepted subset of a partial acceptance (L-20) —, its tax
     * components recomputed.
     *
     * @param  array<int, array{quantity: string, quantity_base: string, price: ?string, description: string}>  $lines  by line id, the kept lines
     *
     * @throws Refused ST-01 for a stale version, ST-02 for a revision no longer a DRAFT
     */
    public function save(CommandContext $context, int $revisionId, int $companyId, int $lockVersion, ?string $validUntil, array $lines, bool $money): void
    {
        $revision = DB::table('quotation_revisions')->where('id', $revisionId)->first(['state', 'lock_version', 'valid_until']);

        if ($revision === null || Row::text($revision, 'state') !== 'DRAFT') {
            throw Refused::conflict('ST-02', ['state' => $revision === null ? 'DELETED' : Row::text($revision, 'state')]);
        }

        if ((int) Row::text($revision, 'lock_version') !== $lockVersion) {
            throw Refused::conflict('ST-01');
        }

        if ($lines === []) {
            throw Refused::rejected('WF-QUO-01', ['reason' => 'no_lines']);
        }

        $before = self::content($revisionId);
        DB::table('quotation_revision_lines')->where('quotation_revision_id', $revisionId)->whereNotIn('id', array_keys($lines))->delete();

        foreach ($lines as $id => $line) {
            DB::table('quotation_revision_lines')->where('id', $id)->where('quotation_revision_id', $revisionId)->update([
                'entered_qty' => $line['quantity'],
                'qty_base' => $line['quantity_base'],
                'description' => $line['description'],
                ...($money && $line['price'] !== null ? ['unit_price' => $line['price']] : []),
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => $context->now(),
            ]);
        }

        DB::statement('update quotation_revision_lines set sales_value = round(entered_qty * unit_price, 2) where quotation_revision_id = ?', [$revisionId]);
        $this->taxes($revisionId, $companyId);
        $changed = DB::table('quotation_revisions')->where('id', $revisionId)->where('state', 'DRAFT')->where('lock_version', $lockVersion)
            ->update(['valid_until' => $validUntil, 'lock_version' => $lockVersion + 1, 'updated_at' => $context->now()]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-01');
        }

        $change = AuditChange::updated([...$before, 'valid_until' => Row::nullable($revision, 'valid_until')], [...self::content($revisionId), 'valid_until' => $validUntil]);

        if (! $change->isEmpty()) {
            $this->record($context, 'PRJ.QuotationDraftSaved', $revisionId, $companyId, $change);
        }
    }

    /**
     * The issued DRAFT made SENT with its validity, in the issue's transaction (AX-15; SM:Quotation) — its lines frozen
     * from then on by trigger.
     *
     * @throws Refused ST-02 when the revision is no longer a DRAFT
     */
    public function sent(CommandContext $context, int $revisionId, int $companyId, string $validUntil, string $number): void
    {
        $changed = DB::table('quotation_revisions')->where('id', $revisionId)->where('state', 'DRAFT')
            ->update(['state' => 'SENT', 'valid_until' => $validUntil, 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => $context->now()]);

        if ($changed !== 1) {
            throw Refused::conflict('ST-02', ['state' => 'SENT']);
        }

        $this->record($context, 'PRJ.QuotationSent', $revisionId, $companyId, AuditChange::updated(['state' => 'DRAFT'], ['state' => 'SENT', 'valid_until' => $validUntil, 'number' => $number]));
    }

    /**
     * The client's answer on a SENT revision, final (SM:Quotation; BR-QUO-01): its account, time and reason.
     *
     * @throws Refused BD-05 when the revision was answered meanwhile
     */
    public function decide(CommandContext $context, int $revisionId, int $companyId, string $state, ?string $reason): void
    {
        $changed = DB::table('quotation_revisions')->where('id', $revisionId)->where('state', 'SENT')->update([
            'state' => $state,
            'decided_by' => $context->request->actorUserId,
            'decided_at' => $context->now(),
            'decision_reason' => $reason,
            'lock_version' => DB::raw('lock_version + 1'),
            'updated_at' => $context->now(),
        ]);

        if ($changed !== 1) {
            throw Refused::conflict('BD-05');
        }

        $this->record($context, $state === 'APPROVED' ? 'PRJ.QuotationApproved' : 'PRJ.QuotationRejected', $revisionId, $companyId,
            AuditChange::updated(['state' => 'SENT'], ['state' => $state]), $reason);
    }

    /**
     * The output taxes a quotation line carries today, as {@see self::taxes()} applies them — for the composer's live
     * preview, which computes each line's components as the save will (INT-012).
     *
     * @return list<array{type: string, rate: string, numerator: string, denominator: string, rounding: string}>
     */
    public static function rules(int $companyId): array
    {
        $today = BusinessDate::today();
        $rules = [];

        foreach (DB::select(self::TAXES.' order by t.code', [$today, $today, $companyId]) as $rule) {
            if (is_object($rule)) {
                $rules[] = ['type' => Row::text($rule, 'code'), 'rate' => Row::text($rule, 'rate_percent'), 'numerator' => Row::text($rule, 'base_numerator'),
                    'denominator' => Row::text($rule, 'base_denominator'), 'rounding' => Row::text($rule, 'rounding_mode')];
            }
        }

        return $rules;
    }

    /**
     * Each line's tax components under the company's treatment on the day (DIR-024 D-1; BR-FIN-17): every active output
     * tax with a rate in force and a SALES_INVOICE treatment — the company's rule over the global one —, its amount on the
     * line's value at the rate's base and rounding. None configured, none written (FS-13).
     */
    private function taxes(int $revisionId, int $companyId): void
    {
        $today = BusinessDate::today();
        DB::statement('with taxes as ('.self::TAXES.') '.<<<'SQL'
            update quotation_revision_lines q set tax_components = coalesce((
                select jsonb_agg(jsonb_build_object(
                    'type', taxes.code,
                    'rate', taxes.rate_percent::text,
                    'base', q.sales_value::text,
                    'amount', (case when taxes.rounding_mode = 'DOWN'
                        then trunc(q.sales_value * taxes.base_numerator / taxes.base_denominator * taxes.rate_percent / 100, 2)
                        else round(q.sales_value * taxes.base_numerator / taxes.base_denominator * taxes.rate_percent / 100, 2) end)::text,
                    'treatment', taxes.treatment) order by taxes.code)
                from taxes), '[]'::jsonb)
            where q.quotation_revision_id = ?
            SQL, [$today, $today, $companyId, $revisionId]);
    }

    /** @return array{lines: list<array<string, ?string>>} */
    private static function content(int $revisionId): array
    {
        $lines = DB::table('quotation_revision_lines')->where('quotation_revision_id', $revisionId)->orderBy('id')
            ->get(['id', 'description', 'entered_qty', 'unit_price', 'sales_value'])->all();

        return ['lines' => array_values(array_map(fn (object $line): array => [
            'id' => Row::text($line, 'id'),
            'description' => Row::text($line, 'description'),
            'quantity' => Row::text($line, 'entered_qty'),
            'price' => Row::text($line, 'unit_price'),
            'value' => Row::text($line, 'sales_value'),
        ], $lines))];
    }

    private static function publicId(int $revisionId): ?string
    {
        $id = DB::table('quotation_revisions')->where('id', $revisionId)->value('public_id');

        return is_string($id) ? $id : null;
    }

    private function record(CommandContext $context, string $action, int $revisionId, int $companyId, AuditChange $change, ?string $reason = null): void
    {
        $this->audit->record(
            action: $action,
            authorityClass: AuthorityClass::Adm,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'quotation_revision',
            entityId: $revisionId,
            entityPublicId: self::publicId($revisionId),
            reason: $reason,
            commandId: $context->request->commandId,
            change: $change,
        );
    }
}
