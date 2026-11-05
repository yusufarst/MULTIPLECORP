<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceCorrections;
use App\Modules\FIN\Services\MoneyPosting;
use App\Modules\FIN\Services\ReceivablePosting;
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
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * `FIN ContraOpeningFact` (ROUTE-214; INT-214; NX-11 with AX-20's and AX-33's classes; CM-33; IP-09): "Koreksi Data
 * Awal" on an opening fact's record with its reason, only once its applications are disposed — an opening receivable
 * corrected down to its right remaining value, a new version of basis ENTRY_ERROR superseding the imported one and the
 * receivable moved by the difference; an opening credit contra'd by a financial contra within its net, its source moved
 * —; never by importing it again. `finance.contra` (ADM_PLUS) within the fact's company.
 */
final class ContraOpeningFact
{
    public const TYPE = 'FIN.ContraOpeningFact';

    public const CAPABILITY = 'finance.contra';

    public const RECEIVABLE = 'piutang';

    public const CREDIT = 'kredit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ReceivablePosting $receivable,
        private readonly MoneyPosting $money,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param  array{sales: string|null, tax: string|null, amount: string|null}  $values  a receivable's right remaining sales and tax, a credit's contra amount */
    public function run(string $commandId, int $actorUserId, int $companyId, string $kind, int $id, array $values, string $reason, string $businessDate, bool $prefilled): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY,
            Fingerprint::of(self::TYPE, null, ['kind' => $kind, 'id' => $id, ...$values, 'reason' => $reason, 'date' => $businessDate]));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $kind, $id, $values, $reason, $businessDate, $prefilled): CommandResult {
            BusinessDateRule::check($businessDate, $prefilled);

            return $kind === self::CREDIT
                ? $this->credit($context, $companyId, $id, $values['amount'], trim($reason), $businessDate)
                : $this->receivable($context, $companyId, $id, $values['sales'] ?? '0.00', $values['tax'] ?? '0.00', trim($reason), $businessDate);
        });
    }

    private function receivable(CommandContext $context, int $companyId, int $id, string $sales, string $tax, string $reason, string $businessDate): CommandResult
    {
        $invoice = Invoice::query()->whereKey($id)->where('company_id', $companyId)->where('origin', 'OPENING')->firstOrFail();
        $context->lock(ReceivablePosting::anchors($invoice));
        $invoice = Invoice::query()->whereKey($id)->firstOrFail();
        $reductions = InvoiceCorrections::reductions($invoice->id);

        // CM-33: the receivable is corrected only after its applications, settlements and write-offs are disposed.
        if ($invoice->state !== 'ISSUED' || $reductions['applications'] !== [] || $reductions['settlements'] !== [] || $reductions['writeoffs'] !== []) {
            throw Refused::rejected('CM-33', ['reason' => $invoice->state !== 'ISSUED' ? 'state' : 'applications']);
        }

        $current = DB::table('invoice_versions')->where('id', $invoice->current_version_id)->first(['id', 'version_no', 'sales_value', 'output_tax_value', 'gross_value', 'client_snapshot'])
            ?? throw Refused::rejected('CM-33', ['reason' => 'state']);
        $newSales = Money::of($sales);
        $newTax = Money::of($tax);
        $oldGross = Money::of(Row::text($current, 'gross_value'));

        if ($newSales->isNegative() || $newTax->isNegative() || $newSales->plus($newTax)->compareTo($oldGross) >= 0) {
            throw Refused::rejected('CM-33', ['reason' => 'value', 'gross' => $oldGross->toString()]);
        }

        $now = $context->now();
        $actor = $context->request->actorUserId;
        $context->connection->table('invoice_versions')->where('id', (int) Row::text($current, 'id'))->update(['state' => 'SUPERSEDED', 'updated_at' => $now]);
        $version = (int) $context->connection->table('invoice_versions')->insertGetId([
            'invoice_id' => $invoice->id, 'company_id' => $companyId, 'version_no' => (int) Row::text($current, 'version_no') + 1, 'state' => 'ISSUED', 'kind' => 'VALUE_ONLY',
            'business_date' => $businessDate, 'sales_value' => $newSales->toString(), 'output_tax_value' => $newTax->toString(), 'client_snapshot' => Row::text($current, 'client_snapshot'),
            'revision_reason' => $reason, 'revision_basis' => 'ENTRY_ERROR', 'supersedes_version_id' => (int) Row::text($current, 'id'),
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
        ]);
        $context->connection->table('invoices')->where('id', $invoice->id)->update(['current_version_id' => $version, 'lock_version' => $invoice->lock_version + 1, 'updated_at' => $now]);
        $this->receivable->move($context, $invoice->id, [
            'gross_value' => $newSales->plus($newTax)->minus($oldGross)->toString(),
            'sales_value' => $newSales->minus(Money::of(Row::text($current, 'sales_value')))->toString(),
            'output_tax_value' => $newTax->minus(Money::of(Row::text($current, 'output_tax_value')))->toString(),
        ]);
        $this->audit->record(
            action: 'FIN.OpeningReceivableCorrected',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $actor,
            companyId: $companyId,
            entityType: 'invoice',
            entityId: $invoice->id,
            entityPublicId: $invoice->public_id,
            reason: $reason,
            commandId: $context->request->commandId,
            change: AuditChange::updated(['remaining' => $oldGross->toString()], ['remaining' => $newSales->plus($newTax)->toString()]),
            businessDate: $businessDate,
            metadata: ['import_row_id' => $invoice->import_row_id],
        );

        return new CommandResult('invoice', $invoice->id);
    }

    private function credit(CommandContext $context, int $companyId, int $id, ?string $amount, string $reason, string $businessDate): CommandResult
    {
        $key = ['opening_customer_credit_id' => $id];
        $context->lock((new LockSet)->add('opening_customer_credits', ['id' => $id])->add('application_source_balances', $key));
        $credit = DB::table('opening_customer_credits')->where('id', $id)->where('company_id', $companyId)->first(['id', 'import_row_id'])
            ?? throw Refused::rejected('CM-33', ['reason' => 'unknown']);
        $source = DB::table('application_source_balances as s')->where('s.opening_customer_credit_id', $id)->first(['s.net_amount', 's.capacity_amount']);

        // CM-33: the credit is contra'd only after its applications are disposed; never beyond what it still holds (AX-33).
        if (DB::table('payment_applications')->where('opening_customer_credit_id', $id)->where('state', 'ACTIVE')->exists() || $source === null) {
            throw Refused::rejected('CM-33', ['reason' => 'applications']);
        }

        $take = Money::of($amount ?? Row::text($source, 'capacity_amount'));

        if (! $take->isPositive() || $take->compareTo(Money::of(Row::text($source, 'capacity_amount'))) > 0) {
            throw Refused::rejected('C-45', ['reason' => 'above_net', 'net' => Row::text($source, 'capacity_amount')]);
        }

        $contra = (int) $context->connection->table('financial_contras')->insertGetId([
            'opening_customer_credit_id' => $id, 'company_id' => $companyId, 'amount' => $take->toString(), 'resolves_application_id' => null, 'reason' => $reason,
            'business_date' => $businessDate, 'correction_case_id' => null, 'recorded_by' => $context->request->actorUserId,
            'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now(),
        ]);
        $this->money->moveSource($context, $key, ['contra_amount' => $take->toString()]);
        $this->audit->record(
            action: 'FIN.OpeningCreditContraed',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $context->request->actorUserId,
            companyId: $companyId,
            entityType: 'opening_customer_credit',
            entityId: $id,
            reason: $reason,
            commandId: $context->request->commandId,
            change: AuditChange::updated(['net' => Row::text($source, 'net_amount')], ['contra' => $take->toString()]),
            businessDate: $businessDate,
            metadata: ['import_row_id' => (int) Row::text($credit, 'import_row_id'), 'financial_contra_id' => $contra],
        );

        return new CommandResult('financial_contra', $contra);
    }
}
