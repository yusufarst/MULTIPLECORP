<?php

namespace App\Modules\FIN\Services;

use App\Support\Command\CommandContext;
use App\Support\GuardUpdate;

/**
 * The one normal writer of `invoice_balances` (ARCHITECTURE §7; DATABASE §11.2): its row born at the issue with the issued value,
 * each invoice moved once per command by its net deltas, so a reduction and the fall of the gross reach the conservation CHECK
 * together (C-15; C-16) and a correction that leaves a reduction undisposed cannot commit.
 */
final class ReceivablePosting
{
    /** The issue initializes the guard: the billed sum, the sales value and the output tax, nothing applied, no billing act (annex 1; BR-FIN-02). */
    public function bear(CommandContext $context, int $invoiceId, int $companyId, string $sales, string $tax, string $gross, ?int $actId = null, ?string $dueDate = null): void
    {
        GuardUpdate::bear($context->connection, 'invoice_balances', ['invoice_id' => $invoiceId], [
            'company_id' => $companyId, 'gross_value' => $gross, 'sales_value' => $sales, 'output_tax_value' => $tax,
            'current_billing_act_id' => $actId, 'current_due_date' => $dueDate, 'recorded_at' => $context->now(),
        ]);
    }

    /** @param  array<string, string>  $deltas  amount column => signed decimal */
    public function move(CommandContext $context, int $invoiceId, array $deltas): void
    {
        $deltas = array_filter($deltas, fn (string $delta): bool => preg_match('/^-?0+(\.0+)?$/', $delta) !== 1);

        if ($deltas !== []) {
            GuardUpdate::apply($context->connection, 'invoice_balances', [['key' => ['invoice_id' => $invoiceId], 'deltas' => $deltas]]);
        }
    }
}
