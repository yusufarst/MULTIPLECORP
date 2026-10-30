<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\ReceivablePosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Services\Revalidation;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `FIN ClearDisputeHold` (ROUTE-246; INT-246; NX-09; WF-FIN-05) — "Cabut Sengketa" with MSG-40's reason (IP-09): the uncleared hold ended once, its reason and actor
 * kept, the receivable as it was (BR-FIN-08; CM-27). A completed project's predicate input moved, it is revalidated (M-26; SF-REVAL). `receivables.dispute` with `finance.view`.
 */
final class ClearDisputeHold
{
    public const TYPE = 'FIN.ClearDisputeHold';

    public const CAPABILITY = 'receivables.dispute';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InvoiceRecords $records,
        private readonly Revalidation $revalidation,
    ) {}

    public function run(string $commandId, int $actorUserId, Invoice $invoice, string $reason): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['invoice' => $invoice->public_id, 'reason' => $reason]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $reason): CommandResult {
            $context->lock(ReceivablePosting::anchors($invoice));

            if ($context->authority()?->allows('finance.view', $invoice->company_id) !== true) {
                throw new AuthorizationException;
            }

            $cleared = DB::table('receivable_dispute_holds')->where('invoice_id', $invoice->id)->whereNull('cleared_at')
                ->update(['cleared_by' => $context->request->actorUserId, 'cleared_at' => $context->now(), 'clear_reason' => $reason]);

            if ($cleared !== 1) {
                throw Refused::rejected('C-42', ['reason' => 'no_hold']);
            }

            $this->records->record($context, 'FIN.DisputeHoldCleared', AuthorityClass::Adm, $invoice->id, $reason, null, ['dispute_hold' => false], ['dispute_hold' => true]);

            if ($invoice->project_id !== null) {
                $this->revalidation->revalidate($context, $invoice->project_id, "FIN.DisputeHoldCleared:{$invoice->public_id}");
            }

            return new CommandResult('invoice', $invoice->id);
        });
    }
}
