<?php

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\INV\Data\DocumentDisposition;
use App\Modules\INV\Queries\DispatchQueries;
use App\Modules\INV\Services\DispatchSubjects;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Queries\ProjectFulfilment;
use App\Support\BusinessDate;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\DispatchWorld;
use Tests\Support\Evidence;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\ReceivingWorld;
use Tests\Support\RefreshDatabase;
use Tests\Support\ReservationWorld;
use Tests\Support\StockWorld;

// TL-04, TL-06, TL-07, TL-14 (TASK-041; ROUTE-023; ROUTE-024; ROUTE-186–ROUTE-189; PJ-01–PJ-06; SM-01; SM-04; SM-25; IG-01; criteria 17, 18, 21): the pages' props per viewer, each route with its refusals and its capability, never a cost.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = $this->world->company;
    $this->actor = ReceivingWorld::actor($this->company, DispatchWorld::CAPABILITIES);
    $this->rack = ReceivingWorld::rack();
    $this->demand = DispatchWorld::demand($this->world);
    $this->line = $this->demand['line'];
    $this->product = $this->demand['product'];
    $this->unit = $this->demand['unit'];
    $this->project = DB::table('projects')->where('id', $this->demand['project']->id)->value('public_id');
    $this->lot = StockWorld::lot($this->actor->id, $this->company, $this->product, $this->unit, $this->rack);
    Numbering::goLive(date('Y').'-01-01');
});

afterEach(fn () => @unlink($this->hotFile));

function dispatchBody(object $test, string $qty = '2', array $extra = []): array
{
    $page = $test->actingAs($test->actor)->get("/gudang/barang-keluar/{$test->project}")->assertOk()->viewData('page')['props'];
    $line = $page['lines'][0];

    return ['command_id' => (string) Str::uuid(), 'baris' => $line['handle'], 'satuan' => $line['units'][0]['code'], 'jumlah' => $qty, 'lot' => [['lot' => RowHandle::of('inventory_lots', $test->lot), 'rak' => RowHandle::of('locations', $test->rack), 'jumlah' => $qty]], 'kebijakan_seri' => $line['serialized'], 'kebijakan_skala' => $line['scale'], 'tanggal' => BusinessDate::today(), ...$extra];
}

it('lists the remaining reservations of ACTIVE projects by company on PG-023, a bad cursor refused and never a silent first page (CALC-02; PF-14; NAV-023)', function () {
    expect(ReservationWorld::reserve($this->actor->id, $this->line, '3'))->toBe('COMMITTED');
    $queries = app(DispatchQueries::class);
    $page = $queries->worklist($this->actor->id, $this->company->id, null);

    expect($page['rows'])->toHaveCount(1)->and($page['rows'][0]['remaining'])->toBe('3')->and($page['rows'][0]['href'])->toBe("/gudang/barang-keluar/{$this->project}")
        ->and($queries->worklist($this->actor->id, $this->company->id, 'bukan-kursor')['cursor_failed'])->toBeTrue();

    $this->actingAs($this->actor)->get('/gudang/barang-keluar')->assertOk()->assertInertia(fn (AssertableInertia $inertia) => $inertia->component('INV/DispatchWorklist', false)->has('groups', 1)->etc());
    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'COMPLETED_NORMAL']);
    expect($queries->worklist($this->actor->id, $this->company->id, null)['rows'])->toBe([]);
});

