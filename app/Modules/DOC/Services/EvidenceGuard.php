<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Models\EvidenceBalance;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The evidence guard of C-18 and C-58 (DATABASE §4.10; architecture challenge 6d), for the commands that cite
 * evidence by an amount — settlements, expenses, charges and remittances (TASK-036, TASK-051, TASK-054): the citing
 * role accepts only its types, so a document registered again under another type gains no capacity in any role, and
 * the amount moves the evidence's guard at LK-29, whose CHECK keeps every citation within the proven amount. The
 * caller holds the guard through `locks()` in its own lock set.
 */
final class EvidenceGuard
{
    /** The roles whose amount counts as expensed rather than cited (C-23). */
    private const EXPENSED = ['FEE_DEDUCTION_EXPENSE', 'TAX_TREATMENT_EXPENSE'];

    public static function locks(int $evidenceId): LockSet
    {
        return (new LockSet)->add('evidence_documents', ['id' => $evidenceId])->add('evidence_balances', ['evidence_document_id' => $evidenceId]);
    }

    /**
     * What of the evidence's proven amount no citation holds yet, read under its guard's lock: the cap a citing role
     * checks before it cites, to refuse with its own rule (C-18; C-20 for a charge's source bill).
     */
    public function uncited(CommandContext $context, int $evidenceId): string
    {
        $context->require(self::locks($evidenceId));
        $balance = DB::selectOne('select proven_amount - cited_amount as uncited from evidence_balances where evidence_document_id = ?', [$evidenceId]);

        return is_object($balance) ? Row::text($balance, 'uncited') : '0.00';
    }

    /**
     * Whether any of these is, by its bytes, a rendition the company issued itself: never proof that money was received (L-29; FL-05).
     *
     * @param  list<int>  $evidenceIds
     */
    public static function companyIssued(array $evidenceIds): bool
    {
        return DB::table('evidence_documents as e')->whereIn('e.id', $evidenceIds)
            ->join('evidence_document_versions as v', 'v.id', '=', 'e.current_version_id')->join('file_objects as f', 'f.id', '=', 'v.file_object_id')
            ->whereExists(fn (Builder $rendition) => $rendition->from('file_objects as r')->whereColumn('r.sha256', 'f.sha256')->where('r.purpose', 'RENDITION'))
            ->exists();
    }

    /** A bank statement line and its guard at LK-29, BD-03's anchor (C-19). */
    public static function lineLocks(int $lineId): LockSet
    {
        return (new LockSet)->add('bank_statement_lines', ['id' => $lineId])->add('evidence_balances', ['bank_statement_line_id' => $lineId]);
    }

    /** A line's guard born with it, proving its amount (L-22; DATABASE §4.10). */
    public function bearLine(CommandContext $context, int $lineId, string $amount): void
    {
        GuardUpdate::bear($context->connection, 'evidence_balances', ['bank_statement_line_id' => $lineId], ['proven_amount' => $amount, 'recorded_at' => $context->now()]);
    }

    /** What of a line its Cash-In facts cite now, net of their contras. */
    public function lineCited(int $lineId): string
    {
        $cited = EvidenceBalance::query()->toBase()->where('bank_statement_line_id', $lineId)->value('cited_amount');

        return is_scalar($cited) ? (string) $cited : '0.00';
    }

    /**
     * Lines' guards moved in one statement, its CHECK keeping Σ Cash-In per line within the line (BD-03; C-19).
     *
     * @param  array<int, array<string, string>>  $moves  line => column => signed decimal, each row the same columns
     */
    public function moveLines(CommandContext $context, array $moves): void
    {
        $rows = [];

        foreach ($moves as $line => $deltas) {
            $rows[] = ['key' => ['bank_statement_line_id' => $line], 'deltas' => $deltas];
        }

        if ($rows !== []) {
            GuardUpdate::apply($context->connection, 'evidence_balances', $rows);
        }
    }

    /** Cites `$amount` of the evidence in the role — a negative amount releases a citation. */
    public function cite(CommandContext $context, int $evidenceId, string $role, string $amount): void
    {
        $context->require(self::locks($evidenceId));
        $type = DB::table('evidence_documents')->where('id', $evidenceId)->value('evidence_type');

        if (! is_string($type) || ! in_array($type, EvidenceTypes::CITING_ROLES[$role] ?? [], true)) {
            throw Refused::rejected('C-58', ['role' => $role]);
        }

        GuardUpdate::apply($context->connection, 'evidence_balances', [[
            'key' => ['evidence_document_id' => $evidenceId],
            'deltas' => [in_array($role, self::EXPENSED, true) ? 'expensed_amount' : 'cited_amount' => $amount],
        ]]);
    }
}
