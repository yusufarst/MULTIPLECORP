<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Models\ApplicationSourceBalance;
use App\Modules\FIN\Models\MoneyFactBalance;
use App\Support\Command\CommandContext;
use App\Support\GuardUpdate;
use App\Support\Search\Row;

/**
 * The money guards a receivable command moves (ARCHITECTURE §7; DATABASE §11.4): a write-off's money fact, born with it and contra'd by its supersession
 * (L-17), and a payment's application source; and what a same-client credit stands at for AX-24's check (L-17). Each read under the anchors its command holds.
 */
final class MoneyPosting
{
    public function bearWriteoff(CommandContext $context, int $writeoffId, int $companyId, string $amount): void
    {
        GuardUpdate::bear($context->connection, 'money_fact_balances', ['receivable_writeoff_id' => $writeoffId], ['company_id' => $companyId, 'fact_kind' => 'WRITEOFF', 'amount' => $amount, 'recorded_at' => $context->now()]);
    }

    public function contraWriteoff(CommandContext $context, int $writeoffId, string $amount): void
    {
        GuardUpdate::apply($context->connection, 'money_fact_balances', [['key' => ['receivable_writeoff_id' => $writeoffId], 'deltas' => ['contra_amount' => $amount]]]);
    }

    public function applyFromPayment(CommandContext $context, int $paymentId, string $amount): void
    {
        GuardUpdate::apply($context->connection, 'application_source_balances', [['key' => ['payment_id' => $paymentId], 'deltas' => ['invoice_applied_amount' => $amount]]]);
    }

    /** What of a write-off still stands: its amount less its supersessions. */
    public static function writeoffStanding(int $writeoffId): string
    {
        $row = MoneyFactBalance::query()->toBase()->where('receivable_writeoff_id', $writeoffId)->selectRaw('amount - contra_amount as standing')->first();

        return is_object($row) ? Row::text($row, 'standing') : '0.00';
    }

    /** What of a payment is still unapplied. */
    public static function paymentCapacity(int $paymentId): string
    {
        $capacity = ApplicationSourceBalance::query()->toBase()->where('payment_id', $paymentId)->value('capacity_amount');

        return is_scalar($capacity) ? (string) $capacity : '0.00';
    }

    /**
     * The same client and company's sources holding unapplied credit, by their guard's key (L-17; BR-FIN-05).
     *
     * @return list<array<string, int>>
     */
    public static function creditSources(int $companyId, int $clientId): array
    {
        $keys = [];

        foreach (ApplicationSourceBalance::query()->toBase()->where('company_id', $companyId)->where('client_organization_id', $clientId)->where('capacity_amount', '>', 0)->orderBy('id')->get(['payment_id', 'opening_customer_credit_id']) as $row) {
            $payment = Row::nullable($row, 'payment_id');
            $keys[] = $payment !== null ? ['payment_id' => (int) $payment] : ['opening_customer_credit_id' => (int) Row::text($row, 'opening_customer_credit_id')];
        }

        return $keys;
    }

    /** The same client and company's unapplied credit (L-17). */
    public static function availableCredit(int $companyId, int $clientId): string
    {
        $row = ApplicationSourceBalance::query()->toBase()->where('company_id', $companyId)->where('client_organization_id', $clientId)->selectRaw('coalesce(sum(capacity_amount), 0)::numeric(16,2) as credit')->first();

        return is_object($row) ? Row::text($row, 'credit') : '0.00';
    }
}
