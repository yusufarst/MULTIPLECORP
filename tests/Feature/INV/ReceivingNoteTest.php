<?php

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Services\ReceivingSubjects;
use App\Modules\ORG\Enums\SequenceType;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-039; ROUTE-182; INT-182; DOC-12; WF-DOC-01; BR-DOC-04; BR-XD-04; PJ-15; PJ-18; criterion 14): the Nota Terima Barang's subject — a committed receipt, never a fictitious one —, its supplier reference printed, no price, tax or cost; its draft through ROUTE-221, issued after its confirmation, numbered, changing no stock.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-12');
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company);
    $goods = Projects::product();
    $this->rack = ReceivingWorld::rack();
    $this->purchase = ReceivingWorld::purchase($this->company, $goods['product']->id, $goods['base']->id);
    $this->line = ReceivingWorld::line($this->purchase);
    $this->actingAs($this->actor)->post("/gudang/barang-masuk/{$this->purchase->public_id}/terima", ReceivingWorld::request($this->line, $goods['base']->code, $this->rack, '8', '1', '1', ['referensi' => 'SJ-500']));
    $this->movement = DB::table('stock_movements')->where('movement_type', 'RECEIPT')->first(['id', 'public_id']);
});

afterEach(fn () => @unlink($this->hotFile));

it('renders the committed receipt with its supplier reference, its damaged and rejected units, never a price, tax or cost (PJ-18; BR-XD-04; criterion 14)', function () {
    $facts = app(ReceivingSubjects::class)->facts(new DocumentSubject('DOC-12', 'STANDARD', $this->company->id, ['receiving_movement_id' => $this->movement->id]));
    $printed = json_encode($facts);

    expect(array_column($facts->references, 'value'))->toContain('SJ-500')->and($facts->references[0]['value'])->toBe($this->purchase->purchase_number)
        ->and($facts->lines)->toHaveCount(1)->and($facts->lines[0]['unit_price'])->toBeNull()->and($facts->lines[0]['amount'])->toBeNull()
        ->and((float) $facts->lines[0]['quantity'])->toEqual(10.0)
        ->and($facts->lines[0]['note'])->toContain('Rusak diterima')->and($facts->lines[0]['note'])->toContain('Ditolak')
        ->and($printed)->not->toContain('1000');
});

it('opens the draft from the receipt, opens it on PG-036 and issues it numbered, no stock changed (ROUTE-221; ROUTE-182; AX-15; criterion 14)', function () {
    $year = substr(BusinessDate::today(), 0, 4);
    Numbering::goLive("{$year}-01-01");
    Numbering::scheme($this->company, SequenceType::Doc12, "{$year}-01-01", 'YEAR', 'UJI/NTB/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc12, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $stock = DB::table('stock_product_balances')->where('product_id', $this->line->product_id)->value('on_hand_qty');

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-12', 'subjek' => ['receiving_movement_id' => $this->movement->public_id]])->assertRedirect();
    $document = DB::table('documents')->where('document_type_code', 'DOC-12')->sole();
    expect($document->receiving_movement_id)->toBe($this->movement->id)->and($document->company_id)->toBe($this->company->id);

    $this->actingAs($this->actor)->get("/dokumen/{$document->public_id}")->assertOk();
    expect(DB::table('stock_product_balances')->where('product_id', $this->line->product_id)->value('on_hand_qty'))->toBe($stock)
        ->and(DB::table('stock_movements')->count())->toBe(1);
});

it('answers a reversal movement or an unknown id as no subject (WF-DOC-01; BR-DOC-04)', function () {
    $authority = app(EffectiveCapabilities::class)->for($this->actor->id);
    $subjects = app(ReceivingSubjects::class);

    expect($subjects->subject($authority, 'DOC-12', 'STANDARD', ['receiving_movement_id' => (string) Str::uuid()]))->toBeNull()
        ->and($subjects->subject($authority, 'DOC-12', 'STANDARD', ['receiving_movement_id' => 'bukan']))->toBeNull()
        ->and($subjects->subject($authority, 'DOC-12', 'STANDARD', ['receiving_movement_id' => $this->movement->public_id, 'lain' => 'x']))->toBeNull()
        ->and($subjects->subject($authority, 'DOC-12', 'STANDARD', ['receiving_movement_id' => $this->movement->public_id]))->not->toBeNull();
});