it('offers the pool\'s usable lots on PG-024, "Ambil Lebih Dulu" on the oldest, another company\'s by its code, rack and quantity with the marker, and no cost in any prop (PJ-01; PJ-04; SM-25; criterion 18)', function () {
    $foreign = InvoiceWorld::make();
    $other = StockWorld::lot(ReceivingWorld::actor($foreign->company, StockWorld::CAPABILITIES)->id, $foreign->company, $this->product, $this->unit, $this->rack, '4', '77777');
    StockWorld::conditionChange($this->actor->id, $this->company, $this->product, $this->lot, $this->rack, '1');
    $handle = app(DispatchQueries::class)->screen($this->actor->id, $this->demand['project']->id, $this->company->id)['lines'][0]['handle'];
    $version = (string) $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}")->viewData('page')['version'];
    $props = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}?baris={$handle}", ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'INV/DispatchGoods', 'X-Inertia-Partial-Data' => 'choices'])->assertOk()->json('props.choices');
    $lots = collect($props['lots']);

    expect($lots)->toHaveCount(2)->and($lots->where('oldest', true))->toHaveCount(1)->and($lots->firstWhere('other', true))->toHaveKeys(['lot', 'code', 'location', 'rack', 'qty', 'other', 'oldest'])
        ->and(array_keys($lots->firstWhere('other', true)))->not->toContain('company')->and($lots->firstWhere('oldest', true)['qty'])->toBe('9');
    $whole = json_encode($this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}")->viewData('page')['props']);
    expect($whole)->not->toContain('remaining_cost')->not->toContain('77777')->not->toContain($foreign->company->legal_name)
        ->and($other)->toBeGreaterThan(0);
});

it('records a dispatch through ROUTE-186, answers in place and refuses each way at its line (INT-186; C-04; PX-03; criteria 1, 18)', function () {
    $sent = $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", dispatchBody($this));

    $sent->assertRedirect("/gudang/barang-keluar/{$this->project}");
    expect(DB::table('dispatch_lines')->where('project_line_id', $this->line)->value('qty_base'))->toBe('2.000')
        ->and(DB::table('audit_events')->where('action', 'INV.GoodsDispatched')->value('authority_class'))->toBe('ADM');

    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", dispatchBody($this, '25'))->assertRedirect();
    expect(DB::table('dispatch_lines')->count())->toBe(1);
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", dispatchBody($this, '2', ['baris' => str_repeat('a', 32)]))->assertSessionHasErrors('baris');
});

it('answers a foreign project, line and movement exactly like a nonexistent one, and takes no bearer or cost from the payload (CS-03; CS-04; C-32; SM-01; criterion 21)', function () {
    $foreign = InvoiceWorld::make();
    $outsider = ReceivingWorld::actor($foreign->company, DispatchWorld::CAPABILITIES);
    $sent = dispatchBody($this);
    $this->actingAs($outsider)->get("/gudang/barang-keluar/{$this->project}")->assertNotFound();
    $this->actingAs($outsider)->get('/gudang/barang-keluar/'.Str::uuid())->assertNotFound();
    $this->actingAs($outsider)->post("/gudang/barang-keluar/{$this->project}/keluarkan", $sent)->assertNotFound();

    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $movement = DB::table('stock_movements')->where('movement_type', 'DISPATCH')->value('public_id');
    $this->actingAs($outsider)->post("/gudang/barang-keluar/{$movement}/balikkan", [])->assertNotFound();

    // A line of another project of this company is refused like one that does not exist.
    $other = DispatchWorld::demand($this->world);
    $body = dispatchBody($this, '1', ['baris' => RowHandle::of('project_lines', $other['line'])]);
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$this->project}/keluarkan", $body)->assertSessionHasErrors('baris');
});

