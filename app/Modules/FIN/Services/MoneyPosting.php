<?php

namespace App\Modules\FIN\Services;

use App\Modules\FIN\Models\ApplicationSourceBalance;
use App\Modules\FIN\Models\MoneyFactBalance;
use App\Support\Command\CommandContext;
use App\Support\GuardUpdate;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

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

    /** A payment's source guard, born with it at its recorded amount and the claims of its remittance advice (AX-18; DATABASE §11.3). */
    public function bearPayment(CommandContext $context, int $paymentId, int $companyId, int $clientId, string $amount, string $claimed): void
    {
        GuardUpdate::bear($context->connection, 'application_source_balances', ['payment_id' => $paymentId], [
            'company_id' => $companyId, 'client_organization_id' => $clientId, 'gross_amount' => $amount, 'claimed_deductions_amount' => $claimed, 'recorded_at' => $context->now(),
        ]);
    }

    /**
     * A source's net deltas in one statement, so its CHECKs read the end state (GU-01; C-13; C-44; C-45).
     *
     * @param  array<string, int>  $key  `payment_id` or `opening_customer_credit_id`
     * @param  array<string, string>  $deltas  amount column => signed decimal
     */
    public function moveSource(CommandContext $context, array $key, array $deltas): void
    {
        $deltas = self::moved($deltas);

        if ($deltas !== []) {
            GuardUpdate::apply($context->connection, 'application_source_balances', [['key' => $key, 'deltas' => $deltas]]);
        }
    }

    /**
     * A money fact's net deltas (C-44; C-45; BR-FIN-07 b).
     *
     * @param  array<string, int>  $key
     * @param  array<string, string>  $deltas
     */
    public function moveFact(CommandContext $context, array $key, array $deltas): void
    {
        $deltas = self::moved($deltas);

        if ($deltas !== []) {
            GuardUpdate::apply($context->connection, 'money_fact_balances', [['key' => $key, 'deltas' => $deltas]]);
        }
    }

    /**
     * A source's guard row, read under its lock.
     *
     * @param  array<string, int>  $key
     */
    public static function source(array $key): ?object
    {
        return ApplicationSourceBalance::query()->toBase()->where($key)->first();
    }

    /** A transfer-out's guard, what a citing Cash-In is capped by (BR-FIN-07 b; XL-5). */
    public static function transferOut(int $disbursementId): ?object
    {
        return MoneyFactBalance::query()->toBase()->where('disbursement_id', $disbursementId)->where('fact_kind', 'TRANSFER_OUT')->first(['company_id', 'amount', 'contra_amount', 'transfer_cited_amount']);
    }

    /**
     * @param  array<string, string>  $deltas
     * @return array<string, string>
     */
    private static function moved(array $deltas): array
    {
        return array_filter($deltas, fn (string $delta): bool => preg_match('/^-?0+(\.0+)?$/', $delta) !== 1);
    }

    /**
     * The invoice and those it replaces: money recovered for one is recovered for the invoice its write-off moved to (L-17; L-30).
     *
     * @return list<int>
     */
    public static function recoveryTargets(int $invoiceId): array
    {
        $ids = [];

        foreach (DB::select('with recursive chain(id, replaces) as (select id, replaces_invoice_id from invoices where id = ? union select i.id, i.replaces_invoice_id from invoices i join chain c on i.id = c.replaces) select id from chain', [$invoiceId]) as $row) {
            if (is_object($row)) {
                $ids[] = (int) Row::text($row, 'id');
            }
        }

        return $ids;
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
