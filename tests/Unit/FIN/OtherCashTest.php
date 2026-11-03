<?php

use App\Modules\CST\Actions\RecordExpense;
use App\Modules\FIN\Data\OtherCash;

// TL-03 (TASK-054; BR-FIN-10; L-08; BR-FIN-07; C-23): the income class each other Cash-In category takes, as `other_cash_receipts_income_by_category` requires, and the money-fact kind each
// disbursement's guard takes; the categories a manual expense may never carry.

it('classes received cashback and other income as income, and every restitution as not income (BR-FIN-10; L-08; FS-13)', function (string $category, string $class) {
    expect(OtherCash::incomeClass($category))->toBe($class);
})->with([
    'cashback' => ['CASHBACK_INCOME', 'INCOME'],
    'other income' => ['OTHER_INCOME', 'INCOME'],
    'supplier refund' => ['SUPPLIER_REFUND', 'NON_INCOME'],
    'transfer in' => ['INTERCOMPANY_TRANSFER_IN', 'NON_INCOME'],
    'other restitution' => ['OTHER_NON_INCOME', 'NON_INCOME'],
]);

it('covers every category the schema codes, each once', function () {
    expect(OtherCash::CATEGORIES)->toHaveCount(5)->and(array_unique(OtherCash::CATEGORIES))->toBe(OtherCash::CATEGORIES);
});

it('bears a transfer-out as TRANSFER_OUT and every other PG-049 kind as OTHER_DISBURSEMENT (DATABASE §4.12; BR-FIN-07 b)', function (string $kind, string $fact) {
    expect(OtherCash::factKind($kind))->toBe($fact);
})->with([
    'purchase payment' => ['PURCHASE_PAYMENT', 'OTHER_DISBURSEMENT'],
    'expense payment' => ['EXPENSE_PAYMENT', 'OTHER_DISBURSEMENT'],
    'tax remittance' => ['TAX_REMITTANCE', 'OTHER_DISBURSEMENT'],
    'transfer out' => ['INTERCOMPANY_TRANSFER_OUT', 'TRANSFER_OUT'],
    'refund' => ['REFUND', 'REFUND'],
]);

it('keeps the refund out of PG-049\'s kinds: it is PG-048\'s (TASK-053)', function () {
    expect(OtherCash::DISBURSEMENT_KINDS)->not->toContain('REFUND');
});

it('reserves the settlement\'s and the charge\'s expense categories (C-23; BR-PUR-05)', function () {
    expect(RecordExpense::RESERVED)->toBe(['FEE_DEDUCTION', 'TAX_TREATMENT', 'PURCHASE_CHARGE']);
});
