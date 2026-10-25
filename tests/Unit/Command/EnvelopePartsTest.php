<?php

use App\Support\Command\DuplicateWarning;
use App\Support\Command\FailureMapper;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockClass;
use App\Support\Command\LockPosition;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;
use Illuminate\Database\QueryException;

// TL-03 (TASK-013): the failure mapper of CONCURRENCY_IDEMPOTENCY §12, the lock-set planner of §4–§5, the
// fingerprint of CI-02 and INT-015's duplicate warning.

function databaseError(string $state, string $message): QueryException
{
    $previous = new PDOException($message);
    $previous->errorInfo = [$state, 7, $message];
    $error = new QueryException('pgsql', 'select 1', [], $previous);
    (fn () => $this->code = $state)->call($error);

    return $error;
}

it('maps each SQLSTATE to its answer (§12)', function (string $state, string $message, Outcome|string $expected) {
    $decision = FailureMapper::map(databaseError($state, $message));

    expect($decision instanceof Outcome ? [$decision->kind, $decision->code] : $decision)
        ->toBe($expected instanceof Outcome ? [$expected->kind, $expected->code] : $expected);
})->with([
    'a CHECK named for its C-row (SQ-04)' => ['23514', 'new row violates check constraint "stock_product_balances_c04_available"', Outcome::rejected('C-04')],
    'a trigger naming its rule (SQ-05)' => ['23514', "ERROR: x\nCONTEXT: PL/pgSQL function c50_business_date_not_future() line 4 at RAISE", Outcome::rejected('C-50')],
    'an L-row trigger' => ['23514', 'violates check constraint "stock_restorations_l31_cited_unit"', Outcome::rejected('L-31')],
    'a CHECK without a registered rule (SQ-19)' => ['23514', 'violates check constraint "invoices_forward_only"', Outcome::failed('SQ-19 23514 invoices_forward_only')],
    'a business identity key (SQ-08)' => ['23505', 'duplicate key value violates unique constraint "receiving_lines_c38_delivery_reference"', Outcome::conflict('C-38')],
    'a command key after expiry (SQ-07)' => ['23505', 'duplicate key value violates unique constraint "payments_c48_command_identity"', Outcome::conflict('SQ-07')],
    'an exclusion constraint (SQ-10)' => ['23P01', 'conflicting key value violates exclusion constraint "x"', Outcome::rejected('C-52')],
    'a reference that no longer resolves (SQ-09)' => ['23503', 'violates foreign key constraint "payments_company_bank_account_foreign"', FailureMapper::NOT_FOUND],
    'a serialization failure (SQ-12)' => ['40001', 'could not serialize access', FailureMapper::RETRY],
    'a deadlock (SQ-13)' => ['40P01', 'deadlock detected', FailureMapper::RETRY],
    'a session lost before COMMIT (SQ-16)' => ['57P01', 'terminating connection', FailureMapper::RETRY],
    'a connection the driver lost, with no server state (SQ-16)' => ['HY000', 'SQLSTATE[HY000]: General error: 7 server closed the connection unexpectedly', FailureMapper::RETRY],
    'the referenced side of a key, a defect (SQ-09)' => ['23503', 'update or delete on table "products" violates foreign key constraint "stock_product_balances_product_id_foreign" on table "stock_product_balances" DETAIL: Key (id)=(1) is still referenced from table "stock_product_balances".', Outcome::failed('SQ-19 23503 stock_product_balances_product_id_foreign')],
    'a lock timeout, never retried (SQ-14; RY-07)' => ['55P03', 'canceling statement due to lock timeout', Outcome::failed('SQ-14 lock timeout')],
    'a statement timeout (SQ-15)' => ['57014', 'canceling statement due to statement timeout', Outcome::failed('SQ-15 statement timeout')],
    'a raise without a rule (SQ-11)' => ['P0001', 'raise', Outcome::failed('SQ-19 P0001')],
]);

it('plans locks in class position, the class\'s table order, then key (LR-01, LR-05)', function () {
    $set = (new LockSet)
        ->add('invoice_balances', ['invoice_id' => 9])
        ->add('stock_product_balances', ['product_id' => 12])
        ->add('invoices', ['id' => 9])
        ->add('stock_product_balances', ['product_id' => 3])
        ->add('projects', ['id' => 40])
        ->add('users', ['id' => 2]);

    expect(array_map(fn (array $row): string => $row['table'].'#'.implode(',', $row['key']), $set->ordered()))->toBe([
        'projects#40', 'users#2', 'stock_product_balances#3', 'stock_product_balances#12', 'invoices#9', 'invoice_balances#9',
    ]);
});

