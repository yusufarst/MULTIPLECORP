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

// TL-08 (TASK-054; INT-257–INT-259; IG-05; CI-05): each command through its route — success, a rejection named, one `command_id` replayed with one effect, the same identifier with another body
// CONFLICT, a field error at its field.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = Authorization::admin([$this->company->id], OtherCashWorld::CAPABILITIES);
    $this->account = PaymentWorld::accountHandle(PaymentWorld::account($this->company->id));
    $this->proof = PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->public_id;
});

afterEach(fn () => @unlink($this->hotFile));

function otherCashInteraction(object $test, string $url, array $body): array
{
    $answer = $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

it('answers each of INT-257–INT-259 with one command_id replayed and one effect, a changed body CONFLICT (CI-05; IG-05)', function (string $url, array $fields, string $table) {
    $body = ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'jumlah' => '25000', 'tanggal' => BusinessDate::today(), 'bukti' => [$this->proof], 'rekening' => $this->account, ...$fields];

    expect(otherCashInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(otherCashInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table($table)->count())->toBe(1)
        ->and(otherCashInteraction($this, $url, [...$body, 'jumlah' => '26000'])['kind'])->toBe('CONFLICT')
        ->and(DB::table($table)->count())->toBe(1);
})->with([
    'INT-257' => ['/keuangan/kas-lain/beban', ['kategori' => 'Sewa gudang'], 'expenses'],
    'INT-258' => ['/keuangan/kas-lain/pengeluaran', ['jenis' => 'INTERCOMPANY_TRANSFER_OUT'], 'disbursements'],
    'INT-259' => ['/keuangan/kas-lain/kas-masuk', ['kategori' => 'CASHBACK_INCOME'], 'other_cash_receipts'],
]);

it('names each rejection with its figure in place (MSG-02; NV-04)', function () {
    $bill = SettlementWorld::evidence($this->company->id, 'SUPPLIER_BILL', '10000')->public_id;
    $base = ['perusahaan' => $this->company->public_id, 'tanggal' => BusinessDate::today()];

    $expense = otherCashInteraction($this, '/keuangan/kas-lain/beban', [...$base, 'command_id' => (string) Str::uuid(), 'kategori' => 'Sewa', 'jumlah' => '10001', 'tagihan' => $bill]);
    $reserved = otherCashInteraction($this, '/keuangan/kas-lain/beban', [...$base, 'command_id' => (string) Str::uuid(), 'kategori' => 'FEE_DEDUCTION', 'jumlah' => '1000', 'bukti' => [$this->proof]]);
    $proofless = otherCashInteraction($this, '/keuangan/kas-lain/pengeluaran', [...$base, 'command_id' => (string) Str::uuid(), 'jenis' => 'INTERCOMPANY_TRANSFER_OUT', 'jumlah' => '1000', 'rekening' => $this->account]);
    $orphan = otherCashInteraction($this, '/keuangan/kas-lain/kas-masuk', [...$base, 'command_id' => (string) Str::uuid(), 'kategori' => 'SUPPLIER_REFUND', 'jumlah' => '1000', 'rekening' => $this->account, 'bukti' => [$this->proof]]);

    expect([$expense['kind'], $expense['code']])->toBe(['REJECTED', 'C-20'])->and($expense['text'])->toContain('10.000')
        ->and([$reserved['kind'], $reserved['code']])->toBe(['REJECTED', 'C-23'])
        ->and([$proofless['kind'], $proofless['code']])->toBe(['REJECTED', 'L-29'])
        ->and([$orphan['kind'], $orphan['code']])->toBe(['REJECTED', 'C-14']);
});

it('answers a malformed field at the field, the command never run (MSG-12; IP-14)', function () {
    $this->actingAs($this->actor)->post('/keuangan/kas-lain/pengeluaran', ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'jenis' => 'REFUND', 'jumlah' => '0', 'rekening' => $this->account, 'tanggal' => BusinessDate::today()])
        ->assertSessionHasErrors(['jenis', 'jumlah']);
    $this->actingAs($this->actor)->post('/keuangan/kas-lain/kas-masuk', ['command_id' => (string) Str::uuid(), 'perusahaan' => $this->company->public_id, 'kategori' => 'INTERCOMPANY_TRANSFER_IN', 'jumlah' => '1000', 'rekening' => $this->account, 'tanggal' => BusinessDate::today()])
        ->assertSessionHasErrors('transfer');

    expect(DB::table('disbursements')->count() + DB::table('other_cash_receipts')->count())->toBe(0);
});
