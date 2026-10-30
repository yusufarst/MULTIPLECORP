<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\ReceivablePosting;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
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
 * `FIN BillInvoice` (ROUTE-243; INT-243; AX-17; WF-FIN-02; J-FIN-02) — "Tagihkan" after MSG-39 (IP-08): the billing date and the entered due date, no
 * payment-terms engine, one root act per invoice, a second one a CONFLICT with the dates it holds (BD-07; C-41). It records no amount (DATABASE §11.2): the
 * unpaid remainder the guard already carries becomes active, advances already applied reducing what billing activates, so no Cash-In is counted again
 * (BR-FIN-04; annex 2). `billing.record` with `finance.view` of the invoice's company.
 */
final class BillInvoice
{
    public const TYPE = 'FIN.BillInvoice';

    public const CAPABILITY = 'billing.record';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly InvoiceRecords $records,
        private readonly ReceivablePosting $receivable,
    ) {}

    public function run(string $commandId, int $actorUserId, Invoice $invoice, string $billedAt, string $dueDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $invoice->company_id, self::CAPABILITY,
            Fingerprint::of(self::TYPE, SaveQuotationDraft::companyKey($invoice->company_id), ['invoice' => $invoice->public_id, 'billed_at' => $billedAt, 'due_date' => $dueDate, 'prefilled' => $prefilled]));

        return $this->envelope->run($request, function (CommandContext $context) use ($invoice, $billedAt, $dueDate, $prefilled): CommandResult {
            $context->lock(ReceivablePosting::anchors($invoice));

            if ($context->authority()?->allows('finance.view', $invoice->company_id) !== true) {
                throw new AuthorizationException;
            }

            ReceivablePosting::ensureIssued($invoice->id);
            BusinessDateRule::check($billedAt, $prefilled);
            $root = self::root($invoice->id);

            if ($root !== null) {
                throw Refused::conflict('BD-07', $root);
            }

            $act = DB::table('invoice_billing_acts')->insertGetId([
                'invoice_id' => $invoice->id, 'billed_at' => $billedAt, 'due_date' => $dueDate, 'kind' => 'BILLING', 'decided_by' => $context->request->actorUserId,
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId,
            ]);
            $this->receivable->act($context, $invoice->id, $act, $dueDate);
            $balance = ReceivablePosting::balance($invoice->id);
            $this->records->record($context, 'FIN.InvoiceBilled', AuthorityClass::Adm, $invoice->id, null, $billedAt,
                ['billed_at' => $billedAt, 'due_date' => $dueDate, 'activated' => $balance === null ? '0.00' : Row::text($balance, 'outstanding')]);

            return new CommandResult('invoice', $invoice->id);
        }, null, fn (Outcome $outcome): ?array => $outcome->code === 'BD-07' ? self::root($invoice->id) : null);
    }

    /** @return array{billed_at: string, due_date: ?string}|null The root act's dates, what a second billing is answered with. */
    private static function root(int $invoiceId): ?array
    {
        $root = DB::table('invoice_billing_acts')->where('invoice_id', $invoiceId)->whereNull('supersedes_act_id')->first(['billed_at', 'due_date']);

        return $root === null ? null : ['billed_at' => Row::text($root, 'billed_at'), 'due_date' => Row::nullable($root, 'due_date')];
    }
}
