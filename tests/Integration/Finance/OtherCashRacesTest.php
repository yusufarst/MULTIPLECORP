<?php

use App\Modules\CST\Queries\CostFigures;
use App\Modules\PUR\Models\Purchase;
use App\Support\BusinessDate;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\CostWorld;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;
use Tests\Support\OtherCashWorld;
use Tests\Support\PaymentWorld;

// TL-05 (TASK-054; NX-10; XL-5; BR-FIN-07 b; L-43; C-20; LR-10; FX-02; criteria 1, 2, 3, 6) on real PostgreSQL sessions: two transfers-in citing one transfer-out beyond its net amount leave the
// second REJECTED at its money-fact guard (LK-27); an expense and a purchase charge citing one source bill beyond its total leave the second REJECTED C-20 at the bill's guard (LK-29); a
// payment of a cancelled purchase meets the cancellation at its header (LK-21). The reconciliation suite runs after every scenario (tests/Pest.php).

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
    $this->company = $this->world->company;
});

it('lets two transfers-in citing one 500,000 transfer-out for 300,000 each commit one, the other REJECTED with what is left (XL-5; BR-FIN-07 b; criterion 6)', function () {
    $receiver = InvoiceWorld::make()->company->id;
    $transfer = OtherCashWorld::transferOut($this->company->id, '500000');
    $owner = Authorization::owner()->id;
    [$first, $second] = [PaymentWorld::account($receiver), PaymentWorld::account($receiver)];
    [$proofA, $proofB] = [PaymentWorld::proof($receiver, 'TRANSFER_PROOF')->id, PaymentWorld::proof($receiver, 'TRANSFER_PROOF')->id];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(OtherCashWorld::cashIn($owner, $receiver, 'INTERCOMPANY_TRANSFER_IN', '300000', $first, ['transfer' => $transfer], [$proofA])), 'the first holds the transfer'),
        'second' => static function () use ($owner, $receiver, $second, $transfer, $proofB): string {
            Barrier::await('the first holds the transfer');

            return InvoiceWorld::word(OtherCashWorld::cashIn($owner, $receiver, 'INTERCOMPANY_TRANSFER_IN', '300000', $second, ['transfer' => $transfer], [$proofB]));
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED BR-FIN-07'])
        ->and(DB::table('money_fact_balances')->where('disbursement_id', $transfer)->value('transfer_cited_amount'))->toBe('300000.00')
        ->and(DB::table('other_cash_receipts')->where('transfer_from_disbursement_id', $transfer)->count())->toBe(1);
});

it('lets an expense and a purchase charge citing one 100,000 bill for 60,000 each commit one, the other REJECTED C-20, in both orders (L-43; C-20; BD-17; criterion 3)', function (bool $expenseFirst) {
    $cost = CostWorld::make($this->company);
    $purchase = $cost->purchase();
    $line = $cost->line($purchase, '2', '200000.00');
    $bill = $cost->bill('100000.00');
    [$ids, $purchaseId, $lineId, $actor, $company] = [$cost->ids(), $purchase->id, $line->id, OtherCashWorld::actor($this->company->id), $this->company->id];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => $expenseFirst
            ? InvoiceWorld::word(OtherCashWorld::expense($actor, $company, null, '60000', $bill))
            : InvoiceWorld::word(CostWorld::resume($ids)->recordNew(Purchase::query()->findOrFail($purchaseId), '60000.00', [$lineId => '200000.00'], billId: $bill)), 'the first holds the bill'),
        'second' => static function () use ($expenseFirst, $ids, $purchaseId, $lineId, $actor, $company, $bill): string {
            Barrier::await('the first holds the bill');

            return $expenseFirst
                ? InvoiceWorld::word(CostWorld::resume($ids)->recordNew(Purchase::query()->findOrFail($purchaseId), '60000.00', [$lineId => '200000.00'], billId: $bill))
                : InvoiceWorld::word(OtherCashWorld::expense($actor, $company, null, '60000', $bill));
        },
    ]);

    expect($results)->toBe(['first' => 'COMMITTED', 'second' => 'REJECTED C-20'])
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $bill)->value('cited_amount'))->toBe('60000.00')
        ->and(DB::table('expenses')->where('source_bill_evidence_id', $bill)->count())->toBe($expenseFirst ? 1 : 0)
        ->and(DB::table('purchase_charges')->where('source_bill_evidence_id', $bill)->count())->toBe($expenseFirst ? 0 : 1);
})->with(['the expense first' => true, 'the charge first' => false]);

