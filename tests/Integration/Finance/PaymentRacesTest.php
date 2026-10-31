<?php

use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Models\Invoice;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;

// TL-05 (TASK-051; AX-18–AX-20; NX-08; M-08–M-14, M-28, M-37; BD-02; BD-03; C-13–C-15; C-19; C-44; architecture challenges 6a–6c, 5b; HO-12) on real PostgreSQL sessions, both orders, with synthetic refunds until TASK-053 exists: one `command_id` twice, one bank line beyond its amount,
// one receipt twice, two applications past a payment or an invoice, a contra racing an application, reallocations in opposite orders, a void racing an application. `ReconcileGuards` reports no difference after each.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();

    // The truncation between tests removes the seeded catalogue.
    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->world = InvoiceWorld::make();
    $this->project = $this->world->project();
    $this->company = $this->world->company->id;
    $this->client = $this->project->client_organization_id;
    $this->actor = $this->world->actorId;
    $this->account = PaymentWorld::account($this->company);
    $this->today = BusinessDate::today();
});

function paymentRaceDifferences(): array
{
    $found = [];

    foreach (['invoice_balances', 'application_source_balances', 'evidence_balances', 'money_fact_balances', 'correction_cases'] as $family) {
        $found = [...$found, ...(new Reconciler)->differences(DB::connection(), Families::named($family) ?? throw new RuntimeException($family))];
    }

    return $found;
}

it('records one payment for one command_id sent at once, each session answered COMMITTED (M-08; CI-04; CI-05; criterion 5)', function () {
    $actor = $this->actor;
    $company = $this->company;
    $client = $this->client;
    $commandId = (string) Str::uuid();

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(PaymentWorld::record($actor, $company, $client, '300000', [], $commandId)), 'the first holds its identity'),
        'second' => static function () use ($actor, $company, $client, $commandId): string {
            Barrier::await('the first holds its identity');

            return InvoiceWorld::word(PaymentWorld::record($actor, $company, $client, '300000', [], $commandId));
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'COMMITTED'])->and(DB::table('payments')->count())->toBe(1)->and(paymentRaceDifferences())->toBe([]);
});

it('answers the same command_id with other content a CONFLICT, nothing new committed (M-09)', function () {
    $commandId = (string) Str::uuid();
    expect(InvoiceWorld::word(PaymentWorld::record($this->actor, $this->company, $this->client, '300000', [], $commandId)))->toBe('COMMITTED');
    expect(PaymentWorld::record($this->actor, $this->company, $this->client, '300001', [], $commandId)->kind)->toBe('CONFLICT')->and(DB::table('payments')->count())->toBe(1);
});

it('leaves the second of two ids citing one line beyond it REJECTED C-19, the line\'s guard exact (M-11; BD-03; challenge 6c; criterion 4)', function () {
    $line = PaymentWorld::line($this->company, $this->account, '1000000');
    $actor = $this->actor;
    $company = $this->company;
    $client = $this->client;
    $account = $this->account;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(PaymentWorld::record($actor, $company, $client, '700000', ['accountId' => $account, 'lineId' => $line])), 'the first holds the line'),
        'second' => static function () use ($actor, $company, $client, $account, $line): string {
            Barrier::await('the first holds the line');

            return InvoiceWorld::word(PaymentWorld::record($actor, $company, $client, '400000', ['accountId' => $account, 'lineId' => $line]));
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-19'])
        ->and(DB::table('evidence_balances')->where('bank_statement_line_id', $line)->value('cited_amount'))->toBe('700000.00')->and(paymentRaceDifferences())->toBe([]);
});

