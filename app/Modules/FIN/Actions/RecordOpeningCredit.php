<?php

namespace App\Modules\FIN\Actions;

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
use App\Support\GuardUpdate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * `FIN RecordOpeningCredit` (AX-28; WF-MIG-01; L-50; DATABASE §24): an opening import row's customer credit for a
 * client in a company, with its signed opening evidence of that company — an application source for invoices of the
 * same client and company, never a live-period Cash-In —, its `application_source_balances` row born with it.
 * Driven only.
 */
final class RecordOpeningCredit
{
    public const TYPE = 'FIN.RecordOpeningCredit';

    public const CAPABILITY = 'import.commit';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{import_row: int, client: int, amount: string, evidence: int, date: string}  $entry
     */
    public function run(string $commandId, int $actorUserId, int $companyId, array $entry): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $companyId, self::CAPABILITY, Fingerprint::of(self::TYPE, null, $entry));

        return $this->envelope->run($request, function (CommandContext $context) use ($companyId, $entry): CommandResult {
            $context->lock(new LockSet);

            if (DB::table('evidence_documents')->where('id', $entry['evidence'])->value('company_id') !== $companyId) {
                throw Refused::rejected('CS-04', ['field' => 'bukti']);
            }

            if (! Money::of($entry['amount'])->isPositive()) {
                throw Refused::rejected('L-50', ['reason' => 'no_value']);
            }

            $now = $context->now();
            $credit = (int) $context->connection->table('opening_customer_credits')->insertGetId([
                'company_id' => $companyId, 'client_organization_id' => $entry['client'], 'amount' => $entry['amount'], 'import_row_id' => $entry['import_row'],
                'signed_evidence_id' => $entry['evidence'], 'business_date' => $entry['date'],
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $now, 'recorded_by' => $context->request->actorUserId,
            ]);
            GuardUpdate::bear($context->connection, 'application_source_balances', ['opening_customer_credit_id' => $credit], [
                'company_id' => $companyId, 'client_organization_id' => $entry['client'], 'gross_amount' => $entry['amount'], 'claimed_deductions_amount' => '0.00', 'recorded_at' => $now,
            ]);
            $this->audit->record(
                action: 'FIN.OpeningCreditRecorded',
                authorityClass: AuthorityClass::OwnerOnly,
                actorUserId: $context->request->actorUserId,
                companyId: $companyId,
                entityType: 'opening_customer_credit',
                entityId: $credit,
                commandId: $context->request->commandId,
                change: AuditChange::created(['client_organization_id' => $entry['client'], 'amount' => $entry['amount']]),
                businessDate: $entry['date'],
                metadata: ['import_row_id' => $entry['import_row']],
            );

            return new CommandResult('opening_customer_credit', $credit);
        });
    }
}
