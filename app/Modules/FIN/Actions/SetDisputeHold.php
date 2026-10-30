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
 * `FIN SetDisputeHold` (ROUTE-246; INT-246; NX-09; WF-FIN-05; J-FIN-05) — "Tandai Sengketa" with MSG-40's reason (IP-09): an indicator on a billed receivable with
 * something outstanding; it stays active, keeps ageing from its due date, settles nothing and blocks normal completion (BR-FIN-08; L-12). One uncleared hold per
 * invoice (C-42). A completed project's predicate input moved, it is revalidated (M-26; SF-REVAL). `receivables.dispute` with `finance.view`.
 */
final class SetDisputeHold
{
    public const TYPE = 'FIN.SetDisputeHold';

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

            ReceivablePosting::ensureReceivable($invoice->id);

            if (DB::table('receivable_dispute_holds')->where('invoice_id', $invoice->id)->whereNull('cleared_at')->exists()) {
                throw Refused::rejected('C-42', ['reason' => 'hold_open']);
            }

            $now = $context->now();
            DB::table('receivable_dispute_holds')->insert([
                'invoice_id' => $invoice->id, 'reason' => $reason, 'set_by' => $context->request->actorUserId, 'set_at' => $now,
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
            ]);
            $this->records->record($context, 'FIN.DisputeHoldSet', AuthorityClass::Adm, $invoice->id, $reason, null, ['dispute_hold' => true], ['dispute_hold' => false]);

            if ($invoice->project_id !== null) {
                $this->revalidation->revalidate($context, $invoice->project_id, "FIN.DisputeHoldSet:{$invoice->public_id}");
            }

            return new CommandResult('invoice', $invoice->id);
        });
    }
}
