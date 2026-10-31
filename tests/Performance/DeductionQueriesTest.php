<?php

use App\Modules\FIN\Actions\RecordContra;
use App\Modules\FIN\Queries\DeductionList;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\SettlementWorld;

// TL-15 (TASK-052; QB-12; QB-13; QB-14; PFM-01; PFM-02; PFM-07; PFM-20): PG-047 reads its page in a bounded number of statements, the same for one row and a full page, QS-13 one bounded statement; AX-21 within the reference command's budget; AX-33 proportional to its rows.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company->id;
    $this->actor = User::query()->findOrFail(Authorization::admin([$this->company], array_values(array_unique([...InvoiceWorld::CAPABILITIES, 'settlement.record', 'finance.contra', 'evidence.view', 'payment.record', 'payment.apply'])))->id);
    $this->project = $this->world->project(['SERVICE'], '1000');
    Queue::fake();
});

afterEach(fn () => @unlink($this->hotFile));

function deductionStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function deductionSettled(object $test, string $amount = '1000'): int
{
    $invoice = $test->world->issued($test->project, '100000');
    $payment = PaymentWorld::paid($test->actor->id, $invoice, '50000', '50000');
    SettlementWorld::claimFee($payment->id, $amount);

    return SettlementWorld::fee($test->actor->id, $invoice, $payment->id, $amount, SettlementWorld::evidence($test->company, 'FEE_ADVICE', $amount)->id)->resultId;
}

it('reads PG-047 in at most four statements, the same for one row and many (QB-12; PFM-01; PFM-02; PFM-07)', function () {
    deductionSettled($this);
    $query = app(DeductionList::class);
    $screen = fn (): array => $query->screen($this->actor->id, null, 'semua', null, null);
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $one = deductionStatements($screen);

    foreach (range(1, 8) as $step) {
        deductionSettled($this);
    }

    $many = deductionStatements($screen);

    expect($many)->toBe($one)->and($many)->toBeLessThanOrEqual(4);
});

it('records a settlement within the reference command\'s budget (QB-13; PFM-20; AX-21)', function () {
    $invoice = $this->world->issued($this->project, '100000');
    $payment = PaymentWorld::paid($this->actor->id, $invoice, '50000', '50000');
    SettlementWorld::claimFee($payment->id, '1000');
    $evidence = SettlementWorld::evidence($this->company, 'FEE_ADVICE', '1000')->id;

    $count = deductionStatements(fn () => expect(SettlementWorld::fee($this->actor->id, $invoice, $payment->id, '1000', $evidence)->kind)->toBe('COMMITTED'));

    expect($count)->toBeLessThanOrEqual(40);
});

it('contras a settlement proportionally to its rows (QB-14; PFM-20; AX-33)', function () {
    $id = deductionSettled($this);

    $count = deductionStatements(fn () => expect(SettlementWorld::contra($this->actor->id, $this->company, RecordContra::SETTLEMENT, $id)->kind)->toBe('COMMITTED'));

    expect($count)->toBeLessThanOrEqual(50);
});
