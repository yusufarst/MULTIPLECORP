<?php

namespace App\Modules\FIN\Actions;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Services\InvoiceRecords;
use App\Modules\FIN\Services\ReceivablePosting;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
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
use Illuminate\Support\Facades\DB;

/**
 * `FIN RecordOpeningReceivable` (AX-28; WF-MIG-01; BR-FIN-14; DATABASE §24): an opening import row's receivable — an
 * invoice of origin OPENING holding the remaining value, issued as recorded, with an OPENING billing act carrying the
 * legacy billed date and due date, or no due date and the *migrasi* bucket — and its `invoice_balances`, a first-class
 * receivable: in the billed sum, a valid application target, aged from its due date. Its sale happened before the
 * cutover, so it moves neither live-period revenue nor the project invoice cap (BR-XD-06; CALC-08). Driven only.
 */
final class RecordOpeningReceivable
{
    public const TYPE = 'FIN.RecordOpeningReceivable';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly ReceivablePosting $receivable,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{import_row: int, client: int, project: int|null, reference: string, billed_at: string, due_date: string|null, sales: string, tax: string, date: string}  $entry
     */
    public function run(string $commandId, int $actorUserId, int $companyId, array $entry): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $entry));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $entry): CommandResult {
            $context->lock($entry['project'] === null ? new LockSet : (new LockSet)->add('projects', ['id' => $entry['project']]));

            if ($entry['project'] !== null && DB::table('projects')->where('id', $entry['project'])->value('company_id') !== $companyId) {
                throw Refused::rejected('CS-04', ['field' => 'proyek']);
            }

            $sales = Money::of($entry['sales']);
            $tax = Money::of($entry['tax']);

            if (! $sales->plus($tax)->isPositive()) {
                throw Refused::rejected('L-16', ['reason' => 'no_value']);
            }

            $now = $context->now();
            $actor = $context->request->actorUserId;
            $invoice = new Invoice;
            $invoice->forceFill([
                'company_id' => $companyId, 'project_id' => $entry['project'], 'client_organization_id' => $entry['client'], 'origin' => 'OPENING', 'import_row_id' => $entry['import_row'],
                'aging_basis' => $entry['due_date'] === null ? 'MIGRASI' : 'DUE_DATE', 'state' => 'ISSUED',
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $actor,
            ])->save();
            $snapshot = $entry['project'] === null
                ? ['organization' => DB::table('client_organizations')->where('id', $entry['client'])->value('name')]
                : InvoiceRecords::clientSnapshot($entry['project']);
            $version = (int) $context->connection->table('invoice_versions')->insertGetId([
                'invoice_id' => $invoice->id, 'company_id' => $companyId, 'version_no' => 1, 'state' => 'ISSUED', 'kind' => 'VALUE_ONLY', 'business_date' => $entry['billed_at'],
                'sales_value' => $sales->toString(), 'output_tax_value' => $tax->toString(), 'client_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'revision_basis' => 'INITIAL',
                'command_id' => $context->request->commandId, 'command_line_no' => 2, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $context->connection->table('invoices')->where('id', $invoice->id)->update(['current_version_id' => $version, 'updated_at' => $now]);
            $act = (int) $context->connection->table('invoice_billing_acts')->insertGetId([
                'invoice_id' => $invoice->id, 'billed_at' => $entry['billed_at'], 'due_date' => $entry['due_date'], 'kind' => 'OPENING', 'import_row_id' => $entry['import_row'],
                'reason' => "Saldo awal faktur {$entry['reference']}", 'decided_by' => $actor, 'command_id' => $context->request->commandId, 'command_line_no' => 3, 'recorded_at' => $now, 'recorded_by' => $actor,
            ]);
            $this->receivable->bear($context, $invoice->id, $companyId, $sales->toString(), $tax->toString(), $sales->plus($tax)->toString(), $act, $entry['due_date']);
            $this->audit->record(
                action: 'FIN.OpeningReceivableRecorded',
                authorityClass: AuthorityClass::OwnerOnly,
                actorUserId: $actor,
                companyId: $companyId,
                entityType: 'invoice',
                entityId: $invoice->id,
                entityPublicId: $invoice->public_id,
                commandId: $context->request->commandId,
                change: AuditChange::created(['reference' => $entry['reference'], 'remaining' => $sales->plus($tax)->toString(), 'due_date' => $entry['due_date']]),
                businessDate: $entry['date'],
                metadata: ['import_row_id' => $entry['import_row']],
            );

            return new CommandResult('invoice', $invoice->id);
        });
    }
}
