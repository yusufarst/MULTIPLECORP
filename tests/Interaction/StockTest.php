<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\StockWorld;

// TL-08 (TASK-040; IG-05; INT-212; CI-05; REF-058; AC-16; criterion 14): ROUTE-212 once for one `command_id` sent twice, a changed payload CONFLICT, the reason required, the refusals in words.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, StockWorld::CAPABILITIES);
    $goods = Projects::product();
    $this->product = $goods['product'];
    $this->rack = ReceivingWorld::rack();
    $this->lot = StockWorld::lot($this->actor->id, $this->company, $this->product->id, $goods['base']->id, $this->rack);
    $this->change = StockWorld::conditionChange($this->actor->id, $this->company, $this->product->id, $this->lot, $this->rack, '2');
    $this->url = '/gudang/riwayat-stok/'.DB::table('stock_movements')->where('id', $this->change)->value('public_id').'/balikkan';
});

afterEach(fn () => @unlink($this->hotFile));

function stockFlash(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('INT-212: reverses once, a replay the recorded answer, another content a CONFLICT, the reason at its field (IP-09; MSG-40; CI-05; criterion 14)', function () {
    $body = ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah kondisi', 'tanggal' => date('Y-m-d')];

    $this->actingAs($this->actor)->post($this->url, [...$body, 'alasan' => ''])->assertSessionHasErrors('alasan');
    expect(DB::table('stock_movements')->where('reverses_movement_id', $this->change)->count())->toBe(0);

    $this->actingAs($this->actor)->post($this->url, $body)->assertRedirect('/gudang/riwayat-stok');
    expect(stockFlash()['kind'])->toBe('COMMITTED', json_encode(stockFlash()));
    $this->actingAs($this->actor)->post($this->url, $body)->assertRedirect('/gudang/riwayat-stok');
    expect(stockFlash()['kind'])->toBe('COMMITTED')->and(DB::table('stock_movements')->where('reverses_movement_id', $this->change)->count())->toBe(1)
        ->and(DB::table('audit_events')->where('action', 'INV.MovementReversed')->count())->toBe(1);

    $this->actingAs($this->actor)->post($this->url, [...$body, 'alasan' => 'Alasan lain']);
    expect(stockFlash()['kind'])->toBe('CONFLICT')->and(DB::table('stock_movements')->where('reverses_movement_id', $this->change)->count())->toBe(1);
});

it('INT-212: answers a second reversal and a movement not reversible here in words (C-55)', function () {
    $this->actingAs($this->actor)->post($this->url, ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah kondisi', 'tanggal' => date('Y-m-d')]);
    $this->actingAs($this->actor)->post($this->url, ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah kondisi', 'tanggal' => date('Y-m-d')]);
    expect(stockFlash()['code'])->toBe('C-55')->and(stockFlash()['text'])->toBe(__('stock.refusal.reversed'));

    $receipt = DB::table('stock_movements')->where('movement_type', 'RECEIPT')->value('public_id');
    $this->actingAs($this->actor)->post("/gudang/riwayat-stok/{$receipt}/balikkan", ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah kondisi', 'tanggal' => date('Y-m-d')]);
    expect(stockFlash()['code'])->toBe('C-55');
});

it('INT-212: refuses an unknown reservation at its field (IP-14)', function () {
    $this->actingAs($this->actor)->post($this->url, ['command_id' => (string) Str::uuid(), 'alasan' => 'Salah kondisi', 'tanggal' => date('Y-m-d'), 'potong' => [['reservasi' => str_repeat('a', 32), 'jumlah' => '1']]])->assertSessionHasErrors('potong.0.reservasi');
});
