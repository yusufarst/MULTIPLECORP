<?php

use App\Modules\PUR\Actions\CancelPurchase;
use App\Modules\PUR\Models\Purchase;
use App\Support\BusinessDate;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;

// TL-05 (TASK-046; AX-08; AX-12; C-29; M-35; criterion 9) on real PostgreSQL sessions: a confirmation racing the
// purchase's cancellation meets it at LK-21 — the confirmation wins or the cancellation wins, never both —, and two
// partial confirmations of one line commit until the cap, the second REJECTED with the remainder. The reconciliation
// suite runs after every scenario (tests/Pest.php).

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
    $this->actor = DropshipWorld::actor($this->company, ['purchase.cancel', 'purchase.manage'])->id;
    $this->today = BusinessDate::today();
});

it('AX-12: a cancellation holding its locks first leaves the confirmation CONFLICT on the purchase\'s state, nothing confirmed (ST-02)', function () {
    $w = DropshipWorld::make($this->world);
    [$actor, $purchaseId, $today] = [$this->actor, $w['purchase']->id, $this->today];
    $proof = DropshipWorld::proof($this->company->id);

    $results = Sessions::run([
        'cancel' => static fn (): string => InvoiceWorld::hold(static fn (): string => InvoiceWorld::word(app(CancelPurchase::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), 'Batal', $today, false)), 'the cancel holds its locks'),
        'confirm' => static function () use ($actor, $w, $proof): string {
            Barrier::await('the cancel holds its locks');

            return DropshipWorld::confirm($actor, $w, '2', 'SJ-1', evidence: $proof);
        },
    ]);

    expect($results['cancel'])->toBe('COMMITTED')->and($results['confirm'])->toContain('ST-02')
        ->and(DB::table('dropship_confirmations')->count())->toBe(0)->and(DB::table('cost_attributions')->whereNotNull('dropship_confirmation_line_id')->count())->toBe(0);
});

it('AX-12: a confirmation holding its locks first leaves the cancellation REJECTED, the confirmation standing (C-29)', function () {
    $w = DropshipWorld::make($this->world);
    [$actor, $purchaseId, $today] = [$this->actor, $w['purchase']->id, $this->today];
    $proof = DropshipWorld::proof($this->company->id);

    $results = Sessions::run([
        'confirm' => static fn (): string => InvoiceWorld::hold(static fn (): string => DropshipWorld::confirm($actor, $w, '2', 'SJ-1', evidence: $proof), 'the confirmation holds its locks'),
        'cancel' => static function () use ($actor, $purchaseId, $today): string {
            Barrier::await('the confirmation holds its locks');

            return InvoiceWorld::word(app(CancelPurchase::class)->run((string) Str::uuid(), $actor, Purchase::query()->findOrFail($purchaseId), 'Batal', $today, false));
        },
    ]);

    expect($results['confirm'])->toBe('COMMITTED')->and($results['cancel'])->toContain('C-29')
        ->and(DB::table('purchases')->where('id', $purchaseId)->value('state'))->toBe('OPEN')
        ->and(DropshipWorld::demand($w['line'])['confirmed'])->toBe('2.000');
});

it('M-35: two partial confirmations of one line racing near its cap leave one committed and one REJECTED with the remainder (C-29; PX-03)', function () {
    $w = DropshipWorld::make($this->world, '20', '10');
    $actor = $this->actor;
    [$first, $second] = [DropshipWorld::proof($this->company->id), DropshipWorld::proof($this->company->id)];

    $results = Sessions::run([
        'first' => static fn (): string => InvoiceWorld::hold(static fn (): string => DropshipWorld::confirm($actor, $w, '6', 'SJ-1', evidence: $first), 'the first holds its locks'),
        'second' => static function () use ($actor, $w, $second): string {
            Barrier::await('the first holds its locks');

            return DropshipWorld::confirm($actor, $w, '6', 'SJ-2', evidence: $second);
        },
    ]);

    expect($results['first'])->toBe('COMMITTED')->and($results['second'])->toContain('C-29')
        ->and((string) DropshipWorld::guard($w['purchase_line'])->dropship_confirmed_net_qty_base)->toBe('6.000');
});
