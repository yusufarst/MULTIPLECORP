<?php

use App\Modules\FIN\Models\Invoice;
use App\Modules\FIN\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Refusal;

// TL-05 (TASK-009), C-13 and C-15 under two connections (TEST_STRATEGY §6; DATABASE §11.3, §11.2): real
// PostgreSQL sessions as the runtime role, the second blocked on the first's uncommitted write to the same
// guard row and re-evaluated on the committed row. Two applications of one payment never take more than its
// money, an application and a refund's backing neither; two reductions of one receivable never take more
// than its gross.

uses(DatabaseTruncation::class);

afterEach(function () {
    // The sessions commit, so the tests after these in this process start from empty tables.
    $this->truncateTablesForAllConnections();
});

/**
 * The first session holds its guard write uncommitted until the second is blocked on it, then commits; each
 * outcome is `committed` or the refusal's SQLSTATE and message.
 *
 * @param  list<mixed>  $bindings
 * @return array<string, string>
 */
function contendForGuard(string $first, string $second, array $bindings): array
{
    $holder = static function () use ($first, $bindings): string {
        DB::beginTransaction();
        DB::affectingStatement($first, $bindings);
        Barrier::await('the first holds the guard');
        Sessions::awaitBlocked();
        DB::commit();

        return 'committed';
    };
    $waiter = static function () use ($second, $bindings): string {
        Barrier::await('the first holds the guard');

        try {
            DB::affectingStatement($second, $bindings);
        } catch (QueryException $exception) {
            return $exception->getCode().' '.$exception->getMessage();
        }

        return 'committed';
    };

    return Sessions::run(['first' => $holder, 'second' => $waiter]);
}

/** A payment of Rp1.000.000 with its source guard. */
function guardedPayment(): int
{
    $payment = Payment::factory()->create(['amount' => '1000000.00']);
    DB::table('application_source_balances')->insert([
        'payment_id' => $payment->id, 'company_id' => $payment->company_id, 'client_organization_id' => $payment->client_organization_id, 'gross_amount' => $payment->amount,
    ]);

    return $payment->id;
}

/** An invoice of Rp1.110.000 gross with its receivable guard. */
function guardedInvoice(): int
{
    $invoice = Invoice::factory()->create();
    DB::table('invoice_balances')->insert(['invoice_id' => $invoice->id, 'company_id' => $invoice->company_id, 'gross_value' => '1110000.00', 'sales_value' => '1000000.00', 'output_tax_value' => '110000.00']);

    return $invoice->id;
}

it('refuses the second of two concurrent writes that would take more than a source\'s money or an invoice\'s gross (C-13; C-15; AX-19, AX-21, AX-23, AX-24)', function (Closure $prepare, string $first, string $second, string $constraint) {
    $outcomes = contendForGuard($first, $second, [$prepare()]);

    expect($outcomes['first'])->toBe('committed')
        ->and($outcomes['second'])->toStartWith(Refusal::CHECK)->toContain($constraint);
})->with([
    'two applications of one payment (C-13)' => [
        fn (): int => guardedPayment(),
        'update application_source_balances set invoice_applied_amount = invoice_applied_amount + 600000 where payment_id = ?',
        'update application_source_balances set invoice_applied_amount = invoice_applied_amount + 600000 where payment_id = ?',
        'application_source_balances_c13_capacity',
    ],
    'an application and a refund backed by the same payment (C-13; C-44)' => [
        fn (): int => guardedPayment(),
        'update application_source_balances set invoice_applied_amount = invoice_applied_amount + 600000 where payment_id = ?',
        'update application_source_balances set refund_backed_amount = refund_backed_amount + 600000 where payment_id = ?',
        'application_source_balances_c13_capacity',
    ],
    'two applications to one invoice (C-15)' => [
        fn (): int => guardedInvoice(),
        'update invoice_balances set applied_amount = applied_amount + 700000 where invoice_id = ?',
        'update invoice_balances set applied_amount = applied_amount + 700000 where invoice_id = ?',
        'invoice_balances_c15_conservation',
    ],
    'a fee settlement and a write-off of one invoice (C-15; C-22)' => [
        fn (): int => guardedInvoice(),
        'update invoice_balances set fee_settled_amount = fee_settled_amount + 700000 where invoice_id = ?',
        'update invoice_balances set written_off_amount = written_off_amount + 700000 where invoice_id = ?',
        'invoice_balances_c15_conservation',
    ],
]);

it('commits both of two concurrent writes that fit together', function () {
    $payment = guardedPayment();

    $outcomes = contendForGuard(
        'update application_source_balances set invoice_applied_amount = invoice_applied_amount + 400000 where payment_id = ?',
        'update application_source_balances set invoice_applied_amount = invoice_applied_amount + 600000 where payment_id = ?',
        [$payment],
    );

    expect($outcomes)->toBe(['first' => 'committed', 'second' => 'committed'])
        ->and(DB::table('application_source_balances')->where('payment_id', $payment)->value('capacity_amount'))->toBe('0.00');
});
