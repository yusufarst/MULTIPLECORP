<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\InvoiceBalance;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardMissing;
use App\Support\GuardUpdate;
use App\Support\Money;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

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

    /** The guard's current act and due date, what ageing reads (AX-17; L-30). */
    public function act(CommandContext $context, int $invoiceId, int $actId, ?string $dueDate): void
    {
        $affected = $context->connection->table('invoice_balances')->where('invoice_id', $invoiceId)->update(['current_billing_act_id' => $actId, 'current_due_date' => $dueDate]);

        if ($affected !== 1) {
            throw new GuardMissing('invoice_balances', 1, $affected);
        }
    }

    /** AX-17's and AX-24's anchors: the invoice's project at LK-01, so a racing completion re-reads its predicates (M-26), and the invoice at LK-25. */
    public static function anchors(Invoice $invoice): LockSet
    {
        $set = (new LockSet)->add('invoices', ['id' => $invoice->id]);

        return $invoice->project_id === null ? $set : $set->add('projects', ['id' => $invoice->project_id]);
    }

    public static function balance(int $invoiceId): ?object
    {
        return InvoiceBalance::query()->toBase()->where('invoice_id', $invoiceId)->first();
    }

    /** The invoice issued and not void, re-read under the anchors (AX-17): a void since the page read it a CONFLICT, a draft refused. */
    public static function ensureIssued(int $invoiceId): void
    {
        $state = DB::table('invoices')->where('id', $invoiceId)->value('state');

        if ($state === 'VOIDED') {
            throw Refused::conflict('ST-02', ['state' => 'VOIDED']);
        }

        if ($state !== 'ISSUED') {
            throw Refused::rejected('BR-FIN-02', ['reason' => 'not_issued']);
        }
    }

    /** A billed invoice with something outstanding: what a hold and a write-off act on (BR-FIN-08); its guard's row. */
    public static function ensureReceivable(int $invoiceId): object
    {
        self::ensureIssued($invoiceId);
        $balance = self::balance($invoiceId);

        if ($balance === null || Row::nullable($balance, 'current_billing_act_id') === null || ! Money::of(Row::text($balance, 'outstanding'))->isPositive()) {
            throw Refused::rejected('BR-FIN-08', ['reason' => 'no_receivable']);
        }

        return $balance;
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