it('keeps the pages and commands to their capabilities: stock.dispatch, stock.reverse, stock.reserve for the offer (IG-01; W4-13; W4-18; SM-04; criterion 18)', function () {
    $without = Authorization::admin([$this->company->id], ['stock.view', 'projects.view']);
    $this->actingAs($without)->get('/gudang/barang-keluar')->assertForbidden();
    $this->actingAs($without)->get("/gudang/barang-keluar/{$this->project}")->assertForbidden();
    $this->actingAs($without)->post("/gudang/barang-keluar/{$this->project}/keluarkan", [])->assertForbidden();

    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $movement = DB::table('stock_movements')->where('movement_type', 'DISPATCH')->value('public_id');
    $dispatcher = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    $page = $this->actingAs($dispatcher)->get("/gudang/barang-keluar/{$this->project}")->assertOk()->viewData('page')['props'];
    // The Surat Jalan is `documents.issue`'s, on what is still out, whoever may reverse (ROUTE-187; W4-18).
    expect($page['can']['reverse'])->toBeFalse()->and($page['dispatches'][0]['reversible'])->toBeFalse()->and($page['dispatches'][0]['issuable'])->toBeTrue();
    $this->actingAs($dispatcher)->post("/gudang/barang-keluar/{$movement}/balikkan", [])->assertForbidden();
    $this->actingAs($dispatcher)->post("/gudang/barang-keluar/{$movement}/ganti-identitas", [])->assertForbidden();

    // Without `stock.reserve` the restoration's offer is refused, the restoration itself allowed.
    $reverser = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.reverse']);
    $row = DispatchWorld::last($this->line);
    expect(DispatchWorld::restore($reverser->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '1')], '1'))->toContain('AZ-01');
    expect(DispatchWorld::restore($reverser->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '1')]))->toBe('COMMITTED');
});

it('never offers a drop-ship or service line and refuses it at the route; a closed project takes no dispatch (BR-INV-11; L-11; criterion 17)', function () {
    $dropship = $this->world->project(['DROPSHIP'], '5');
    $line = (int) DB::table('project_lines')->where('project_id', $dropship->id)->value('id');
    $public = DB::table('projects')->where('id', $dropship->id)->value('public_id');
    expect($this->actingAs($this->actor)->get("/gudang/barang-keluar/{$public}")->assertOk()->viewData('page')['props']['lines'])->toBe([]);
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toContain('BR-INV-11');

    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'COMPLETED_NORMAL']);
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toContain('L-11');
});

it('opens the Surat Jalan\'s draft from the committed dispatch, no second delivery and no stock changed; a dispatch wholly restored has none (DOC-03; L-19; AX-15; criterion 14)', function () {
    Numbering::scheme($this->company, SequenceType::Doc03, date('Y').'-01-01', 'YEAR', 'UJI/SJ/{tahun}/{nomor:4}');
    Numbering::started($this->company, SequenceType::Doc03, CarbonImmutable::parse(((int) date('Y') - 1).'-12-31 03:00:00', 'UTC'));
    DocumentTypeFactory::ensure('DOC-03');
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '2', [DispatchWorld::pick($this->lot, $this->rack, '2')]))->toBe('COMMITTED');
    $movement = (string) DB::table('stock_movements')->where('movement_type', 'DISPATCH')->value('public_id');
    $before = DB::table('stock_product_balances as b')->where('product_id', $this->product)->value('on_hand_qty');

    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-03', 'subjek' => ['dispatch_movement_id' => $movement]])->assertRedirect();

    $document = DB::table('documents')->whereNotNull('dispatch_movement_id')->first();
    expect($document)->not->toBeNull()->and((int) $document->dispatch_movement_id)->toBe((int) DB::table('stock_movements')->where('public_id', $movement)->value('id'))
        ->and(DB::table('stock_product_balances as b')->where('product_id', $this->product)->value('on_hand_qty'))->toBe($before)
        ->and(DB::table('stock_movements')->where('movement_type', 'DISPATCH')->count())->toBe(1)
        ->and(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('DRAFT');

    // Issued only by its own route, after the confirmation; replayed, one number; no second delivery and no stock changed.
    Queue::fake();
    $draft = DB::table('document_versions')->where('document_id', $document->id)->where('state', 'DRAFT')->first();
    $issue = ['command_id' => (string) Str::uuid(), 'lock_version' => $draft->lock_version, 'draf' => $draft->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true];
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$document->public_id}/surat-jalan", $issue)->assertRedirect();
    $this->actingAs($this->actor)->post("/gudang/barang-keluar/{$document->public_id}/surat-jalan", $issue)->assertRedirect();
    expect(DB::table('document_versions')->where('document_id', $document->id)->pluck('state')->all())->toBe(['ISSUED'])
        ->and(DB::table('document_versions')->where('document_id', $document->id)->value('formatted_number'))->toStartWith('UJI/SJ/')
        ->and(DB::table('stock_product_balances as b')->where('product_id', $this->product)->value('on_hand_qty'))->toBe($before)
        ->and(DB::table('delivery_lines')->count())->toBe(0);
});

