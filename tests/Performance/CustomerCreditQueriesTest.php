<?php

use App\Support\BusinessDate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\PaymentWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-053; QB-02; QB-14; PFM-01; PFM-02; PFM-20): PG-048 reads in the same number of statements for one credit source and several; "Kembalikan Dana" writes in the same number
// of statements whatever the source's earlier refunds, never one per row read.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = Authorization::admin([$this->company->id], ['finance.view', 'refund.record', 'payment.apply']);
});

afterEach(fn () => @unlink($this->hotFile));

function creditStatements(callable $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    Auth::forgetGuards();

    return $count;
}

function creditOverpaid(object $test, string $paid): int
{
    $invoice = $test->world->issued($test->world->project(), '1000000');
    $payment = PaymentWorld::paid($test->world->actorId, $invoice, $paid, '1000000');
    DB::table('evidence_links')->insert(['evidence_document_id' => PaymentWorld::proof($test->company->id)->id, 'evidence_scope' => 'COMPANY', 'company_id' => $test->company->id, 'role' => 'PRIMARY_PROOF', 'payment_id' => $payment->id,
        'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $test->world->actorId]);

    return $payment->id;
}

it('reads PG-048 in the same number of statements for one credit source and several (QB-02; PFM-01; PFM-02)', function () {
    creditOverpaid($this, '1200000');
    $one = creditStatements(fn () => $this->actingAs($this->actor)->get('/keuangan/kredit-pelanggan')->assertOk());

    foreach (range(1, 4) as $round) {
        creditOverpaid($this, (string) (1200000 + $round * 1000));
    }

    expect(creditStatements(fn () => $this->actingAs($this->actor)->get('/keuangan/kredit-pelanggan')->assertOk()))->toBe($one);
});

it('refunds in the same number of statements whatever the source\'s earlier refunds (QB-14; PFM-20)', function () {
    $payment = DB::table('payments')->where('id', creditOverpaid($this, '2000000'))->value('public_id');
    $account = PaymentWorld::accountHandle(PaymentWorld::account($this->company->id));
    $proof = PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->public_id;
    $refund = fn (): int => creditStatements(fn () => $this->actingAs($this->actor)->post("/keuangan/kredit-pelanggan/{$payment}/kembalikan", ['command_id' => (string) Str::uuid(), 'jumlah' => '100000', 'rekening' => $account, 'bukti' => $proof,
        'alasan' => 'Klien meminta dana kembali', 'tanggal' => BusinessDate::today()])->assertRedirect('/keuangan/kredit-pelanggan'));

    $refund();
    $second = $refund();
    $refund();
    $refund();

    expect($refund())->toBe($second)->and(DB::table('disbursements')->where('kind', 'REFUND')->count())->toBe(5);
});
