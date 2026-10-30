<?php

namespace App\Modules\FIN\Actions;

use App\Modules\DOC\Actions\IssueDocument;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Services\DocumentChain;
use App\Modules\DOC\Services\DocumentIssuing;
use App\Modules\DOC\Services\DocumentSubjects;
use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\InvoiceSubjects;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use App\Support\Search\Row;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * `FIN IssueInvoice` (ROUTE-242; INT-242; AX-16; WF-FIN-01; J-FIN-01) — "Terbitkan Invoice", or its layout *Nota*, after its confirmation
 * (IP-08; MSG-39): in one transaction the version and root ISSUED within the project's cap (C-31; L-16), the guard initialized with no
 * billing act, the invoiced value raised and the layout issued with the number of its company, type and business-date period, drawn last
 * (NM-03; C-10). It records the agreed value: *Belum Ditagihkan*, no Cash-In, no active receivable, no stock (BR-FIN-02; BR-XD-04). A second
 * issue of the draft is CONFLICT with the issued number (BD-06). `invoice.issue` with `finance.view` of the invoice's company.
 */
final class IssueInvoice
{
    public const TYPE = 'FIN.IssueInvoice';

    public const CAPABILITY = 'invoice.issue';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly DocumentSubjects $subjects,
        private readonly DocumentIssuing $issuing,
        private readonly DocumentChain $chain,
        private readonly InvoiceRecords $records,
    ) {}

    public function run(string $commandId, int $actorUserId, ProjectRef $project, Invoice $invoice, int $lockVersion, string $layout, string $businessDate, bool $prefilled, ?string $factsDigest = null): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['invoice' => $invoice->public_id, 'lock_version' => $lockVersion, 'layout' => $layout, 'business_date' => $businessDate, 'prefilled' => $prefilled, 'facts' => $factsDigest]));

        return $this->envelope->run($request, function (CommandContext $context) use ($project, $invoice, $lockVersion, $layout, $businessDate, $prefilled, $factsDigest): CommandResult {
            $subject = new DocumentSubject($layout, 'STANDARD', $invoice->company_id, ['invoice_id' => $invoice->id]);
            $provider = $this->subjects->of($subject);
            // AX-16's set: the project (LK-01), the company (LK-06), the cap (LK-20), the invoice (LK-25), a chain an earlier press left (LK-30; else first used here, FU-12) and the counter (LK-31).
            $chain = $this->issuing->chainOf($subject);
            $context->lock((new LockSet)->add('projects', ['id' => $project->id])->add('companies', ['id' => $invoice->company_id])
                ->add('project_commercial_balances', ['project_id' => $project->id])->add('invoices', ['id' => $invoice->id])
                ->union($chain === null ? new LockSet : $this->chain->locks($chain, array_values(array_filter([$this->chain->draftOf($chain->id)?->id]))))
                ->union($this->issuing->numberLocks($invoice->company_id, $layout, $businessDate)));

            if ($context->authority()?->allows('finance.view', $invoice->company_id) !== true) {
                throw new AuthorizationException;
            }

            $fresh = DB::table('invoices')->where('id', $invoice->id)->first(['state', 'lock_version', 'current_version_id']);

            if ($fresh === null || $fresh->state === 'VOIDED') {
                throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
            }

            if ($fresh->state !== 'DRAFT') {
                throw Refused::conflict('BD-06', ['number' => InvoiceSubjects::number($invoice->id) ?? '']);
            }

            if ((int) Row::text($fresh, 'lock_version') !== $lockVersion) {
                throw Refused::conflict('ST-01');
            }

            // The invoice moved before the layout's precondition reads it.
            $document = $this->issuing->openDraft($context, $subject, $chain);
            $values = $this->records->markIssued($context, $project, $invoice->id, (int) Row::text($fresh, 'current_version_id'), $lockVersion, $businessDate, $prefilled);
            $version = $this->issuing->issue($context, $document, $subject, $provider, null, $businessDate, $prefilled, null, $factsDigest, authorized: true, held: true);
            IssueDocument::render($context, $version);
            $this->records->record($context, 'FIN.InvoiceIssued', AuthorityClass::Adm, $invoice->id, null, $businessDate,
                ['state' => 'ISSUED', 'number' => $version->formatted_number, 'layout' => $layout, ...$values], ['state' => 'DRAFT']);

            return new CommandResult('invoice', $invoice->id);
        }, null, fn (Outcome $outcome): ?array => $outcome->code === 'BD-06' ? ['number' => InvoiceSubjects::number($invoice->id) ?? ''] : null);
    }
}
