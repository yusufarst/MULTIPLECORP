<?php

use App\Modules\FIN\Actions\ApplyPayment;
use App\Modules\FIN\Actions\RecordContra;
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
use Tests\Support\SettlementWorld;

// TL-05 (TASK-052; AX-21; AX-33; M-14; M-37; BD-08; C-18; architecture challenges 6d, 20a; HO-12) on real PostgreSQL sessions: two settlements citing one evidence from different payments; a settlement racing the invoice's void; a refund's contra racing an application that resolves its
// shortfall; two contras of one settlement; `Reconciler` finds nothing after each.

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
    $this->company = $this->world->company->id;
    $this->actor = Authorization::admin([$this->company], array_values(array_unique([...InvoiceWorld::CAPABILITIES, 'settlement.record', 'finance.contra', 'evidence.view', 'payment.record', 'payment.apply'])))->id;
    $this->today = BusinessDate::today();
});

function settlementRaceDifferences(): array
{
    $found = [];

    foreach (['invoice_balances', 'application_source_balances', 'evidence_balances', 'money_fact_balances', 'correction_cases'] as $family) {
        $found = [...$found, ...(new Reconciler)->differences(DB::connection(), Families::named($family) ?? throw new RuntimeException($family))];
    }

    return $found;
}

it('leaves the second of two settlements citing one evidence REJECTED beyond its proven amount (BD-08; C-18; L-45; challenge 6d)', function () {
    $actor = $this->actor;
    $first = $this->world->issued($this->project, '1000000');
    $second = $this->world->issued($this->project, '1000000');
    $paymentOne = PaymentWorld::paid($actor, $first, '900000', '900000')->id;
    $paymentTwo = PaymentWorld::paid($actor, $second, '900000', '900000')->id;
    SettlementWorld::claimFee($paymentOne, '30000');
    SettlementWorld::claimFee($paymentTwo, '30000');
    $evidence = SettlementWorld::evidence($this->company, 'FEE_ADVICE', '30000')->id;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(SettlementWorld::fee($actor, $first, $paymentOne, '30000', $evidence)), 'the first holds its locks'),
        'second' => static function () use ($actor, $second, $paymentTwo, $evidence): string {
            Barrier::await('the first holds its locks');

            return InvoiceWorld::word(SettlementWorld::fee($actor, $second, $paymentTwo, '30000', $evidence));
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-18')->and(DB::table('deduction_settlements')->count())->toBe(1)
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $evidence)->value('cited_amount'))->toBe('30000.00')->and(settlementRaceDifferences())->toBe([]);
});

it('commits one settlement of one key from two sessions, the other a CONFLICT (BD-08)', function () {
    $actor = $this->actor;
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($actor, $invoice, '900000', '900000')->id;
    SettlementWorld::claimFee($payment, '60000');
    $evidence = SettlementWorld::evidence($this->company, 'FEE_ADVICE', '60000')->id;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(SettlementWorld::fee($actor, $invoice, $payment, '30000', $evidence)), 'the first holds its locks'),
        'second' => static function () use ($actor, $invoice, $payment, $evidence): string {
            Barrier::await('the first holds its locks');

            return InvoiceWorld::word(SettlementWorld::fee($actor, $invoice, $payment, '30000', $evidence));
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('CONFLICT')->and(DB::table('deduction_settlements')->count())->toBe(1)->and(settlementRaceDifferences())->toBe([]);
});

it('meets the invoice\'s void at LK-25: a settlement after it refused, the outstanding never negative (M-37; C-15)', function () {
    $actor = $this->actor;
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($actor, $invoice, '100000')->id;
    SettlementWorld::claimFee($payment, '30000');
    $evidence = SettlementWorld::evidence($this->company, 'FEE_ADVICE', '30000')->id;

    $results = Sessions::run([
        'void' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::voidInCommand($actor, $invoice), 'the void holds its locks'),
        'settlement' => static function () use ($actor, $invoice, $payment, $evidence): string {
            Barrier::await('the void holds its locks');

            return InvoiceWorld::word(SettlementWorld::fee($actor, $invoice, $payment, '30000', $evidence));
        },
    ]);

    expect($results['void'])->toBe('COMMITTED')->and($results['settlement'])->toContain('C-15')->and(DB::table('deduction_settlements')->count())->toBe(0)->and(settlementRaceDifferences())->toBe([]);
});

it('contras a settlement once from two sessions (C-60; AX-33)', function () {
    $actor = $this->actor;
    $company = $this->company;
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($actor, $invoice, '900000', '900000')->id;
    SettlementWorld::claimFee($payment, '30000');
    $id = SettlementWorld::fee($actor, $invoice, $payment, '30000', SettlementWorld::evidence($company, 'FEE_ADVICE', '30000')->id)->resultId;

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(SettlementWorld::contra($actor, $company, RecordContra::SETTLEMENT, $id)), 'the first holds its locks'),
        'second' => static function () use ($actor, $company, $id): string {
            Barrier::await('the first holds its locks');

            return InvoiceWorld::word(SettlementWorld::contra($actor, $company, RecordContra::SETTLEMENT, $id));
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('CONFLICT')->and(DB::table('financial_contras')->where('deduction_settlement_id', $id)->count())->toBe(1)->and(settlementRaceDifferences())->toBe([]);
});

it('keeps a refund\'s contra and an application resolving its shortfall within the real money, both orders (M-14; C-44; L-46)', function () {
    $actor = $this->actor;
    $company = $this->company;
    $invoice = $this->world->issued($this->project, '1000000');
    $payment = PaymentWorld::paid($actor, $invoice, '200000');
    $refund = PaymentWorld::refund($payment->id, '200000');
    expect(PaymentWorld::contraInCommand($actor, $payment->id, '50000', [$refund['application'] => ['action' => 'SHORTFALL', 'amount' => '50000']]))->toBe('COMMITTED');
    $fresh = PaymentWorld::paid($actor, $invoice, '50000')->id;
    $refundId = $refund['refund'];
    $client = $payment->client_organization_id;
    $ended = (int) DB::table('payment_applications')->where('refund_disbursement_id', $refundId)->where('end_reason', 'SOURCE_CONTRA')->value('id');

    $results = Sessions::run([
        'contra' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(SettlementWorld::contra($actor, $company, RecordContra::DISBURSEMENT, $refundId, '50000')), 'the contra holds its locks'),
        'application' => static function () use ($actor, $fresh, $ended, $company, $client): string {
            Barrier::await('the contra holds its locks');

            return InvoiceWorld::word(app(ApplyPayment::class)->run((string) Str::uuid(), $actor, ['payment' => $fresh, 'credit' => null, 'company' => $company, 'client' => $client, 'name' => 'x'], [], [$ended => '50000'], BusinessDate::today(), false, ['ended' => $ended]));
        },
    ]);

    expect($results['contra'])->toBe('COMMITTED')->and($results['application'])->toContain('C-44');
    $fact = DB::table('money_fact_balances')->where('disbursement_id', $refundId)->first();
    expect($fact->shortfall_amount)->toBe('0.00')->and($fact->contra_amount)->toBe('50000.00')
        ->and(DB::table('correction_cases')->where('id', $fact->residual_case_id)->value('unbacked_refund_amount'))->toBe('0.00')->and(settlementRaceDifferences())->toBe([]);
});
