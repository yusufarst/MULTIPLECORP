<?php

namespace App\Modules\FIN\Data;

/**
 * PG-049's vocabulary (DATABASE §4.12, §11.5): the disbursement kinds recorded there, each with the money-fact kind its guard takes, and the other Cash-In categories with
 * their income class — received cashback and other income are income (BR-FIN-10), a supplier refund, a transfer in and other restitution are not (L-08; BR-FIN-07).
 */
final class OtherCash
{
    /** The kinds "Catat Pengeluaran Kas" records; a REFUND is PG-048's (TASK-053). */
    public const DISBURSEMENT_KINDS = ['PURCHASE_PAYMENT', 'EXPENSE_PAYMENT', 'TAX_REMITTANCE', 'INTERCOMPANY_TRANSFER_OUT'];

    public const CATEGORIES = ['CASHBACK_INCOME', 'OTHER_INCOME', 'SUPPLIER_REFUND', 'INTERCOMPANY_TRANSFER_IN', 'OTHER_NON_INCOME'];

    /** The proofs of a money movement (L-29), as a payment's. */
    public const PROOF = ['PAYMENT_PROOF', 'TRANSFER_PROOF', 'BANK_STATEMENT'];

    /** The income class `other_cash_receipts_income_by_category` requires. */
    public static function incomeClass(string $category): string
    {
        return in_array($category, ['CASHBACK_INCOME', 'OTHER_INCOME'], true) ? 'INCOME' : 'NON_INCOME';
    }

    /** The guard kind `money_fact_balances` takes for the disbursement (DATABASE §4.12; the kind trigger). */
    public static function factKind(string $kind): string
    {
        return match ($kind) {
            'INTERCOMPANY_TRANSFER_OUT' => 'TRANSFER_OUT',
            'REFUND' => 'REFUND',
            default => 'OTHER_DISBURSEMENT',
        };
    }
}
