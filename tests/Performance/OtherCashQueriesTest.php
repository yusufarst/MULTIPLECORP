<?php

use App\Support\BusinessDate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\OtherCashWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\SettlementWorld;

// TL-15 (TASK-054; QB-02; QB-13; PFM-01; PFM-02; PFM-20): each PG-049 tab reads in the same number of statements for one fact and several; "Catat Beban" and "Catat Pengeluaran Kas"
// write in the same number of statements whatever was recorded before, never one per row read.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = Authorization::admin([$this->company->id], [...OtherCashWorld::CAPABILITIES, 'finance.contra']);
    $this->accountId = PaymentWorld::account($this->company->id);
});

afterEach(fn () => @unlink($this->hotFile));

function otherCashStatements(callable $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    Auth::forgetGuards();

    return $count;
}

function otherCashFacts(object $test, int $project): void
{
    OtherCashWorld::committed(OtherCashWorld::disburse($test->actor->id, $test->company->id, 'INTERCOMPANY_TRANSFER_OUT', '100000', $test->accountId));
    OtherCashWorld::committed(OtherCashWorld::expense($test->actor->id, $test->company->id, $project, '50000'));
    OtherCashWorld::committed(OtherCashWorld::cashIn($test->actor->id, $test->company->id, 'OTHER_INCOME', '20000', $test->accountId));
}

it('reads each PG-049 tab in the same number of statements for one fact and several (QB-02; PFM-01; PFM-02)', function (string $tab) {
    $project = $this->world->project()->id;
    otherCashFacts($this, $project);
    $url = "/keuangan/kas-lain?tab={$tab}";
    // The session's first page load writes what later loads only read: measured warm.
    otherCashStatements(fn () => $this->actingAs($this->actor)->get($url)->assertOk());
    $one = otherCashStatements(fn () => $this->actingAs($this->actor)->get($url)->assertOk());

    foreach (range(1, 4) as $round) {
        otherCashFacts($this, $project);
    }

    expect(otherCashStatements(fn () => $this->actingAs($this->actor)->get($url)->assertOk()))->toBe($one);
})->with(['pengeluaran', 'beban', 'kas-masuk']);

it('records an expense citing one bill and its payment in the same number of statements whatever came before (QB-13; PFM-20)', function () {
    $bill = SettlementWorld::evidence($this->company->id, 'SUPPLIER_BILL', '1000000')->public_id;
    $account = PaymentWorld::accountHandle($this->accountId);
    $proof = PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->public_id;
    $expense = fn (): int => otherCashStatements(fn () => $this->actingAs($this->actor)->post('/keuangan/kas-lain/beban', ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'kategori' => 'Sewa',
        'jumlah' => '10000', 'tagihan' => $bill, 'tanggal' => BusinessDate::today()])->assertRedirect());
    $transfer = fn (): int => otherCashStatements(fn () => $this->actingAs($this->actor)->post('/keuangan/kas-lain/pengeluaran', ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'jenis' => 'INTERCOMPANY_TRANSFER_OUT',
        'jumlah' => '10000', 'rekening' => $account, 'bukti' => [$proof], 'tanggal' => BusinessDate::today()])->assertRedirect());

    $expense();
    $transfer();
    $secondExpense = $expense();
    $secondTransfer = $transfer();
    $expense();
    $transfer();

    expect($expense())->toBe($secondExpense)->and($transfer())->toBe($secondTransfer)
        ->and(DB::table('expenses')->count())->toBe(4)->and(DB::table('disbursements')->count())->toBe(4);
});