it('lets one of two Admins record one real payment, no line: the second REJECTED L-22 with the match, then only by the override naming it (M-12; BD-02 under LK-07; criterion 4)', function () {
    $actor = $this->actor;
    $company = $this->company;
    $client = $this->client;
    $account = $this->account;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(PaymentWorld::record($actor, $company, $client, '500000', ['accountId' => $account, 'reference' => 'A'])), 'the first holds the account'),
        'second' => static function () use ($actor, $company, $client, $account): string {
            Barrier::await('the first holds the account');

            return InvoiceWorld::word(PaymentWorld::record($actor, $company, $client, '500000', ['accountId' => $account, 'reference' => 'B']));
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED L-22'])->and(DB::table('payments')->count())->toBe(1);

    $match = (string) DB::table('payments')->value('public_id');
    $override = Authorization::admin([$this->company], ['finance.view', 'payment.record', 'warnings.override'])->id;
    expect(InvoiceWorld::word(PaymentWorld::record($override, $this->company, $this->client, '500000', ['accountId' => $this->account, 'acknowledged' => [(string) Str::uuid()], 'overrideReason' => 'Dua transfer'])))->toBe('REJECTED L-22')
        ->and(InvoiceWorld::word(PaymentWorld::record($override, $this->company, $this->client, '500000', ['accountId' => $this->account, 'acknowledged' => [$match], 'overrideReason' => 'Dua transfer'])))->toBe('COMMITTED')
        ->and(DB::table('payments')->count())->toBe(2)->and(paymentRaceDifferences())->toBe([]);
});

it('never lets two applications of one payment exceed its money: the second REJECTED with the capacity (challenge 6a; C-13; AX-19; criterion 2)', function () {
    $first = $this->world->issued($this->project, '1000000');
    $second = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $first, '500000')->id;
    $actor = $this->actor;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::applyInCommand($actor, $payment, $first, '400000'), 'the first holds the source'),
        'second' => static function () use ($actor, $payment, $second): string {
            Barrier::await('the first holds the source');

            return PaymentWorld::applyInCommand($actor, $payment, $second, '400000');
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-13'])
        ->and(DB::table('application_source_balances')->where('payment_id', $payment)->value('capacity_amount'))->toBe('100000.00')->and(paymentRaceDifferences())->toBe([]);
});

it('never lets two payments take an invoice below nothing: the second REJECTED with what remains (C-15; AX-19; criterion 2)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $one = PaymentWorld::paid($this->actor, $invoice, '700000')->id;
    $two = PaymentWorld::paid($this->actor, $invoice, '700000')->id;
    $actor = $this->actor;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::applyInCommand($actor, $one, $invoice, '700000'), 'the first holds the invoice'),
        'second' => static function () use ($actor, $two, $invoice): string {
            Barrier::await('the first holds the invoice');

            return PaymentWorld::applyInCommand($actor, $two, $invoice, '700000');
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-15'])
        ->and(DB::table('invoice_balances')->where('invoice_id', $invoice)->value('outstanding'))->toBe('300000.00')->and(paymentRaceDifferences())->toBe([]);
});

it('answers a contra after a new application a CONFLICT with the current list; the first order (M-14; ST-07)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $invoice, '500000')->id;
    $actor = $this->actor;

    $results = Sessions::run([
        'application' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::applyInCommand($actor, $payment, $invoice, '500000'), 'the application holds the source'),
        'contra' => static function () use ($actor, $payment): string {
            Barrier::await('the application holds the source');

            return PaymentWorld::contraInCommand($actor, $payment, '500000');
        },
    ]);

    expect($results)->toBe(['application' => 'COMMITTED', 'contra' => 'CONFLICT ST-07'])
        ->and(DB::table('financial_contras')->count())->toBe(0)->and(paymentRaceDifferences())->toBe([]);
});

it('commits an application after a contra only within what is left; the other order (M-14; C-13)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $invoice, '500000')->id;
    $actor = $this->actor;

    $results = Sessions::run([
        'contra' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::contraInCommand($actor, $payment, '500000'), 'the contra holds the source'),
        'application' => static function () use ($actor, $payment, $invoice): string {
            Barrier::await('the contra holds the source');

            return PaymentWorld::applyInCommand($actor, $payment, $invoice, '1');
        },
    ]);

    expect($results)->toBe(['contra' => 'COMMITTED', 'application' => 'REJECTED C-13'])
        ->and(DB::table('application_source_balances')->where('payment_id', $payment)->value('net_amount'))->toBe('0.00')->and(paymentRaceDifferences())->toBe([]);
});

