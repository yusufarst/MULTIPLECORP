<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceEvidence;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\ReceivablePosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `FIN CorrectBilling` (ROUTE-244; INT-244; AX-17; CM-25) — "Koreksi Penagihan" with MSG-40's reason (IP-09): only with entry-error evidence linked to the
 * invoice's records, a superseding act in a linear chain under the current one — an inherited act's too (L-30) —, the superseded dates staying visible and ageing
 * following the corrected date; ADM_PLUS, so QS-14 lists the change (AZ-10). A completed project's predicate input moved, it is revalidated (M-26; SF-REVAL).
 */
final class CorrectBilling
{
    public const TYPE = 'FIN.CorrectBilling';

    public const CAPABILITY = 'billing.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InvoiceRecords $records,
        private readonly ReceivablePosting $receivable,
        private readonly Revalidation $revalidation,
    ) {}

    public function run(string $commandId, int $actorUserId, Invoice $invoice, ?int $evidenceId, string $billedAt, string $dueDate, bool $prefilled, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['invoice' => $invoice->public_id, 'evidence' => $evidenceId, 'billed_at' => $billedAt, 'due_date' => $dueDate, 'prefilled' => $prefilled, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $evidenceId, $billedAt, $dueDate, $prefilled, $reason): CommandResult {
            $context->lock(ReceivablePosting::anchors($invoice));

            if ($context->authority()?->allows('finance.view', $invoice->company_id) !== true) {
                throw new AuthorizationException;
            }

            ReceivablePosting::ensureIssued($invoice->id);
            BusinessDateRule::check($billedAt, $prefilled);
            $balance = ReceivablePosting::balance($invoice->id);
            $currentId = $balance === null ? null : Row::nullable($balance, 'current_billing_act_id');
            $current = $currentId === null ? null : DB::table('invoice_billing_acts')->where('id', (int) $currentId)->first(['billed_at', 'due_date']);

            if ($current === null) {
                throw Refused::rejected('CM-25', ['reason' => 'not_billed']);
            }

            if (! InvoiceEvidence::linked($invoice, $evidenceId)) {
                throw Refused::rejected('CM-25', ['reason' => 'evidence']);
            }

            $before = ['billed_at' => Row::text($current, 'billed_at'), 'due_date' => Row::nullable($current, 'due_date')];

            if ($before['billed_at'] === $billedAt && $before['due_date'] === $dueDate) {
                throw Refused::rejected('CM-25', ['reason' => 'unchanged']);
            }

            $act = DB::table('invoice_billing_acts')->insertGetId([
                'invoice_id' => $invoice->id, 'billed_at' => $billedAt, 'due_date' => $dueDate, 'kind' => 'CORRECTION', 'supersedes_act_id' => (int) $currentId, 'reason' => $reason, 'decided_by' => $context->request->actorUserId,
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
            ]);
            $this->receivable->act($context, $invoice->id, $act, $dueDate);
            $this->records->record($context, 'FIN.BillingCorrected', AuthorityClass::AdmPlus, $invoice->id, $reason, $billedAt, ['billed_at' => $billedAt, 'due_date' => $dueDate, 'evidence_id' => $evidenceId], $before);

            if ($invoice->project_id !== null) {
                $this->revalidation->revalidate($context, $invoice->project_id, "FIN.BillingCorrected:{$invoice->public_id}");
            }

            return new CommandResult('invoice', $invoice->id);
        });
    }
}