it('prints what is still out and offers no Surat Jalan once wholly restored, whatever way it left (BR-DOC-04; ER-06)', function () {
    DocumentTypeFactory::ensure('DOC-03');
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '5', [DispatchWorld::pick($this->lot, $this->rack, '5')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $subjects = app(DispatchSubjects::class);
    $subject = new DocumentSubject('DOC-03', 'STANDARD', $this->company->id, ['dispatch_movement_id' => $row->movement]);
    $public = (string) DB::table('stock_movements')->where('id', $row->movement)->value('public_id');
    $viewer = app(EffectiveCapabilities::class)->for($this->actor->id);

    expect(DispatchWorld::restore($this->actor->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '2')]))->toBe('COMMITTED');
    expect($subjects->facts($subject)->lines[0]['quantity'])->toBe('3')->and($subjects->subject($viewer, 'DOC-03', 'STANDARD', ['dispatch_movement_id' => $public]))->not->toBeNull();

    expect(DispatchWorld::restore($this->actor->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($row->consumption, $this->rack, '3')]))->toBe('COMMITTED');
    $page = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}")->viewData('page')['props'];
    expect($page['dispatches'][0]['issuable'])->toBeFalse()->and($page['dispatches'][0]['reversible'])->toBeFalse()
        ->and($subjects->subject($viewer, 'DOC-03', 'STANDARD', ['dispatch_movement_id' => $public]))->toBeNull();
});

it('narrows the line\'s choices to what is looked for and tells a list it cut; the dispatches continue by a keyset, a bad cursor refused (PF-13; PF-14; IP-15; ER-06)', function () {
    $queries = app(DispatchQueries::class);
    $handle = $queries->screen($this->actor->id, $this->demand['project']->id, $this->company->id)['lines'][0]['handle'];
    $code = (string) DB::table('inventory_lots')->where('id', $this->lot)->value('lot_code');

    expect($queries->choices($this->demand['project']->id, $this->company->id, $handle))->toMatchArray(['find' => null, 'more' => false])
        ->and($queries->choices($this->demand['project']->id, $this->company->id, $handle, strtolower($code))['lots'])->toHaveCount(1)
        ->and($queries->choices($this->demand['project']->id, $this->company->id, $handle, 'tidak-ada')['lots'])->toBe([]);

    $failed = $queries->screen($this->actor->id, $this->demand['project']->id, $this->company->id, 'bukan-kursor');
    expect($failed['older_failed'])->toBeTrue()->and($failed['dispatches'])->toBe([]);

});