it('commits the Rp1 contra at a real commit keeping the capacity at nothing, and refuses the attack (FX-04; C-44; M-14; criterion 7)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $invoice, '1200000', '1000000')->id;
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment)->where('target_kind', 'INVOICE')->value('id');
    $refund = PaymentWorld::refund($payment, '200000');

    expect(PaymentWorld::contraInCommand($this->actor, $payment, '1', [$application => ['action' => ContraEntry::KEEP, 'amount' => null], $refund['application'] => ['action' => ContraEntry::SHORTFALL, 'amount' => null]]))->toBe('REJECTED C-44')
        ->and(PaymentWorld::contraInCommand($this->actor, $payment, '1', [$application => ['action' => ContraEntry::RELEASE, 'amount' => '1'], $refund['application'] => ['action' => ContraEntry::KEEP, 'amount' => null]]))->toBe('COMMITTED')
        ->and(DB::table('application_source_balances')->where('payment_id', $payment)->value('capacity_amount'))->toBe('0.00')
        ->and(PaymentWorld::applyInCommand($this->actor, $payment, $invoice, '1'))->toBe('REJECTED C-13')
        ->and(paymentRaceDifferences())->toBe([]);
});

it('lets the shortfall\'s resolving application and the case agree at a real commit (L-46; M-14; C-46)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $invoice, '200000')->id;
    $refund = PaymentWorld::refund($payment, '200000');

    expect(PaymentWorld::contraInCommand($this->actor, $payment, '100000', [$refund['application'] => ['action' => ContraEntry::SHORTFALL, 'amount' => '100000']]))->toBe('COMMITTED');
    $case = (int) DB::table('money_fact_balances')->where('disbursement_id', $refund['refund'])->value('residual_case_id');
    expect(DB::table('correction_cases')->where('id', $case)->value('unbacked_refund_amount'))->toBe('100000.00')->and(paymentRaceDifferences())->toBe([]);

    $later = PaymentWorld::record($this->actor, $this->company, $this->client, '100000', ['resolves' => [$refund['application'] => '100000']]);
    expect($later->kind)->toBe('COMMITTED')
        ->and(DB::table('correction_cases')->where('id', $case)->value('unbacked_refund_amount'))->toBe('0.00')
        ->and(DB::table('money_fact_balances')->where('disbursement_id', $refund['refund'])->value('shortfall_amount'))->toBe('0.00')
        ->and(paymentRaceDifferences())->toBe([]);
});

it('completes two reallocations between the same invoices in opposite orders, each once (M-28; LR-01)', function () {
    $a = $this->world->issued($this->project, '1000000');
    $b = $this->world->issued($this->project, '1000000');
    $one = PaymentWorld::paid($this->actor, $a, '300000', '300000')->id;
    $two = PaymentWorld::paid($this->actor, $b, '300000', '300000')->id;
    $fromA = (int) DB::table('payment_applications')->where('payment_id', $one)->value('id');
    $fromB = (int) DB::table('payment_applications')->where('payment_id', $two)->value('id');
    $actor = $this->actor;

    $results = Sessions::run([
        'a to b' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::reallocateInCommand($actor, $one, $fromA, $b, '300000'), 'the first move holds the invoices'),
        'b to a' => static function () use ($actor, $two, $fromB, $a): string {
            Barrier::await('the first move holds the invoices');

            return PaymentWorld::reallocateInCommand($actor, $two, $fromB, $a, '300000');
        },
    ]);

    expect($results)->toBe(['a to b' => 'COMMITTED', 'b to a' => 'COMMITTED'])
        ->and(DB::table('invoice_balances')->where('invoice_id', $a)->value('applied_amount'))->toBe('300000.00')->and(DB::table('invoice_balances')->where('invoice_id', $b)->value('applied_amount'))->toBe('300000.00')->and(paymentRaceDifferences())->toBe([]);
});

