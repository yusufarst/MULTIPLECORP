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
 * `FIN ReviseInvoiceDown` (ROUTE-245; INT-245; AX-22; CM-12; CM-23; CM-24) — "Revisi Turun" with MSG-40's reason (IP-09): a new version of the
 * record with its typed basis, never below what stays applied (C-16), every reduction disposed. A return's correction is this explicit command
 * citing its case; nothing changes when the goods come back until it runs (BR-XD-01). `invoice.correct` (ADM+); with a standing write-off also
 * `writeoff.supersede` (OD-07).
 */
final class ReviseInvoiceDown
{
    public const TYPE = 'FIN.ReviseInvoiceDown';

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

            $done = $this->corrections->revise($context, $invoice, $lockVersion, $input);

            foreach ($done['versions'] as $version) {
                IssueDocument::renderVersion($context, $version);
            }

            return new CommandResult('invoice', $done['invoice']);
        });
    }
}