it('orders a table keyed by kind in §4.2\'s sub-order, and one integer key however it is given (LK-24, LK-26, LK-27, LK-29; LR-04)', function () {
    $set = (new LockSet)
        ->add('money_fact_balances', ['expense_id' => 1])
        ->add('money_fact_balances', ['disbursement_id' => 8])
        ->add('evidence_balances', ['bank_statement_line_id' => 2])
        ->add('evidence_balances', ['evidence_document_id' => 30])
        ->add('application_source_balances', ['opening_customer_credit_id' => 1])
        ->add('application_source_balances', ['payment_id' => '10'])
        ->add('application_source_balances', ['payment_id' => 9])
        ->add('application_source_balances', ['payment_id' => 10]);

    expect(array_map(fn (array $row): string => $row['table'].'#'.json_encode($row['key']), $set->ordered()))->toBe([
        'application_source_balances#{"payment_id":9}', 'application_source_balances#{"payment_id":10}', 'application_source_balances#{"opening_customer_credit_id":1}',
        'money_fact_balances#{"disbursement_id":8}', 'money_fact_balances#{"expense_id":1}',
        'evidence_balances#{"evidence_document_id":30}', 'evidence_balances#{"bank_statement_line_id":2}',
    ])
        ->and($set->contains('application_source_balances', ['payment_id' => '9']))->toBeTrue()
        ->and(fn () => (new LockSet)->add('money_fact_balances', ['company_id' => 1]))->toThrow(InvalidArgumentException::class);
});

it('keeps a set\'s union across restarts and knows when a re-derived set grew (§5)', function () {
    $a = (new LockSet)->add('projects', ['id' => 1]);
    $b = (new LockSet)->add('correction_cases', ['id' => 7]);

    expect($a->covers($b))->toBeFalse()
        ->and($a->union($b)->covers($b))->toBeTrue()
        ->and($a->union($b)->contains('projects', ['id' => 1]))->toBeTrue();
});

it('refuses a lock sorting before one already held, and a class locked by its own mechanism (LR-01, LR-02)', function () {
    $held = new LockPosition;
    $held->advance(LockClass::Product, 0, 0, ['product_id' => 5], 'p5');

    expect(fn () => $held->advance(LockClass::User, 0, 0, ['id' => 1], 'u1'))->toThrow(LogicException::class)
        ->and(fn () => $held->advance(LockClass::Product, 0, 0, ['product_id' => 4], 'p4'))->toThrow(LogicException::class)
        ->and($held->sortsBefore(LockClass::Product, 0, 0, ['product_id' => 6]))->toBeFalse()
        ->and(fn () => (new LockSet)->add('command_log', ['command_id' => 'x']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new LockSet)->add('sessions', ['id' => 'x']))->toThrow(InvalidArgumentException::class);
});

it('fingerprints the canonical payload: key order, NFC and white space change nothing; content does (CI-02)', function () {
    $one = Fingerprint::of('payment.record', 'c-1', ['amount' => '100.00', 'note' => "Cafe\u{0301}", 'lines' => [1, 2], 'prefilled' => false]);
    $same = Fingerprint::of('payment.record', 'c-1', ['prefilled' => false, 'lines' => [1, 2], 'note' => "Caf\u{00E9}", 'amount' => '100.00']);

    expect($one)->toStartWith('v1:')->toHaveLength(67)
        ->and($same)->toBe($one)
        ->and(Fingerprint::of('payment.record', 'c-1', ['amount' => '100.00', 'note' => "Caf\u{00E9}", 'lines' => [2, 1], 'prefilled' => false]))->not->toBe($one)
        ->and(Fingerprint::of('payment.record', 'c-2', ['amount' => '100.00', 'note' => "Caf\u{00E9}", 'lines' => [1, 2], 'prefilled' => false]))->not->toBe($one);
});

it('refuses a fingerprinted float — money is a decimal string (CI-02)', function () {
    Fingerprint::of('x', null, ['amount' => 1.5]);
})->throws(InvalidArgumentException::class);

it('refuses again a duplicate the submission does not acknowledge, or one acknowledged without its reason (INT-015)', function () {
    expect(fn () => DuplicateWarning::check('BD-02', ['b', 'a'], ['a'], 'sama'))->toThrow(Refused::class)
        ->and(fn () => DuplicateWarning::check('BD-02', ['a'], ['a'], ' '))->toThrow(Refused::class);

    try {
        DuplicateWarning::check('BD-02', ['b', 'a'], [], null);
    } catch (Refused $refused) {
        expect([$refused->outcome->code, $refused->outcome->state])->toBe(['BD-02', ['matches' => ['a', 'b']]]);
    }

    DuplicateWarning::check('BD-02', ['a', 'b'], ['b', 'a'], 'pembayaran terpisah');
    DuplicateWarning::check('BD-02', [], [], null);
});