it('flips an application once under two moves at once, the second a CONFLICT (AX-19; DATABASE §11.3)', function () {
    $a = $this->world->issued($this->project, '1000000');
    $b = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $a, '300000', '300000')->id;
    $application = (int) DB::table('payment_applications')->where('payment_id', $payment)->value('id');
    $actor = $this->actor;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::reallocateInCommand($actor, $payment, $application, $b, '300000'), 'the first holds the application'),
        'second' => static function () use ($actor, $payment, $application, $b): string {
            Barrier::await('the first holds the application');

            return PaymentWorld::reallocateInCommand($actor, $payment, $application, $b, '300000');
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toStartWith('CONFLICT')
        ->and(DB::table('invoice_balances')->where('invoice_id', $b)->value('applied_amount'))->toBe('300000.00')->and(paymentRaceDifferences())->toBe([]);
});

it('answers an invoice void after a new application a CONFLICT (M-37; ST-07; C-16)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $invoice, '300000')->id;
    $actor = $this->actor;

    $results = Sessions::run([
        'application' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::applyInCommand($actor, $payment, $invoice, '300000'), 'the application holds the invoice'),
        'void' => static function () use ($actor, $invoice): string {
            Barrier::await('the application holds the invoice');

            return InvoiceWorld::voidInCommand($actor, $invoice);
        },
    ]);

    expect($results)->toBe(['application' => 'COMMITTED', 'void' => 'CONFLICT ST-07'])->and(Invoice::query()->findOrFail($invoice)->state)->toBe('ISSUED')->and(paymentRaceDifferences())->toBe([]);
});

it('refuses an application after the void with what is left: nothing (M-37; C-15)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($this->actor, $invoice, '300000')->id;
    $actor = $this->actor;

    $results = Sessions::run([
        'void' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::voidInCommand($actor, $invoice), 'the void holds the invoice'),
        'application' => static function () use ($actor, $payment, $invoice): string {
            Barrier::await('the void holds the invoice');

            return PaymentWorld::applyInCommand($actor, $payment, $invoice, '300000');
        },
    ]);

    expect($results)->toBe(['void' => 'COMMITTED', 'application' => 'REJECTED C-15'])->and(DB::table('application_source_balances')->where('payment_id', $payment)->value('capacity_amount'))->toBe('300000.00')->and(paymentRaceDifferences())->toBe([]);
});

it('moves both lines\' guards at a real commit, a racing payment seeing what the correction left (NX-08; C-19; criterion 10)', function () {
    $old = PaymentWorld::line($this->company, $this->account, '500000', 'TRX-1');
    $new = PaymentWorld::line($this->company, $this->account, '500000', 'TRX-2');
    $payment = PaymentWorld::record($this->actor, $this->company, $this->client, '500000', ['accountId' => $this->account, 'lineId' => $old])->resultId;
    $actor = Authorization::admin([$this->company], [...InvoiceWorld::CAPABILITIES, 'cash.correct'])->id;
    $company = $this->company;
    $client = $this->client;
    $account = $this->account;

    $results = Sessions::run([
        'correction' => static fn (): string => InvoiceWorld::hold(static fn (): string => PaymentWorld::citeInCommand($actor, $payment, $new), 'the correction holds the lines'),
        'payment' => static function () use ($actor, $company, $client, $account, $new): string {
            Barrier::await('the correction holds the lines');

            return InvoiceWorld::word(PaymentWorld::record($actor, $company, $client, '1', ['accountId' => $account, 'lineId' => $new]));
        },
    ]);

    expect($results)->toBe(['correction' => 'COMMITTED', 'payment' => 'REJECTED C-19'])
        ->and(DB::table('evidence_balances')->where('bank_statement_line_id', $old)->value('cited_amount'))->toBe('0.00')->and(DB::table('evidence_balances')->where('bank_statement_line_id', $new)->value('cited_amount'))->toBe('500000.00')->and(paymentRaceDifferences())->toBe([]);
});
