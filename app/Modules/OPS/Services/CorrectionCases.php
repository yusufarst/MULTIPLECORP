<?php

namespace App\Modules\OPS\Services;

use App\Modules\OPS\Models\CorrectionCase;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The correction case of SF-CASE (DATABASE §4.13; BR-CR-04; C-46), the one service every correcting command uses for
 * it: a case opened only inside the command that records the correction, under that command's capability — there is
 * no case capability of its own — with its reason, its company, its project and the causal project and source lot the
 * cited facts name (XL-3); its guard counters written only by the posting services, in the transactions of the facts
 * they count, a CLOSED case whose figures change reopened in that same transaction and audited (L-06; L-46); and its
 * closure, refused while a residual stands — an unbacked refund, an unresolved deduction or a purchase return's
 * unaccounted cost (C-46). Its rows at LK-10 under LK-01's case project and causal project, of whichever company (NX-06).
 */
final class CorrectionCases
{
    public const KINDS = ['SALES_RETURN', 'DELIVERY_DISCREPANCY', 'DAMAGED_UNIT', 'PURCHASE_RETURN', 'DROPSHIP_RETURN', 'PAYMENT_CORRECTION', 'INVOICE_CORRECTION', 'OPENING_CORRECTION', 'OTHER'];

    /** The guard counters of DATABASE §3.1, each one posting service's (C-61). */
    public const COUNTERS = ['unbacked_refund_amount', 'residual_settlement_amount', 'returned_cost', 'refunded_value', 'replaced_value', 'unrecovered_value'];

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * NX-06's rows of a case: the case project and the causal project at LK-01 — of whichever company —, the case at
     * LK-10. A case not found locks nothing of its own.
     */
    public static function locks(int $caseId): LockSet
    {
        $case = DB::table('correction_cases')->where('id', $caseId)->first(['project_id', 'causal_project_id']);
        $set = (new LockSet)->add('correction_cases', ['id' => $caseId]);

        foreach (is_object($case) ? [$case->project_id, $case->causal_project_id] : [] as $project) {
            if (is_numeric($project)) {
                $set = $set->add('projects', ['id' => (int) $project]);
            }
        }

        return $set;
    }

    /** The projects a case opened now will name, at LK-01, before the opening command reads its clock. */
    public static function openingLocks(?int $projectId, ?int $causalProjectId): LockSet
    {
        $set = new LockSet;

        foreach ([$projectId, $causalProjectId] as $project) {
            if ($project !== null) {
                $set = $set->add('projects', ['id' => $project]);
            }
        }

        return $set;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Opens a case inside the correcting command that records it (SF-CASE; criterion 1), its reason required and the
     * causal project and source lot set from the facts the command cites (XL-3), audited under the command's authority.
     */
    public function open(CommandContext $context, AuthorityClass $authority, int $companyId, string $kind, string $reason, ?int $projectId = null, ?int $causalProjectId = null, ?int $sourceLotId = null): CorrectionCase
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("{$kind} is not a correction case kind.");
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A correction case opens with its reason (BR-CR-01).');
        }

        $context->require(self::openingLocks($projectId, $causalProjectId));
        $line = self::int(DB::table('correction_cases')->where('command_id', $context->request->commandId)->max('command_line_no')) + 1;
        $case = new CorrectionCase;
        $case->forceFill([
            'company_id' => $companyId, 'project_id' => $projectId, 'case_kind' => $kind, 'state' => 'OPEN', 'reason' => trim($reason),
            'causal_project_id' => $causalProjectId, 'source_lot_id' => $sourceLotId,
            'command_id' => $context->request->commandId, 'command_line_no' => $line,
            'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
        ])->save();

        $this->audit->record(
            action: 'OPS.CorrectionCaseOpened',
            authorityClass: $authority,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'correction_case',
            entityId: $case->id,
            entityPublicId: $case->public_id,
            reason: trim($reason),
            commandId: $context->request->commandId,
            change: AuditChange::created(['case_kind' => $kind, 'state' => 'OPEN']),
        );

