<?php

use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;

// TL-05 (TASK-053; AX-23; AX-19; M-14; C-13; criterion 6) on real PostgreSQL sessions: a refund racing an application of the same credit meets it at the source (LK-26) — one commits, the
// other is REJECTED C-13 — in both orders; the source's capacity never above its real money. The reconciliation suite runs after every scenario (tests/Pest.php).

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
});

it('lets a refund and an application of the same 200,000 credit commit one, the other REJECTED C-13, in both orders (M-14; AX-23; criterion 6)', function (bool $refundFirst) {
    $invoice = $this->world->issued($this->project, '1000000');
    $target = $this->world->issued($this->project, '500000');
    $payment = PaymentWorld::paid($this->world->actorId, $invoice, '1200000', '1000000');
    DB::table('evidence_links')->insert(['evidence_document_id' => PaymentWorld::proof($payment->company_id)->id, 'evidence_scope' => 'COMPANY', 'company_id' => $payment->company_id, 'role' => 'PRIMARY_PROOF', 'payment_id' => $payment->id,
        'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->world->actorId]);
    $refunder = Authorization::admin([$payment->company_id], ['finance.view', 'refund.record'])->id;
    [$applier, $paymentId, $account, $proof] = [$this->world->actorId, $payment->id, PaymentWorld::account($payment->company_id), PaymentWorld::proof($payment->company_id, 'TRANSFER_PROOF')->id];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $refundFirst
            ? PaymentWorld::refundInCommand($refunder, $paymentId, '200000', $account, $proof)
            : InvoiceWorld::applyInCommand($applier, $target, $paymentId, '200000'), 'the first holds the source'),
        'second' => static function () use ($refundFirst, $refunder, $applier, $paymentId, $target, $account, $proof): string {
            Barrier::await('the first holds the source');

            return $refundFirst
                ? InvoiceWorld::applyInCommand($applier, $target, $paymentId, '200000')
                : PaymentWorld::refundInCommand($refunder, $paymentId, '200000', $account, $proof);
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-13'])
        ->and(DB::table('application_source_balances')->where('payment_id', $paymentId)->value('capacity_amount'))->toBe('0.00')
        ->and(DB::table('disbursements')->where('kind', 'REFUND')->count())->toBe($refundFirst ? 1 : 0);
})->with(['the refund first' => true, 'the application first' => false]);
