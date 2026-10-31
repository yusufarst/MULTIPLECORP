<?php

use App\Modules\ORG\Models\Company;
use App\Modules\PUR\Actions\CancelPurchase;
use App\Modules\PUR\Actions\ClosePurchaseRemainder;
use App\Modules\PUR\Actions\CorrectPurchaseCost;
use App\Modules\PUR\Actions\RecordPurchaseCharge;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use App\Support\BusinessDate;
use App\Support\Guards\Families;
use App\Support\Guards\Reconciler;
use App\Support\Money;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;

// TL-05 (TASK-037; AX-12; AX-29; AX-32; AX-34; M-07; M-15; M-36; C-20; C-29; challenge 2a) on real PostgreSQL sessions: concurrent PURCHASE numbers distinct and consecutive; two saves citing one bill against its cap; a closure racing a receipt both ways; a cancellation closing every line; a correction racing a draw; `Reconciler` finds nothing after each.

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
    $this->company = PurchaseWorld::numbered($this->world->company);
    $this->actor = PurchaseWorld::actor($this->company)->id;
    $this->supplier = PurchaseWorld::supplier()->id;
    $goods = Projects::product();
    $this->product = $goods['product']->id;
    $this->unit = $goods['base']->id;
    $this->today = BusinessDate::today();
});

function purchaseRaceDifferences(): array
{
    $found = [];

    foreach (['purchase_line_balances', 'purchase_charge_balances', 'purchase_charge_share_balances', 'lot_cost_balances', 'evidence_balances'] as $family) {
        $named = Families::named($family);

        if ($named !== null) {
            $found = [...$found, ...(new Reconciler)->differences(DB::connection(), $named)];
        }
    }

    return $found;
}

it('draws distinct consecutive numbers for concurrent saves, never reused (M-15; NM-08; criterion 2)', function () {
    $company = $this->company->id;
    $actor = $this->actor;
    $entry = PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $this->today);

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => PurchaseWorld::save($actor, $entry), 'the first holds its locks'),
        'second' => static function () use ($actor, $entry): string {
            Barrier::await('the first holds its locks');

            return PurchaseWorld::save($actor, $entry);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toBe('COMMITTED');
    $numbers = DB::table('purchases')->where('company_id', $company)->orderBy('sequence_no')->pluck('sequence_no')->all();
    expect($numbers)->toBe([1, 2])->and(purchaseRaceDifferences())->toBe([]);
});

it('leaves the second of two saves citing one bill REJECTED beyond its total (C-20; BD-17; criterion 6)', function () {
    $company = $this->company->id;
    $actor = $this->actor;
    $bill = (int) DB::table('evidence_documents')->where('public_id', PurchaseWorld::bill($company, '1000'))->value('id');
    $entry = PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $this->today, [['bill' => $bill, 'classification' => 'NON_ATTRIBUTABLE', 'amount' => '700', 'basis' => null, 'values' => []]]);

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => PurchaseWorld::save($actor, $entry), 'the first holds its locks'),
        'second' => static function () use ($actor, $entry): string {
            Barrier::await('the first holds its locks');

            return PurchaseWorld::save($actor, $entry);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-20')
        ->and(DB::table('purchases')->count())->toBe(1)->and(DB::table('evidence_balances')->where('evidence_document_id', $bill)->value('cited_amount'))->toBe('700.00')->and(purchaseRaceDifferences())->toBe([]);
});

