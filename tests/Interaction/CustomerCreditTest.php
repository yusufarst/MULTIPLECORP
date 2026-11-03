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

// TL-08 (TASK-053; INT-256; INT-264; IG-05; CI-05): each command through its route — success, a rejection named, one `command_id` replayed with one effect, the same identifier with another body CONFLICT.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->project = $this->world->project();
    $invoice = $this->world->issued($this->project, '1000000');
    $this->payment = PaymentWorld::paid($this->world->actorId, $invoice, '1200000', '1000000');
    DB::table('evidence_links')->insert(['evidence_document_id' => PaymentWorld::proof($this->company->id)->id, 'evidence_scope' => 'COMPANY', 'company_id' => $this->company->id, 'role' => 'PRIMARY_PROOF', 'payment_id' => $this->payment->id,
        'command_id' => (string) Str::uuid(), 'command_line_no' => 1, 'recorded_at' => now(), 'recorded_by' => $this->world->actorId]);
    $this->actor = Authorization::admin([$this->company->id], ['finance.view', 'refund.record']);
});

afterEach(fn () => @unlink($this->hotFile));

function creditInteraction(object $test, string $url, array $body): array
{
    $answer = $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

it('answers INT-256 with one command_id replayed and one effect, a changed body CONFLICT, too much REJECTED with the figure (CI-05; C-13)', function () {
    $url = "/keuangan/kredit-pelanggan/{$this->payment->public_id}/kembalikan";
    $body = ['command_id' => (string) Str::uuid(), 'jumlah' => '100000', 'rekening' => PaymentWorld::accountHandle(PaymentWorld::account($this->company->id)), 'bukti' => PaymentWorld::proof($this->company->id, 'TRANSFER_PROOF')->public_id,
        'alasan' => 'Klien meminta dana kembali', 'tanggal' => BusinessDate::today()];

    expect(creditInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(creditInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('disbursements')->where('kind', 'REFUND')->count())->toBe(1)
        ->and(creditInteraction($this, $url, [...$body, 'jumlah' => '90000'])['kind'])->toBe('CONFLICT');

    $over = creditInteraction($this, $url, [...$body, 'command_id' => (string) Str::uuid(), 'jumlah' => '100001']);

    expect($over['kind'])->toBe('REJECTED')->and($over['code'])->toBe('C-13')->and($over['text'])->toContain('100000.00');
});

it('answers INT-264 with one command_id replayed and one effect, a changed body CONFLICT (CI-05; DIR-104)', function () {
    $url = "/keuangan/kredit-pelanggan/proyek/{$this->project->public_id}/tahan";
    $body = ['command_id' => (string) Str::uuid(), 'alasan' => 'Untuk proyek lanjutan'];

    expect(creditInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(creditInteraction($this, $url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'FIN.CreditHeld')->count())->toBe(1)
        ->and(creditInteraction($this, $url, [...$body, 'alasan' => 'Alasan lain sama sekali'])['kind'])->toBe('CONFLICT');
});
