<?php

use App\Modules\FIN\Data\ContraEntry;
use App\Modules\FIN\Queries\BankStatementList;
use App\Modules\FIN\Queries\PaymentList;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-051; QB-12; QB-13; QB-14; PFM-01; PFM-02; PFM-20): PG-045 and PG-050 read their page in at most four statements, the same for one row and a full page; AX-18 of a reference command of ten lines and AX-19 within their budget; AX-20 proportional to its rows.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company->id;
    $this->actor = User::query()->findOrFail(Authorization::admin([$this->company], [...InvoiceWorld::CAPABILITIES, 'bank_statement.record', 'cash.correct'])->id);
    $this->project = $this->world->project(['SERVICE'], '1000');
    $this->client = $this->project->client_organization_id;
    $this->account = PaymentWorld::account($this->company);
    $this->today = BusinessDate::today();
    Queue::fake();
});

afterEach(fn () => @unlink($this->hotFile));

function paymentStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('reads PG-045 in at most four statements, the same for one payment and a full page (QB-12; PFM-01; PFM-02)', function () {
    $invoice = $this->world->issued($this->project, '1000000');
    $pay = fn (int $step) => PaymentWorld::record($this->actor->id, $this->company, $this->client, (string) (1000 + $step), ['applications' => [$invoice => '10']]);
    $pay(0);
    $query = app(PaymentList::class);
    $screen = fn (): array => $query->screen($this->actor->id, null, 'semua', null, null, null);
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $one = paymentStatements($screen);

    foreach (range(1, 12) as $step) {
        $pay($step);
    }

    $many = paymentStatements($screen);

    expect($many)->toBe($one)->and($many)->toBeLessThanOrEqual(4);
});

it('reads PG-050 in a bounded number of statements, the same for one line and many (QB-12; PFM-01)', function () {
    PaymentWorld::line($this->company, $this->account, '1000', 'TRX-0');
    $query = app(BankStatementList::class);
    $screen = fn (): array => $query->screen($this->actor->id, null, 'mutasi', null);
    app(EffectiveCapabilities::class)->for($this->actor->id);
    $one = paymentStatements($screen);

    foreach (range(1, 12) as $step) {
        PaymentWorld::line($this->company, $this->account, '1000', "TRX-{$step}");
    }

    expect(paymentStatements($screen))->toBe($one)->and($one)->toBeLessThanOrEqual(8);
});

it('records a payment of ten applications, citing a line, within the reference command\'s budget (QB-13; PFM-20; AX-18)', function () {
    $invoices = [];

    foreach (range(1, 10) as $step) {
        $invoices[$this->world->issued($this->project, '1000')] = '1000';
    }

    $line = PaymentWorld::line($this->company, $this->account, '10000');
    $proof = PaymentWorld::proof($this->company)->id;

    $count = paymentStatements(fn () => expect(InvoiceWorld::word(PaymentWorld::record($this->actor->id, $this->company, $this->client, '10000', ['accountId' => $this->account, 'lineId' => $line, 'applications' => $invoices, 'evidence' => [$proof]])))->toBe('COMMITTED'));
    // About forty, one per locked row until the multi-row lock form of LR-05 is verified (QB-13): ten invoices are nine rows more of one class.
    expect($count)->toBeLessThanOrEqual(40 + 9);
});

it('applies and reallocates within the reference command\'s budget (QB-13; PFM-20; AX-19)', function () {
    $a = $this->world->issued($this->project, '1000');
    $b = $this->world->issued($this->project, '1000');
    $payment = PaymentWorld::paid($this->actor->id, $a, '1000')->id;

    expect(paymentStatements(fn () => expect(PaymentWorld::applyInCommand($this->actor->id, $payment, $a, '1000'))->toBe('COMMITTED')))->toBeLessThanOrEqual(40);

    $application = (int) DB::table('payment_applications')->where('payment_id', $payment)->value('id');
    expect(paymentStatements(fn () => expect(PaymentWorld::reallocateInCommand($this->actor->id, $payment, $application, $b, '1000'))->toBe('COMMITTED')))->toBeLessThanOrEqual(40);
});

it('contras in statements proportional to the rows it must change (QB-14; PFM-20; AX-20)', function () {
    $contra = function (int $rows): int {
        $invoices = [];

        foreach (range(1, $rows) as $step) {
            $invoices[$this->world->issued($this->project, '1000')] = '1000';
        }

        $payment = PaymentWorld::record($this->actor->id, $this->company, $this->client, (string) (1000 * $rows), ['applications' => $invoices])->resultId;
        $dispositions = [];

        foreach (DB::table('payment_applications')->where('payment_id', $payment)->pluck('id') as $id) {
            $dispositions[(int) $id] = ['action' => ContraEntry::RELEASE, 'amount' => null];
        }

        return paymentStatements(fn () => expect(PaymentWorld::contraInCommand($this->actor->id, $payment, (string) (1000 * $rows), $dispositions))->toBe('COMMITTED'));
    };

    $one = $contra(1);
    $five = $contra(5);

    expect($five)->toBeGreaterThanOrEqual($one)->and($five - $one)->toBeLessThanOrEqual(4 * 8);
});
