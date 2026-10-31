<?php

namespace App\Modules\DOC\Actions;

use App\Modules\DOC\Contracts\BankLineCitations;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;

/**
 * `DOC RecordBankStatementLine` (ROUTE-261; INT-261; NX-08; L-45; J-FIN-07) — "Catat Mutasi Bank" with its BANK_STATEMENT evidence: account, date, amount and the
 * bank's own line reference are its identity, so one receipt in two overlapping statements is one line (C-19; BD-03) and a line recorded again a CONFLICT; its guard
 * born proving the amount (C-18). A payment recorded before its line arrived is flagged until cited: the flagged facts named here take their citation in this command
 * (DATABASE §11.3; QS-12). `bank_statement.record` of the company, with `finance.view` and `evidence.upload` by its catalogue.
 */
final class RecordBankStatementLine
{
    public const TYPE = 'DOC.RecordBankStatementLine';

    public const CAPABILITY = 'bank_statement.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly EvidenceGuard $guard,
        private readonly BankLineCitations $citations,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  list<int>  $citing  the flagged facts the line's arrival cites
     * @param  array<string, mixed>  $payload  the validated request, the command's fingerprint
     */
    public function run(string $commandId, int $actorUserId, int $companyId, int $accountId, int $evidenceId, string $businessDate, bool $prefilled, string $amount, string $reference, array $citing, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, DocumentChain::companyKey($companyId), $payload));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $accountId, $evidenceId, $businessDate, $prefilled, $amount, $reference, $citing): CommandResult {
            $context->lock((new LockSet)->add('evidence_documents', ['id' => $evidenceId])->union($this->citations->locks($citing)));
            BusinessDateRule::check($businessDate, $prefilled);

            if (DB::table('company_bank_accounts')->where('id', $accountId)->where('company_id', $companyId)->where('is_active', true)->doesntExist()) {
                throw Refused::rejected('C-32', ['reason' => 'account']);
            }

            // BD-03: the partial UNIQUE key is the race's backstop; here the answer names what happened.
            $identity = DB::table('bank_statement_lines')->where('company_bank_account_id', $accountId)->where('business_date', $businessDate)->where('amount', $amount)->where('line_reference', $reference)->where('state', 'ACTIVE')->exists();

            if ($identity) {
                throw Refused::conflict('C-19', ['reason' => 'identity']);
            }

            // A cited line stands as proof of receipt: never on a document the company issued itself (L-29; FL-05).
            if (EvidenceGuard::companyIssued([$evidenceId])) {
                throw Refused::rejected('L-29', ['reason' => 'company_issued']);
            }

            $now = $context->now();
            $id = DB::table('bank_statement_lines')->insertGetId([
                'company_id' => $companyId, 'company_bank_account_id' => $accountId, 'statement_evidence_id' => $evidenceId, 'statement_evidence_type' => 'BANK_STATEMENT', 'business_date' => $businessDate,
                'amount' => $amount, 'line_reference' => $reference, 'state' => 'ACTIVE', 'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
            ]);
            $this->guard->bearLine($context, $id, $amount);
            $this->citations->arrived($context, $id, $accountId, $amount, $citing);
            $this->audit->record(
                action: 'DOC.BankLineRecorded',
                authorityClass: AuthorityClass::Adm,
                actorUserId: $context->request->actorUserId,
                companyId: $companyId,
                entityType: 'bank_statement_line',
                entityId: $id,
                entityPublicId: RowHandle::of('bank_statement_lines', $id),
                reason: null,
                commandId: $context->request->commandId,
                change: AuditChange::created(['amount' => $amount, 'line_reference' => $reference, 'cited' => count($citing)]),
                businessDate: $businessDate,
            );

            return new CommandResult('bank_statement_line', $id);
        });
    }
}