        return $case;
    }

    /**
     * A posting service's counters of the facts it posts in this transaction, as decimal strings, under the case's
     * locks: a CLOSED case whose figures change reopens here, audited with the reason the posting gives, so the
     * completion predicate it feeds is false again (C-46; BR-CR-04; L-06; L-46).
     *
     * @param  array<string, string>  $deltas  counter => signed decimal string
     */
    public function post(CommandContext $context, int $caseId, array $deltas, string $reopenReason): void
    {
        $context->require(self::locks($caseId));
        $sets = [];
        $bindings = [];

        foreach ($deltas as $counter => $delta) {
            if (! in_array($counter, self::COUNTERS, true) || preg_match('/^-?\d+(\.\d{1,2})?$/', $delta) !== 1) {
                throw new InvalidArgumentException("{$counter} {$delta} is no case counter's delta (DATABASE §3.1).");
            }

            $sets[] = "{$counter} = {$counter} + ?::numeric";
            $bindings[] = $delta;
        }

        if ($sets === []) {
            return;
        }

        $before = DB::table('correction_cases')->where('id', $caseId)->first(['state', 'company_id', 'public_id', 'lock_version']);

        if (! is_object($before)) {
            throw new InvalidArgumentException("No correction case {$caseId}.");
        }

        $changed = array_filter($deltas, fn (string $delta): bool => preg_match('/^-?0+(\.0+)?$/', $delta) !== 1) !== [];
        $reopen = $changed && $before->state === 'CLOSED';
        $now = $context->now();

        if ($reopen) {
            $sets[] = "state = 'OPEN', reopened_by = ?, reopened_at = ?, reopen_reason = ?, reopen_command_id = ?";
            array_push($bindings, $context->request->actorUserId, $now, trim($reopenReason), $context->request->commandId);
        }

        $context->connection->update('update correction_cases set '.implode(', ', $sets).', lock_version = lock_version + 1, updated_at = ? where id = ?', [...$bindings, $now, $caseId]);

        if ($reopen) {
            $this->audit->record(
                action: 'OPS.CorrectionCaseReopened',
                authorityClass: AuthorityClass::System,
                actorUserId: $context->request->actorUserId,
                companyId: self::int($before->company_id ?? null),
                entityType: 'correction_case',
                entityId: $caseId,
                entityPublicId: is_string($before->public_id ?? null) ? $before->public_id : null,
                reason: trim($reopenReason),
                commandId: $context->request->commandId,
                change: AuditChange::updated(['state' => 'CLOSED'], ['state' => 'OPEN']),
                metadata: ['counters' => $deltas],
            );
        }
    }

    /**
     * The residuals that keep a case open (C-46), in the order the page names them: an unbacked refund, an unresolved
     * deduction, a purchase return's returned cost not accounted for by refunds, replacements and unrecovered loss.
     *
     * @return array<string, string> residual => its amount as a decimal string
     */
    public static function residuals(int $caseId): array
    {
        $row = DB::selectOne(<<<'SQL'
            select unbacked_refund_amount::text as refund, residual_settlement_amount::text as settlement,
                case when case_kind = 'PURCHASE_RETURN' then (returned_cost - refunded_value - replaced_value - unrecovered_value)::text else '0' end as unaccounted
            from correction_cases where id = ?
            SQL, [$caseId]);
        $residuals = [];

        foreach (['unbacked_refund' => 'refund', 'unresolved_deduction' => 'settlement', 'unaccounted_return' => 'unaccounted'] as $name => $column) {
            $amount = is_object($row) && is_string($row->{$column} ?? null) ? $row->{$column} : '0';

            if (preg_match('/^-?0+(\.0+)?$/', $amount) !== 1) {
                $residuals[$name] = $amount;
            }
        }

        return $residuals;
    }

    /**
     * Closes the case with its outcome (SF-CASE; BR-CR-04), REJECTED while a residual stands (C-46); the closure's
     * conditional update and the table's CHECKs are the backstop.
     */
    public function close(CommandContext $context, int $caseId, int $lockVersion, string $outcome): void
    {
        $context->require(self::locks($caseId));
        $case = DB::table('correction_cases')->where('id', $caseId)->first(['state', 'lock_version']);

        if (! is_object($case) || $case->state !== 'OPEN') {
            throw Refused::conflict('ST-02', ['state' => 'CLOSED']);
        }

        // A residual first: whatever version the page read, the case cannot close while one stands, and the answer names it.
        $residuals = self::residuals($caseId);

        if ($residuals !== []) {
            throw Refused::rejected('C-46', ['residuals' => $residuals]);
        }

        if (self::int($case->lock_version ?? null) !== $lockVersion) {
            throw Refused::conflict('ST-01');
        }

        $closed = DB::table('correction_cases')->where('id', $caseId)->where('state', 'OPEN')->where('lock_version', $lockVersion)->update([
            'state' => 'CLOSED', 'closure_outcome' => trim($outcome), 'closed_by' => $context->request->actorUserId, 'closed_at' => $context->now(),
            'lock_version' => $lockVersion + 1, 'updated_at' => $context->now(),
        ]);

        if ($closed !== 1) {
            throw Refused::conflict('ST-01');
        }
    }
}
