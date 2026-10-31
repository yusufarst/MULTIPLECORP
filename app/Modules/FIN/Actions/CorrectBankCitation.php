<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Services\EvidenceGuard;
use App\Modules\FIN\Models\Payment;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\PaymentCorrections;
use App\Modules\FIN\Services\PaymentRecords;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN CorrectBankCitation` (ROUTE-262; INT-262; NX-08; C-19; DATABASE §11.3) — a wrong statement-line citation corrected by a reasoned change that moves both lines' guards in one transaction, never by a contra of the
 * payment: the new line ACTIVE on the payment's account with room for its net money; a payment flagged until now takes its first citation the same way. Never back to none. `cash.correct` (ADM+).
 */
final class CorrectBankCitation
{
    public const TYPE = 'FIN.CorrectBankCitation';

    public const CAPABILITY = 'cash.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly EvidenceGuard $evidence,
        private readonly PaymentRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, Payment $payment, int $lineId, string $reason): Outcome
    {
        $handle = RowHandle::of('bank_statement_lines', $lineId);
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $payment->company_id, self::CAPABILITY, Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($payment->company_id), ['payment' => $payment->public_id, 'line' => $handle, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($payment, $lineId, $reason, $handle): CommandResult {
            $context->lock(self::locks($payment, $lineId));
            $fresh = $payment->fresh() ?? $payment;
            $context->require(self::locks($fresh, $lineId));
            $old = $fresh->bank_statement_line_id;

            if ($old === $lineId) {
                throw Refused::rejected('C-19', ['reason' => 'same_line']);
            }

            $source = MoneyPosting::source(['payment_id' => $fresh->id]);
            $net = Money::of($source === null ? '0' : Row::text($source, 'net_amount'));
            $line = DB::table('bank_statement_lines')->where('id', $lineId)->where('company_id', $fresh->company_id)->first(['state', 'company_bank_account_id', 'amount']);

            if ($line === null || Row::text($line, 'state') !== 'ACTIVE' || (int) Row::text($line, 'company_bank_account_id') !== $fresh->company_bank_account_id) {
                throw Refused::rejected('C-19', ['reason' => 'line']);
            }

            $uncited = Money::of(Row::text($line, 'amount'))->minus(Money::of($this->evidence->lineCited($lineId)));

            if ($net->compareTo($uncited) > 0) {
                throw Refused::rejected('C-19', ['reason' => 'above_line', 'uncited' => $uncited->toString(), 'amount' => $net->toString()]);
            }

            $now = $context->now();
            $moved = DB::table('payments')->where('id', $fresh->id)->update($old === null
                ? ['bank_statement_line_id' => $lineId]
                : ['bank_statement_line_id' => $lineId, 'bank_citation_corrected_at' => $now, 'bank_citation_corrected_by' => $context->request->actorUserId, 'bank_citation_corrected_reason' => $reason]);

            if ($moved !== 1) {
                throw Refused::conflict('ST-02', ['state' => 'CITED']);
            }

            $this->evidence->moveLines($context, $old === null ? [$lineId => ['cited_amount' => $net->toString()]] : [$old => ['cited_amount' => $net->negated()->toString()], $lineId => ['cited_amount' => $net->toString()]]);
            $this->records->record($context, 'FIN.BankCitationCorrected', AuthorityClass::AdmPlus, $fresh->id, $reason, null, ['line' => $handle], ['line' => $old === null ? 'NONE' : RowHandle::of('bank_statement_lines', $old)]);

            return new CommandResult('payment', $fresh->id);
        });
    }

    /** NX-08's set: both lines' guards at LK-29, the payment at LK-26 and, data-dependent, its applications' projects at LK-01. */
    private static function locks(Payment $payment, int $lineId): LockSet
    {
        $invoices = [];

        foreach (PaymentCorrections::activeApplications($payment->id) as $row) {
            if ($row['invoice'] !== null) {
                $invoices[] = $row['invoice'];
            }
        }

        $projects = new LockSet;

        foreach (DB::table('invoices')->whereIn('id', $invoices)->whereNotNull('project_id')->pluck('project_id') as $project) {
            $projects = is_numeric($project) ? $projects->add('projects', ['id' => (int) $project]) : $projects;
        }

        $set = $projects->union(EvidenceGuard::lineLocks($lineId))->add('payments', ['id' => $payment->id])->add('application_source_balances', ['payment_id' => $payment->id]);

        return $payment->bank_statement_line_id === null ? $set : $set->union(EvidenceGuard::lineLocks($payment->bank_statement_line_id));
    }
}
