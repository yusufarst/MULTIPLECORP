<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Actions\IssueDocument;
use App\Modules\FIN\Data\InvoiceCorrection;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceCorrections;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\Outcome;

/**
 * `FIN VoidInvoice` (ROUTE-245; INT-245; AX-22; CM-23; CM-24) — "Batalkan Invoice" with MSG-40's reason (IP-09): its typed basis, a replacement
 * named in the same action where value is still owed (L-30), every reduction disposed. `invoice.correct` (ADM+) of the invoice's company; with a
 * standing write-off also `writeoff.supersede` (OD-07).
 */
final class VoidInvoice
{
    public const TYPE = 'FIN.VoidInvoice';

    public const CAPABILITY = 'invoice.correct';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InvoiceCorrections $corrections,
    ) {}

    /** @param  array<string, mixed>  $payload  the validated request, the command's fingerprint */
    public function run(string $commandId, int $actorUserId, Invoice $invoice, int $lockVersion, InvoiceCorrection $input, array $payload): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), [...$payload, 'invoice' => $invoice->public_id]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $lockVersion, $input): CommandResult {
            $context->lock($this->corrections->locks($invoice, $input));

            $done = $this->corrections->void($context, $invoice, $lockVersion, $input);

            foreach ($done['versions'] as $version) {
                IssueDocument::renderVersion($context, $version);
            }

            return new CommandResult('invoice', $done['invoice']);
        });
    }
}
