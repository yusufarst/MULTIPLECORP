<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Data\EvidenceTypes;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
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

    /** Cites `$amount` of the evidence in the role — a negative amount releases a citation. */
    public function cite(CommandContext $context, int $evidenceId, string $role, string $amount): void
    {
        $context->require(self::locks($evidenceId));
        $type = DB::table('evidence_documents')->where('id', $evidenceId)->value('evidence_type');

        if (! is_string($type) || ! in_array($type, EvidenceTypes::CITING_ROLES[$role] ?? [], true)) {
            throw Refused::rejected('C-58', ['role' => $role]);
        }

        $statement = in_array($role, self::EXPENSED, true)
            ? 'update evidence_balances set expensed_amount = expensed_amount + ?::numeric where evidence_document_id = ?'
            : 'update evidence_balances set cited_amount = cited_amount + ?::numeric where evidence_document_id = ?';
        DB::update($statement, [$amount, $evidenceId]);
    }
}