it('M-36: a receipt holding its locks first leaves the closure refused against the new remainder, never beyond the ordered quantity (C-29; challenge 2a; criterion 12)', function () {
    $company = $this->company->id;
    $actor = $this->actor;
    $today = $this->today;
    PurchaseWorld::save($actor, PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $today));
    $purchase = PurchaseWorld::latest($company);
    $line = PurchaseWorld::lines($purchase)[0];
    $productId = $line->product_id;
    $lineId = $line->id;
    $purchaseId = $purchase->id;

    $results = Sessions::run([
        'receipt' => static fn (): string => InvoiceWorld::hold(static function () use ($company, $actor, $productId, $lineId): string {
            PurchaseWorld::cost(Company::query()->findOrFail($company), $actor, $productId)->receive(PurchaseLine::query()->findOrFail($lineId), '6');

            return 'COMMITTED';
        }, 'the receipt holds its locks'),
        'closure' => static function () use ($actor, $purchaseId, $lineId, $today): string {
            Barrier::await('the receipt holds its locks');

            return InvoiceWorld::word(app(ClosePurchaseRemainder::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), [$lineId => '6'], 'Tutup sisa', $today, false));
        },
    ]);
    $balance = DB::table('purchase_line_balances')->where('purchase_line_id', $lineId)->first();

    expect($results['receipt'])->toBe('COMMITTED')->and($results['closure'])->toContain('C-29')
        ->and($balance->normal_received_net_qty_base)->toBe('6.000')->and($balance->closed_qty_base)->toBe('0.000')->and(purchaseRaceDifferences())->toBe([]);
});

it('M-36: a closure holding its locks first leaves the receipt failing C-29 against the new remainder (criterion 12)', function () {
    $company = $this->company->id;
    $actor = $this->actor;
    $today = $this->today;
    PurchaseWorld::save($actor, PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $today));
    $purchase = PurchaseWorld::latest($company);
    $line = PurchaseWorld::lines($purchase)[0];
    $productId = $line->product_id;
    $lineId = $line->id;
    $purchaseId = $purchase->id;

    $results = Sessions::run([
        'closure' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(app(ClosePurchaseRemainder::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), [$lineId => '6'], 'Tutup sisa', $today, false)), 'the closure holds its locks'),
        'receipt' => static function () use ($company, $actor, $productId, $lineId): string {
            Barrier::await('the closure holds its locks');

            try {
                PurchaseWorld::cost(Company::query()->findOrFail($company), $actor, $productId)->receive(PurchaseLine::query()->findOrFail($lineId), '6');

                return 'COMMITTED';
            } catch (Throwable $refused) {
                return $refused->getMessage();
            }
        },
    ]);
    $balance = DB::table('purchase_line_balances')->where('purchase_line_id', $lineId)->first();

    expect($results['closure'])->toBe('COMMITTED')->and($results['receipt'])->toContain('C-29')
        ->and($balance->normal_received_net_qty_base)->toBe('0.000')->and($balance->closed_qty_base)->toBe('6.000')->and(purchaseRaceDifferences())->toBe([]);
});

it('cancels racing a receipt: one commits, every line closed to its ordered quantity when the cancellation wins (AX-12; C-29; criteria 11, 12)', function () {
    $company = $this->company->id;
    $actor = $this->actor;
    $today = $this->today;
    PurchaseWorld::save($actor, PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $today));
    $purchase = PurchaseWorld::latest($company);
    $line = PurchaseWorld::lines($purchase)[0];
    $productId = $line->product_id;
    $lineId = $line->id;
    $purchaseId = $purchase->id;

    $results = Sessions::run([
        'cancel' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(app(CancelPurchase::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), 'Batal', $today, false)), 'the cancel holds its locks'),
        'receipt' => static function () use ($company, $actor, $productId, $lineId): string {
            Barrier::await('the cancel holds its locks');

            try {
                PurchaseWorld::cost(Company::query()->findOrFail($company), $actor, $productId)->receive(PurchaseLine::query()->findOrFail($lineId), '1');

                return 'COMMITTED';
            } catch (Throwable $refused) {
                return $refused->getMessage();
            }
        },
    ]);

    expect($results['cancel'])->toBe('COMMITTED')->and($results['receipt'])->toContain('C-29')
        ->and(DB::table('purchases')->where('id', $purchaseId)->value('state'))->toBe('CANCELLED')
        ->and(DB::table('purchase_line_balances')->where('purchase_line_id', $lineId)->value('closed_qty_base'))->toBe('10.000')->and(purchaseRaceDifferences())->toBe([]);
});

