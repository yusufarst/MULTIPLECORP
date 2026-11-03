<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-046; INT-205–INT-207; INT-015; IG-05; CI-05): each command through its route — success, a rejection named, one
// `command_id` replayed with one effect, the same identifier with another body CONFLICT, a likely duplicate return confirmed.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->actor = DropshipWorld::actor($this->world->company);
    $this->w = DropshipWorld::make($this->world, '10', '5');
    $this->url = "/proyek/{$this->w['project']->publicId}/kirim-langsung";
    $this->proof = (string) DB::table('evidence_documents')->where('id', DropshipWorld::proof($this->world->company->id))->value('public_id');
});

afterEach(fn () => @unlink($this->hotFile));

function dropshipInteraction(object $test, string $url, array $body): array
{
    $answer = $test->actingAs($test->actor)->followingRedirects()->post($url, $body)->viewData('page')['flash']['answer'];
    Auth::forgetGuards();

    return $answer;
}

function dropshipConfirmation(object $test, string $qty, string $reference = 'SJ-1'): array
{
    return ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'pembelian' => $test->w['purchase']->public_id, 'referensi' => $reference, 'bukti' => $test->proof,
        'baris' => [['baris' => RowHandle::of('purchase_lines', $test->w['purchase_line']), 'jumlah' => $qty]]];
}

it('answers INT-205 with one command_id replayed and one effect, a changed body CONFLICT, too much REJECTED with the figure (CI-05; C-29; MSG-02)', function () {
    $body = dropshipConfirmation($this, '2');

    expect(dropshipInteraction($this, $this->url, $body)['kind'])->toBe('COMMITTED')
        ->and(dropshipInteraction($this, $this->url, $body)['kind'])->toBe('COMMITTED')
        ->and(DB::table('dropship_confirmations')->count())->toBe(1)
        ->and(dropshipInteraction($this, $this->url, [...$body, 'referensi' => 'SJ-LAIN'])['kind'])->toBe('CONFLICT');

    $over = dropshipInteraction($this, $this->url, dropshipConfirmation($this, '4', 'SJ-2'));

    expect($over['kind'])->toBe('REJECTED')->and($over['code'])->toBe('C-29')->and($over['text'])->toContain('3');
});

it('answers INT-206 and INT-207 in place, one command_id replayed correcting once, a likely duplicate return confirmed with its reason (CI-05; INT-015; L-49)', function () {
    dropshipInteraction($this, $this->url, dropshipConfirmation($this, '4'));
    $confirmation = DropshipWorld::last();
    $line = RowHandle::of('dropship_confirmation_lines', $confirmation->lines[0]);
    $reverse = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Salah catat', 'baris' => [['baris' => $line, 'jumlah' => '1']]];

    expect(dropshipInteraction($this, "{$this->url}/{$confirmation->public_id}/balikkan", $reverse)['kind'])->toBe('COMMITTED')
        ->and(dropshipInteraction($this, "{$this->url}/{$confirmation->public_id}/balikkan", $reverse)['kind'])->toBe('COMMITTED')
        ->and(DB::table('stock_restorations')->where('kind', 'DROPSHIP_CONFIRMATION_REVERSAL')->count())->toBe(1);

    $return = ['command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'alasan' => 'Klien mengembalikan', 'tujuan' => 'PEMASOK', 'baris' => [['baris' => $line, 'jumlah' => '1']]];
    $first = dropshipInteraction($this, "{$this->url}/{$confirmation->public_id}/retur", $return);
    $warned = dropshipInteraction($this, "{$this->url}/{$confirmation->public_id}/retur", [...$return, 'command_id' => (string) Str::uuid()]);
    $matches = array_column($warned['state']['matches'] ?? [], 'id');

    expect($first['kind'])->toBe('COMMITTED')->and($warned['code'])->toBe('L-49')->and($matches)->toHaveCount(1)
        ->and(dropshipInteraction($this, "{$this->url}/{$confirmation->public_id}/retur", [...$return, 'command_id' => (string) Str::uuid(), 'acknowledged' => $matches, 'reason' => 'Dua retur terpisah'])['kind'])->toBe('COMMITTED')
        ->and(DB::table('audit_events')->where('action', 'FUL.DropShipReturnDuplicateConfirmed')->count())->toBe(1)
        ->and(DropshipWorld::demand($this->w['line'])['confirmed'])->toBe('1.000');
});