/** The dispatch's Surat Jalan issued through its own route; its document. */
function dispatchIssuedNote(object $test, int $movement): object
{
    if (! isset($test->noteNumbered)) {
        Numbering::scheme($test->company, SequenceType::Doc03, date('Y').'-01-01', 'YEAR', 'UJI/SJ/{tahun}/{nomor:4}');
        Numbering::started($test->company, SequenceType::Doc03, CarbonImmutable::parse(((int) date('Y') - 1).'-12-31 03:00:00', 'UTC'));
        $test->noteNumbered = true;
    }

    DocumentTypeFactory::ensure('DOC-03');
    Queue::fake();
    $public = (string) DB::table('stock_movements')->where('id', $movement)->value('public_id');
    $test->actingAs($test->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-03', 'subjek' => ['dispatch_movement_id' => $public]])->assertRedirect();
    $document = DB::table('documents')->where('dispatch_movement_id', $movement)->sole();
    $draft = DB::table('document_versions')->where('document_id', $document->id)->where('state', 'DRAFT')->sole();
    $test->actingAs($test->actor)->post("/gudang/barang-keluar/{$document->public_id}/surat-jalan", ['command_id' => (string) Str::uuid(), 'lock_version' => $draft->lock_version, 'draf' => $draft->public_id, 'tanggal' => BusinessDate::today(), 'tanggal_otomatis' => true])->assertRedirect();
    expect(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('ISSUED');

    return $document;
}

it('makes a correction revise, void or keep with its reason the issued Surat Jalan it affects, in its own transaction (WORKFLOWS §8; CM-05; CM-36; C-11; criterion 13)', function () {
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '6', [DispatchWorld::pick($this->lot, $this->rack, '6')]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    $document = dispatchIssuedNote($this, $row->movement);
    $item = fn (string $qty): array => [DispatchWorld::item($row->consumption, $this->rack, $qty)];
    $keep = new DocumentDisposition('KEEP', 'Kertas sudah di tangan klien');
    $versions = fn (): array => DB::table('document_versions')->where('document_id', $document->id)->orderBy('id')->pluck('state')->all();
    $correcting = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'documents.correct']);
    $page = $this->actingAs($this->actor)->get("/gudang/barang-keluar/{$this->project}")->viewData('page')['props'];
    expect($page['dispatches'][0]['note']['id'])->toBe($document->public_id)->and($page['dispatches'][0]['note']['number'])->toStartWith('UJI/SJ/');

    // Nothing is corrected until the document's fate is chosen, with its reason.
    expect(DispatchWorld::restore($this->actor->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', $item('1')))->toContain('WF-DOC-01')
        ->and(DispatchWorld::restore($this->actor->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', $item('1'), document: new DocumentDisposition('KEEP', ' ')))->toContain('WF-DOC-01')
        ->and(DB::table('stock_restorations')->count())->toBe(0);

    expect(DispatchWorld::restore($this->actor->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', $item('1'), document: $keep))->toBe('COMMITTED')
        ->and($versions())->toBe(['ISSUED']);
    $after = json_decode(DB::table('audit_events')->where('action', 'INV.DispatchReversed')->value('after'), true);
    expect($after['document']['disposition'])->toBe('KEEP')->and($after['document']['reason'])->toBe('Kertas sudah di tangan klien')->and($after['document']['number'])->toStartWith('UJI/SJ/');

    // A revision's draft or a void is `documents.correct`'s; refused, the correction with it.
    expect(DispatchWorld::restore($this->actor->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', $item('2'), document: new DocumentDisposition('REVISE', 'Jumlah berubah')))->toContain('AZ-01')
        ->and(DB::table('stock_restorations')->count())->toBe(1);
    expect(DispatchWorld::restore($correcting->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', $item('2'), document: new DocumentDisposition('REVISE', 'Jumlah berubah')))->toBe('COMMITTED')
        ->and($versions())->toBe(['ISSUED', 'DRAFT'])
        ->and(DB::table('document_versions')->where('document_id', $document->id)->where('state', 'DRAFT')->value('supersedes_version_id'))->toBe(DB::table('document_versions')->where('document_id', $document->id)->where('state', 'ISSUED')->value('id'))
        ->and(DB::table('audit_events')->where('action', 'DOC.DocumentRevisionDrafted')->count())->toBe(1);

    // The rest back: the Surat Jalan voided with it, its number retired, its revision's draft gone.
    expect(DispatchWorld::restore($correcting->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', $item('3'), document: new DocumentDisposition('VOID', 'Tidak jadi dikirim')))->toBe('COMMITTED')
        ->and($versions())->toBe(['VOIDED']);
});

it('asks the Surat Jalan\'s fate of a serial\'s substitution, whose paper names it, and not of a lot\'s (CM-36; architecture challenge 3c)', function () {
    $evidence = DispatchWorld::evidence($this->company);
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $plain = DispatchWorld::last($this->line);
    dispatchIssuedNote($this, $plain->movement);
    $second = StockWorld::lot($this->actor->id, $this->company, $this->product, $this->unit, $this->rack, '2');
    expect(DispatchWorld::substitute($this->actor->id, $this->company->id, $plain->consumption, '1', null, $second, $this->rack, null, $evidence))->toBe('COMMITTED');

    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    [$recorded, $shipped] = [StockWorld::serial($this->product, $this->lot, $this->rack), StockWorld::serial($this->product, $this->lot, $this->rack)];
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1', $recorded)], null, DB::table('stock_movements')->where('movement_type', 'DISPATCH')->pluck('public_id')->all(), 'Kiriman kedua'))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    dispatchIssuedNote($this, $row->movement);

    expect(DispatchWorld::substitute($this->actor->id, $this->company->id, $row->consumption, '1', $recorded, $this->lot, $this->rack, $shipped, $evidence))->toContain('WF-DOC-01')
        ->and(DB::table('serial_units')->where('id', $recorded)->value('state'))->toBe('DISPATCHED')
        ->and(DispatchWorld::substitute($this->actor->id, $this->company->id, $row->consumption, '1', $recorded, $this->lot, $this->rack, $shipped, $evidence, document: new DocumentDisposition('KEEP', 'Ditandatangani klien')))->toBe('COMMITTED')
        ->and(DB::table('serial_units')->where('id', $shipped)->value('state'))->toBe('DISPATCHED');
});

it('returns a completed project to ACTIVE when a correction voids its Surat Jalan or restores goods, cost unchanged or not, and not when it keeps the paper (SF-REVAL; WF-PRJ-03; CM-32)', function () {
    $evidence = DispatchWorld::evidence($this->company);
    $correcting = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'documents.correct']);
    DB::table('products')->where('id', $this->product)->update(['is_serialized' => true]);
    [$first, $second, $third] = [StockWorld::serial($this->product, $this->lot, $this->rack), StockWorld::serial($this->product, $this->lot, $this->rack), StockWorld::serial($this->product, $this->lot, $this->rack)];
    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1', $first)]))->toBe('COMMITTED');
    $row = DispatchWorld::last($this->line);
    dispatchIssuedNote($this, $row->movement);
    $state = fn (): string => (string) DB::table('projects')->where('id', $this->demand['project']->id)->value('state');
    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'COMPLETED_NORMAL']);

    // The same lot, so the same cost: keeping the paper changes no predicate.
    expect(DispatchWorld::substitute($correcting->id, $this->company->id, $row->consumption, '1', $first, $this->lot, $this->rack, $second, $evidence, document: new DocumentDisposition('KEEP', 'Ditandatangani klien')))->toBe('COMMITTED')
        ->and($state())->toBe('COMPLETED_NORMAL');

    $next = (int) DB::table('stock_consumptions')->where('dispatch_line_id', $row->dispatch)->orderByDesc('id')->value('id');
    expect(DispatchWorld::substitute($correcting->id, $this->company->id, $next, '1', $second, $this->lot, $this->rack, $third, $evidence, document: new DocumentDisposition('VOID', 'Surat jalan salah')))->toBe('COMMITTED')
        ->and($state())->toBe('ACTIVE')
        ->and(DB::table('project_state_transitions')->where('project_id', $this->demand['project']->id)->where('transition_kind', 'REVALIDATE')->count())->toBe(1);

    // A restoration lowers net dispatched, a predicate's input, even at no cost.
    DB::table('projects')->where('id', $this->demand['project']->id)->update(['state' => 'COMPLETED_NORMAL']);
    $last = (int) DB::table('stock_consumptions')->where('dispatch_line_id', $row->dispatch)->orderByDesc('id')->value('id');
    expect(DispatchWorld::restore($correcting->id, $this->company->id, $row->dispatch, 'DISPATCH_REVERSAL', [DispatchWorld::item($last, $this->rack, '1', 'USABLE', $third)]))->toBe('COMMITTED')
        ->and($state())->toBe('ACTIVE');
});

it('offers the proofs a viewer may see for a substitution, the company\'s and the pool\'s, narrowed when asked, so no upload authority is needed (PJ-08; CM-36; AGENTS rule 12)', function () {
    $own = DB::table('evidence_documents')->where('id', DispatchWorld::evidence($this->company, ['evidence_type' => 'DELIVERY_PROOF', 'declared_issuer' => 'Gudang Uji']))->value('public_id');
    $pool = DB::table('evidence_documents')->where('id', DispatchWorld::evidence($this->company, ['evidence_type' => 'WAREHOUSE_RECORD', 'evidence_scope' => 'POOL', 'company_id' => null]))->value('public_id');
    $foreign = InvoiceWorld::make();
    $other = DB::table('evidence_documents')->where('id', DispatchWorld::evidence($foreign->company, ['evidence_type' => 'DELIVERY_PROOF']))->value('public_id');
    $viewer = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'evidence.view']);
    $queries = app(DispatchQueries::class);
    $found = collect($queries->proofs($viewer->id, $this->company->id)['rows'])->pluck('id')->all();

    expect($found)->toContain($own)->toContain($pool)->not->toContain($other)
        ->and($queries->proofs($viewer->id, $this->company->id, 'gudang uji')['rows'])->toHaveCount(1)
        ->and($queries->proofs($viewer->id, $this->company->id, 'tidak ada')['rows'])->toBe([])
        ->and($queries->evidence($viewer->id, $this->company->id, (string) $own))->toBeInt()
        ->and($queries->evidence($viewer->id, $this->company->id, (string) $other))->toBeNull();
});

it('keeps PG-022\'s and PG-024\'s forms in place when a proof is registered from them, and answers with the evidence (IP-16; NV-04; ROUTE-229; ER-06)', function () {
    $uploader = ReceivingWorld::actor($this->company, [...DispatchWorld::CAPABILITIES, 'evidence.upload', 'evidence.view']);
    $company = (string) DB::table('companies')->where('id', $this->company->id)->value('public_id');
    $file = fn (string $marker) => Evidence::upload($uploader, $this->company->id, Evidence::pdf($marker));

    foreach (["/gudang/barang-keluar/{$this->project}", "/gudang/barang-masuk/{$this->project}"] as $back) {
        $this->actingAs($uploader)->post('/bukti', Evidence::registration($file($back), $company, ['type' => 'DELIVERY_PROOF', 'reference' => $back, 'kembali' => $back]))->assertRedirect($back);
    }

    // Any other address is no return address.
    $this->actingAs($uploader)->post('/bukti', Evidence::registration($file('luar'), $company, ['type' => 'DELIVERY_PROOF', 'reference' => 'luar', 'kembali' => 'https://contoh.test/gudang/barang-keluar/'.$this->project]))->assertRedirectContains('/dokumen/bukti');
});

it('opens the dispatch record from *Rincian proyek* for who may correct it once something left (ROUTE-189; INT-189; ER-06)', function () {
    $fulfilment = app(ProjectFulfilment::class);
    $capabilities = app(EffectiveCapabilities::class);
    $project = Project::query()->findOrFail($this->demand['project']->id);
    expect($fulfilment->of($capabilities->for($this->actor->id), $project)['correct_dispatch'])->toBeFalse();

    expect(DispatchWorld::dispatch($this->actor->id, $this->company->id, $this->line, $this->unit, '1', [DispatchWorld::pick($this->lot, $this->rack, '1')]))->toBe('COMMITTED');
    $plain = Authorization::admin([$this->company->id], ['stock.view', 'projects.view', 'stock.dispatch']);
    expect($fulfilment->of($capabilities->for($this->actor->id), $project)['correct_dispatch'])->toBeTrue()
        ->and($fulfilment->of($capabilities->for($plain->id), $project)['correct_dispatch'])->toBeFalse();
});