it('M-07: a correction sees a draw that committed first and re-attributes it, Σ attributed = the corrected cost (AX-34; C-57; challenge 2b; criterion 14)', function () {
    $company = $this->company->id;
    $actor = $this->actor;
    $today = $this->today;
    PurchaseWorld::save($actor, PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $today));
    $purchase = PurchaseWorld::latest($company);
    $line = PurchaseWorld::lines($purchase)[0];
    $world = PurchaseWorld::cost($this->company, $actor, $line->product_id);
    $lot = $world->receive($line, '10');
    $project = $this->world->project(['SERVICE'], '1');
    $world->dispatch($lot, '4', $project);
    $purchaseId = $purchase->id;
    $lineId = $line->id;

    $outcome = app(CorrectPurchaseCost::class)->line((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), $lineId, 'PRICE_CORRECTION', '12000', 'Harga keliru', $today, false);
    expect($outcome->kind)->toBe('COMMITTED', json_encode($outcome->state));

    $held = (string) DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
    $drawn = (string) DB::table('cost_attributions')->whereIn('stock_consumption_id', DB::table('stock_consumptions')->where('lot_id', $lot)->pluck('id'))->sum('amount');
    expect(Money::of($held)->plus(Money::of($drawn))->toString())->toBe('12000.00')->and(purchaseRaceDifferences())->toBe([]);
});

it('holds the lot a receipt created while a late charge waited: the charge restarts with its locks and posts on it (AX-29; §5 steps 3–4; LK-23; ER-06)', function () {
    $company = $this->company->id;
    $actor = $this->actor;
    $today = $this->today;
    PurchaseWorld::save($actor, PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $today));
    $purchase = PurchaseWorld::latest($company);
    $line = PurchaseWorld::lines($purchase)[0];
    $productId = $line->product_id;
    $lineId = $line->id;
    $purchaseId = $purchase->id;
    $bill = (int) DB::table('evidence_documents')->where('public_id', PurchaseWorld::bill($company, '1000'))->value('id');

    $results = Sessions::run([
        'receipt' => static fn (): string => InvoiceWorld::hold(static function () use ($company, $actor, $productId, $lineId): string {
            PurchaseWorld::cost(Company::query()->findOrFail($company), $actor, $productId)->receive(PurchaseLine::query()->findOrFail($lineId), '10');

            return 'COMMITTED';
        }, 'the receipt holds its locks'),
        'charge' => static function () use ($actor, $purchaseId, $bill, $today): string {
            Barrier::await('the receipt holds its locks');

            return InvoiceWorld::word(app(RecordPurchaseCharge::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), ['bill' => $bill, 'classification' => 'ATTRIBUTABLE', 'amount' => '400', 'basis' => 'LINE_VALUE', 'values' => []], $today, false, ['run' => (string) Str::uuid()]));
        },
    ]);
    $charge = (int) DB::table('purchase_charges')->where('purchase_id', $purchaseId)->value('id');
    $lot = (int) DB::table('inventory_lots as i')->join('receiving_lines as r', 'r.id', '=', 'i.receiving_line_id')->where('r.purchase_line_id', $lineId)->value('i.id');

    expect($results['receipt'])->toBe('COMMITTED')->and($results['charge'])->toBe('COMMITTED')
        ->and(DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->value('allocated_amount'))->toBe('400.00')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('10400.00')->and(purchaseRaceDifferences())->toBe([]);
});

it('keeps PurchasePosting the only writer of the line guard, checked by the reconciliation (TL-05; ARCHITECTURE §7)', function () {
    $company = $this->company->id;
    PurchaseWorld::save($this->actor, PurchaseWorld::entry($company, $this->supplier, $this->product, $this->unit, $this->today));
    $purchase = PurchaseWorld::latest($company);
    $line = PurchaseWorld::lines($purchase)[0];

    expect(DB::table('purchase_line_balances')->where('purchase_line_id', $line->id)->value('ordered_qty_base'))->toBe('10.000')->and(purchaseRaceDifferences())->toBe([]);
});
