<?php

namespace App\Modules\FIN\Services;

use App\Modules\DOC\Contracts\BankLineCitations;
use App\Modules\DOC\Services\EvidenceGuard;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\Money;
use App\Support\RowHandle;
use App\Support\Search\Row;
use Illuminate\Support\Facades\DB;

/**
 * FIN's part of `BankLineCitations` (ARCHITECTURE §3; NX-08; DATABASE §11.3; QS-12): a payment recorded before its statement line arrived, flagged until then, takes its citation in the command
 * that records the line, the line's guard moved by what the payment still carries, net of its contras (C-19; L-22). A payment already cited since the page read it is a CONFLICT; one of another account refused.
 */
final class PaymentCitations implements BankLineCitations
{
    public function __construct(private readonly EvidenceGuard $evidence, private readonly PaymentRecords $records) {}

    public function locks(array $facts): LockSet
    {
        $set = new LockSet;

        foreach ($facts as $payment) {
            $set = $set->add('payments', ['id' => $payment])->add('application_source_balances', ['payment_id' => $payment]);
        }

        return $set;
    }

    public function arrived(CommandContext $context, int $lineId, int $accountId, string $amount, array $facts): void
    {
        $facts = array_values(array_unique($facts));
        $carried = Money::zero();

        foreach ($facts as $payment) {
            $carried = $carried->plus(Money::of(self::ensureCitable($payment, $accountId)));
        }

        if ($carried->compareTo(Money::of($amount)) > 0) {
            throw Refused::rejected('C-19', ['reason' => 'above_line', 'uncited' => $amount, 'amount' => $carried->toString()]);
        }

        if ($facts === []) {
            return;
        }

        DB::table('payments')->whereIn('id', $facts)->update(['bank_statement_line_id' => $lineId]);
        $this->evidence->moveLines($context, [$lineId => ['cited_amount' => $carried->toString()]]);

        foreach ($facts as $payment) {
            $this->records->record($context, 'FIN.BankCitationSet', AuthorityClass::Adm, $payment, null, null, ['line' => RowHandle::of('bank_statement_lines', $lineId)]);
        }
    }

    /** What a flagged payment of this account would cite: its net money. */
    private static function ensureCitable(int $paymentId, int $accountId): string
    {
        $payment = DB::table('payments')->where('id', $paymentId)->first(['company_bank_account_id', 'bank_statement_line_id']);

        if ($payment === null || Row::nullable($payment, 'company_bank_account_id') !== (string) $accountId) {
            throw Refused::rejected('C-19', ['reason' => 'other_account']);
        }

        if (Row::nullable($payment, 'bank_statement_line_id') !== null) {
            throw Refused::conflict('ST-02', ['state' => 'CITED']);
        }

        $source = MoneyPosting::source(['payment_id' => $paymentId]);

        return $source === null ? '0.00' : Row::text($source, 'net_amount');
    }
}