it('caps one bill\'s charges and expenses together, the rest left to cite, and counts a project expense in that project\'s cost once (L-43; C-20; CALC-09; CALC-11; criteria 2, 3)', function () {
    $cost = CostWorld::make($this->company);
    $purchase = $cost->purchase();
    $line = $cost->line($purchase, '2', '200000.00');
    $bill = $cost->bill('100000.00');
    $actor = OtherCashWorld::actor($this->company->id);
    $project = $this->world->project();

    $cost->expect($cost->recordNew($purchase, '40000.00', [$line->id => '200000.00'], billId: $bill));
    $above = OtherCashWorld::expense($actor, $this->company->id, $project->id, '60001', $bill);
    expect($above->code)->toBe('C-20')->and($above->state['uncited'])->toBe('60000.00')
        ->and(OtherCashWorld::committed(OtherCashWorld::expense($actor, $this->company->id, $project->id, '60000', $bill)))->toBeInt()
        ->and(DB::table('evidence_balances')->where('evidence_document_id', $bill)->value('cited_amount'))->toBe('100000.00')
        ->and($cost->recordNew($purchase, '1.00', [$line->id => '200000.00'], billId: $bill)->code)->toBe('C-20')
        ->and(OtherCashWorld::committed(OtherCashWorld::expense($actor, $this->company->id, null, '25000')))->toBeInt();

    $figures = app(CostFigures::class);
    expect($figures->project($actor, $project->id)['expenses'])->toBe('60000.00')
        ->and(collect($figures->companies($actor))->firstWhere('company_id', $this->company->id)['expenses'])->toBe('25000.00')
        // No Cash-Out comes of a cost: only a disbursement is one (BR-FIN-11; FS-04; criterion 1).
        ->and(DB::table('disbursements')->count())->toBe(0);
});

it('refuses a payment of a purchase cancelled first, met at its header, and pays an open one once (LR-10; BR-FIN-11; J-FIN-07; criterion 1)', function () {
    $cost = CostWorld::make($this->company);
    $open = $cost->purchase();
    $cancelled = $cost->purchase();
    DB::table('purchases')->where('id', $cancelled->id)->update(['state' => 'CANCELLED', 'cancelled_by' => $cost->actorId, 'cancelled_at' => now(), 'cancelled_reason' => 'Batal']);
    $actor = OtherCashWorld::actor($this->company->id);
    $account = PaymentWorld::account($this->company->id);

    expect(OtherCashWorld::disburse($actor, $this->company->id, 'PURCHASE_PAYMENT', '100000', $account, ['purchase' => $cancelled->id])->code)->toBe('ST-02');

    $command = (string) Str::uuid();
    $paid = OtherCashWorld::committed(OtherCashWorld::disburse($actor, $this->company->id, 'PURCHASE_PAYMENT', '100000', $account, ['purchase' => $open->id], [PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->id], $command));
    expect(DB::table('disbursements')->where('purchase_id', $open->id)->count())->toBe(1)
        ->and(DB::table('money_fact_balances')->where('disbursement_id', $paid)->value('fact_kind'))->toBe('OTHER_DISBURSEMENT')
        ->and(DB::table('disbursements')->where('id', $paid)->value('business_date'))->toStartWith(BusinessDate::today());
});
