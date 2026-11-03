<?php

use App\Support\BusinessDate;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\DropshipWorld;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-046; QB-02; QB-13; PFM-01; PFM-02; PFM-20): PG-032 reads a page in the same number of statements for one
// confirmation and twenty-five; "Konfirmasi Kirim Langsung" writes in the same number of statements whatever the line's
// earlier confirmations, never one per row read.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->actor = DropshipWorld::actor($this->world->company);
});

afterEach(fn () => @unlink($this->hotFile));

function dropshipStatements(callable $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('reads PG-032 in the same number of statements for one confirmation and twenty-five (QB-02; PFM-01; PFM-02)', function () {
    $few = DropshipWorld::make($this->world, '10', '10');
    $many = DropshipWorld::make($this->world, '100', '100');
    expect(DropshipWorld::confirm($this->actor->id, $few, '1', 'SJ-1'))->toBe('COMMITTED');

    foreach (range(1, 25) as $round) {
        expect(DropshipWorld::confirm($this->actor->id, $many, '1', "SJ-{$round}"))->toBe('COMMITTED');
    }

    $one = dropshipStatements(fn () => $this->actingAs($this->actor)->get("/proyek/{$few['project']->publicId}/kirim-langsung")->assertOk());
    $page = dropshipStatements(fn () => $this->actingAs($this->actor)->get("/proyek/{$many['project']->publicId}/kirim-langsung")->assertOk());

    expect($page)->toBe($one);
});

it('confirms in the same number of statements whatever the line\'s earlier confirmations (QB-13; PFM-20)', function () {
    $w = DropshipWorld::make($this->world, '100', '100');
    $proof = (string) DB::table('evidence_documents')->where('id', DropshipWorld::proof($this->world->company->id))->value('public_id');
    $post = fn (string $reference): int => dropshipStatements(fn () => $this->actingAs($this->actor)->post("/proyek/{$w['project']->publicId}/kirim-langsung", [
        'command_id' => (string) Str::uuid(), 'tanggal' => BusinessDate::today(), 'pembelian' => $w['purchase']->public_id, 'referensi' => $reference, 'bukti' => $proof,
        'baris' => [['baris' => RowHandle::of('purchase_lines', $w['purchase_line']), 'jumlah' => '1']],
    ])->assertRedirect());

    $post('SJ-0');
    $second = $post('SJ-1');

    foreach (range(2, 9) as $round) {
        $post("SJ-{$round}");
    }

    expect($post('SJ-10'))->toBe($second)->and(DB::table('dropship_confirmations')->count())->toBe(11);
});
