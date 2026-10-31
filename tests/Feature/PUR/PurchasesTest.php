<?php

use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\TaxType;
use App\Modules\PRJ\Contracts\CompletionPredicateProvider;
use App\Modules\PRJ\Data\JourneyItem;
use App\Modules\PRJ\Data\Predicates;
use App\Modules\PRJ\Data\PredicateState;
use App\Modules\PRJ\Queries\ProjectJourneys;
use App\Modules\PUR\Actions\SupersedeRemainderClosure;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Services\PurchaseCorrections;
use App\Modules\PUR\Services\PurchasePredicate;
use App\Modules\PUR\Services\PurchaseSubjects;
use App\Support\BusinessDate;
use App\Support\Money;
use App\Support\RowHandle;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\ORG\CompanyBankAccountFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\Numbering;
use Tests\Support\Projects;
use Tests\Support\PurchaseWorld;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-037): ROUTE-015–ROUTE-017, ROUTE-171–ROUTE-175 and ROUTE-178–ROUTE-180 with each refusal and the saved answer without cash or stock; the acceptance criteria named on each.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->company = PurchaseWorld::numbered($this->world->company);
    $this->actor = PurchaseWorld::actor($this->company);
    $this->supplier = PurchaseWorld::supplier();
    $this->goods = Projects::product();
    $this->today = BusinessDate::today();
});

afterEach(fn () => @unlink($this->hotFile));

function purchaseAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

/** A replenishment purchase saved through ROUTE-171; its row. */
function savePurchase(object $test, array $lines, array $more = []): Purchase
{
    $test->actingAs($test->actor)->post('/pembelian', PurchaseWorld::payload($test->supplier, $lines, ['perusahaan' => $test->company->public_id, ...$more]))->assertRedirect();
    expect(purchaseAnswer()['kind'] ?? null)->toBe('COMMITTED', json_encode(purchaseAnswer()));

    return PurchaseWorld::latest($test->company->id);
}

function purchaseBalance(int $line): object
{
    return DB::table('purchase_line_balances')->where('purchase_line_id', $line)->first();
}

function reasoned(object $test, array $more = []): array
{
    return ['command_id' => (string) Str::uuid(), 'tanggal' => $test->today, 'alasan' => 'Salah catat di awal', ...$more];
}

it('saves an OPEN purchase with its number, lines and charges, and records no cash, stock, lot or reservation (BR-PUR-03; AC-05; J-PUR-01; criterion 1)', function () {
    $bill = PurchaseWorld::bill($this->company->id, '150000');
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '100000')], ['referensi' => 'PEN-77', 'biaya' => [['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '100000', 'dasar' => 'LINE_VALUE']]]);
    $line = PurchaseWorld::lines($purchase)[0];

    expect($purchase->state)->toBe('OPEN')->and($purchase->purchase_number)->toStartWith('CTH/')->and($purchase->supplier_reference)->toBe('PEN-77')
        ->and($purchase->supplier_snapshot['name'])->toBe($this->supplier->name)
        ->and(purchaseAnswer()['text'])->toBe(__('purchases.answer.recorded'))
        ->and($line->qty_base)->toBe('10.000')->and($line->line_value)->toBe('1000000.00')
        ->and(purchaseBalance($line->id)->ordered_qty_base)->toBe('10.000')->and(purchaseBalance($line->id)->goods_value)->toBe('1000000.00')
        ->and(DB::table('disbursements')->count())->toBe(0)->and(DB::table('stock_movements')->count())->toBe(0)->and(DB::table('inventory_lots')->count())->toBe(0)->and(DB::table('reservations')->count())->toBe(0)
        ->and(DB::table('audit_events')->where('action', 'PUR.PurchaseRecorded')->count())->toBe(1);
});

it('refuses a save whose period may create no counter with MSG-17, nothing committed (NM-03; C-10; criterion 2)', function () {
    $other = Company::factory()->create();
    $actor = PurchaseWorld::actor($other);
    $this->actingAs($actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $other->public_id]))->assertRedirect();

    expect(purchaseAnswer()['code'])->toBe('C-10')->and(purchaseAnswer()['text'])->toContain($other->legal_name)->and(DB::table('purchases')->count())->toBe(0);
});

it('lets two suppliers serve one confirmed demand line without duplicating it, the PO never a precondition (BR-PUR-02; CJ-02; criterion 3)', function () {
    $project = $this->world->project(['WAREHOUSE'], '10');
    $demand = DB::table('project_lines')->where('project_id', $project->id)->first();
    $handle = RowHandle::of('project_lines', $demand->id);
    $unit = DB::table('product_units as pu')->join('units as u', 'u.id', '=', 'pu.unit_id')->where('pu.product_id', $demand->product_id)->where('pu.is_base', true)->value('u.code');
    $productPublic = DB::table('products')->where('id', $demand->product_id)->value('public_id');
    $demandBefore = DB::table('project_line_balances')->where('project_line_id', $demand->id)->value('demand_qty_base');
    $line = ['jenis' => 'GOODS', 'produk' => $productPublic, 'satuan' => $unit, 'jumlah' => '6', 'harga' => '1000', 'item_proyek' => $handle];

    foreach ([PurchaseWorld::supplier(), PurchaseWorld::supplier()] as $supplier) {
        $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($supplier, [$line], ['proyek' => $project->public_id]))->assertRedirect();
        expect(purchaseAnswer()['kind'] ?? null)->toBe('COMMITTED', json_encode(purchaseAnswer()));
    }

    expect(DB::table('purchases')->where('project_id', $project->id)->count())->toBe(2)
        ->and(DB::table('purchases')->where('project_id', $project->id)->pluck('company_id')->unique()->all())->toBe([$this->company->id])
        ->and(DB::table('project_line_balances')->where('project_line_id', $demand->id)->value('demand_qty_base'))->toBe($demandBefore)
        ->and(DB::table('documents')->whereNotNull('purchase_id')->count())->toBe(0);
});

it('takes the project\'s company, a foreign project, demand line or bill answering like none (L-05; CS-04; challenge 1a; criterion 4)', function () {
    $other = InvoiceWorld::make();
    $foreign = $other->project(['WAREHOUSE'], '5');
    $foreignLine = DB::table('project_lines')->where('project_id', $foreign->id)->value('id');
    $own = $this->world->project(['WAREHOUSE'], '5');
    $sent = fn (array $more) => $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '1', '100', $more['line'] ?? [])], $more['request'] ?? []));

    $sent(['request' => ['proyek' => $foreign->public_id]])->assertSessionHasErrors('proyek');
    $sent(['request' => ['proyek' => $own->public_id], 'line' => ['item_proyek' => RowHandle::of('project_lines', (int) $foreignLine)]])->assertSessionHasErrors('baris.0.item_proyek');
    $sent(['request' => ['perusahaan' => $this->company->public_id, 'biaya' => [['tagihan' => PurchaseWorld::bill($other->company->id), 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '100']]]])->assertSessionHasErrors('biaya.0.tagihan');
    expect(DB::table('purchases')->count())->toBe(0);
});

it('keeps a replenishment purchase to goods, its charge a company expense; a deactivated company or an archived supplier refuses it (WF-PUR-02; C-54; J-MD-03; criterion 5)', function () {
    $service = Projects::product('SERVICE');
    $bill = PurchaseWorld::bill($this->company->id, '50000');
    $nonStock = PurchaseWorld::line($service, '1', '500', ['jenis' => 'NONSTOCK']);
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [$nonStock], ['perusahaan' => $this->company->public_id]));
    expect(purchaseAnswer()['code'])->toBe('WF-PUR-02');

    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods)], ['biaya' => [['tagihan' => $bill, 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '20000']]]);
    $expense = DB::table('expenses')->where('purchase_charge_id', DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id'))->first();
    expect($purchase->project_id)->toBeNull()->and($expense->amount)->toBe('20000.00')->and($expense->project_id)->toBeNull()->and($expense->company_id)->toBe($this->company->id);

    DB::table('suppliers')->where('id', $this->supplier->id)->update(['is_active' => false, 'deactivated_at' => now(), 'deactivated_reason' => 'Tutup']);
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $this->company->public_id]));
    expect(purchaseAnswer()['code'])->toBe('J-MD-03');

    DB::table('suppliers')->where('id', $this->supplier->id)->update(['is_active' => true, 'deactivated_at' => null, 'deactivated_reason' => null]);
    DB::table('companies')->where('id', $this->company->id)->update(['is_active' => false]);
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $this->company->public_id]));
    expect(purchaseAnswer()['code'])->toBe('C-54');
});

it('records numeric annex 14: freight 100,000 on A 10 × 100,000 and B 30 × 100,000 shared 25,000 and 75,000, an administration fee 20,000 an expense only; above the bill REJECTED (L-43; C-20; BD-17; challenge 2c; criterion 6)', function () {
    $bill = PurchaseWorld::bill($this->company->id, '120000');
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '100000'), PurchaseWorld::line(Projects::product(), '30', '100000')], ['biaya' => [
        ['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '100000', 'dasar' => 'LINE_VALUE'],
        ['tagihan' => $bill, 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '20000'],
    ]]);
    [$a, $b] = PurchaseWorld::lines($purchase);
    $charge = DB::table('purchase_charges')->where('purchase_id', $purchase->id)->where('classification', 'ATTRIBUTABLE')->value('id');

    expect(DB::table('purchase_charge_line_shares')->where('purchase_charge_id', $charge)->where('purchase_line_id', $a->id)->value('share_amount'))->toBe('25000.00')
        ->and(DB::table('purchase_charge_line_shares')->where('purchase_charge_id', $charge)->where('purchase_line_id', $b->id)->value('share_amount'))->toBe('75000.00')
        ->and(DB::table('expenses')->where('company_id', $this->company->id)->sum('amount'))->toEqual('20000.00')
        ->and(DB::table('evidence_balances')->where('evidence_document_id', DB::table('evidence_documents')->where('public_id', $bill)->value('id'))->value('cited_amount'))->toBe('120000.00');

    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $this->company->public_id, 'biaya' => [['tagihan' => $bill, 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '1']]]));
    expect(purchaseAnswer()['code'])->toBe('C-20')->and(DB::table('purchases')->count())->toBe(1);
});

it('records each tax component under its treatment, only the non-creditable part offered to cost (DIR-024 D-1; BR-FIN-17; criterion 7)', function () {
    $creditable = PurchaseWorld::tax(null, 'INPUT_CREDITABLE');
    $toCost = PurchaseWorld::tax($this->company->id, 'INPUT_TO_ACQUISITION_COST');
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '100000', ['pajak' => [['kode' => $creditable, 'tarif' => '11', 'nilai' => '110000'], ['kode' => $toCost, 'tarif' => '2.5', 'nilai' => '25000']]])]);
    $line = PurchaseWorld::lines($purchase)[0];
    $rows = DB::table('purchase_line_tax_components')->where('purchase_line_id', $line->id)->orderBy('id')->get();

    expect($rows->pluck('treatment')->all())->toBe(['INPUT_CREDITABLE', 'INPUT_TO_ACQUISITION_COST'])->and($rows[0]->base_amount)->toBe('1000000.00')->and(purchaseBalance($line->id)->noncreditable_tax_value)->toBe('25000.00');

    $unruled = TaxType::factory()->create(['code' => 'TANPA-'.Str::upper(Str::random(4))]);
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '1', '100', ['pajak' => [['kode' => $unruled->code, 'tarif' => '1', 'nilai' => '1']]])], ['perusahaan' => $this->company->public_id]));
    expect(purchaseAnswer()['code'])->toBe('BR-FIN-17');
});

it('attributes a non-stock line to its project at the save, changed only by CM-17\'s correction (L-18; CALC-09; criterion 8)', function () {
    $project = $this->world->project(['SERVICE'], '1');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line(Projects::product('SERVICE'), '2', '150000', ['jenis' => 'NONSTOCK'])], ['proyek' => $project->public_id]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED');
    $purchase = PurchaseWorld::latest($this->company->id);
    $line = PurchaseWorld::lines($purchase)[0];
    $rows = fn () => DB::table('cost_attributions')->where('purchase_line_id', $line->id)->where('bearer_project_id', $project->id)->sum('amount');

    expect($rows())->toEqual('300000.00')->and(purchaseBalance($line->id)->posted_goods_value)->toBe('300000.00');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'PRICE_CORRECTION', 'catatan' => RowHandle::of('purchase_lines', $line->id), 'nilai' => '250000']));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))->and($rows())->toEqual('250000.00')->and(purchaseBalance($line->id)->posted_goods_value)->toBe('250000.00')
        ->and(DB::table('audit_events')->where('action', 'PUR.CostCorrected')->count())->toBe(1);
});

it('requires a project and a goods line for a drop-ship line (WF-FUL-03; BR-INV-11; criterion 9)', function () {
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '1', '100', ['langsung' => true])], ['perusahaan' => $this->company->public_id]));
    expect(purchaseAnswer()['code'])->toBe('WF-FUL-03');

    $project = $this->world->project(['SERVICE'], '1');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line(Projects::product('SERVICE'), '1', '100', ['jenis' => 'NONSTOCK', 'langsung' => true])], ['proyek' => $project->public_id]));
    expect(purchaseAnswer()['code'])->toBe('WF-FUL-03');

    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '1', '100', ['langsung' => true])], ['proyek' => $project->public_id]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED')->and(PurchaseWorld::lines(PurchaseWorld::latest($this->company->id))[0]->delivery_mode)->toBe('DROPSHIP');
});

it('amends goods lines before the first effect, the stale one CONFLICT, afterwards REJECTED (L-23; ST-01; criterion 10)', function () {
    $bill = PurchaseWorld::bill($this->company->id);
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '100000'), PurchaseWorld::line(Projects::product(), '30', '100000')], ['biaya' => [['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '100000', 'dasar' => 'LINE_VALUE']]]);
    [$a, $b] = PurchaseWorld::lines($purchase);
    $handle = fn ($line) => RowHandle::of('purchase_lines', $line->id);
    $request = fn (int $version, string $qty) => ['command_id' => (string) Str::uuid(), 'lock_version' => $version, 'baris' => [
        ['handle' => $handle($a), ...PurchaseWorld::line($this->goods, $qty, '100000')],
        ['handle' => $handle($b), 'jenis' => 'GOODS', 'produk' => DB::table('products')->where('id', $b->product_id)->value('public_id'), 'satuan' => DB::table('units')->where('id', $b->entered_unit_id)->value('code'), 'jumlah' => '30', 'harga' => '100000'],
    ]];
    $charge = DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id');

    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", $request($purchase->lock_version, '20'));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))
        ->and(purchaseBalance($a->id)->ordered_qty_base)->toBe('20.000')->and(purchaseBalance($a->id)->goods_value)->toBe('2000000.00')
        ->and(DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $charge)->where('purchase_line_id', $a->id)->value('share_amount'))->toBe('40000.00')
        ->and(DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $charge)->where('purchase_line_id', $a->id)->value('allocation_version'))->toBe(2);

    // The other Admin's form still holds the first version.
    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", $request($purchase->lock_version, '15'));
    expect(purchaseAnswer()['kind'])->toBe('CONFLICT')->and(purchaseAnswer()['code'])->toBe('ST-01')->and(purchaseBalance($a->id)->ordered_qty_base)->toBe('20.000');

    $cost = PurchaseWorld::cost($this->company, $this->actor->id, $a->product_id);
    $cost->receive($a, '5');
    $purchase->refresh();
    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", $request($purchase->lock_version, '25'));
    expect(purchaseAnswer()['code'])->toBe('L-23');
});

it('cancels with nothing received, its number kept, every line closed, its PO voided, its non-stock HPP contra\'d and charges expensed in one transaction; with a receipt REJECTED (AX-12; CM-18; criteria 11, 12)', function () {
    $project = $this->world->project(['SERVICE'], '1');
    $bill = PurchaseWorld::bill($this->company->id);
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line(Projects::product('SERVICE'), '1', '300000', ['jenis' => 'NONSTOCK']), PurchaseWorld::line($this->goods, '10', '100000')], ['proyek' => $project->public_id, 'biaya' => [['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '130000', 'dasar' => 'LINE_VALUE']]]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $purchase = PurchaseWorld::latest($this->company->id);
    $number = $purchase->purchase_number;
    [$service, $goods] = PurchaseWorld::lines($purchase);
    $charge = DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id');

    expect(DB::table('cost_attributions')->where('purchase_line_id', $service->id)->sum('amount'))->toEqual('330000.00');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $purchase->refresh();

    expect($purchase->state)->toBe('CANCELLED')->and($purchase->purchase_number)->toBe($number)->and($purchase->cancelled_reason)->toBe('Salah catat di awal')->and($purchase->cancelled_by)->toBe($this->actor->id)
        ->and(purchaseBalance($goods->id)->closed_qty_base)->toBe('10.000')->and(purchaseBalance($service->id)->closed_qty_base)->toBe('1.000')
        ->and((float) DB::table('cost_attributions')->where('purchase_line_id', $service->id)->sum('amount'))->toEqual(0.0)
        ->and(purchaseBalance($service->id)->posted_goods_value)->toBe('0.00')
        ->and(DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->value('expensed_amount'))->toBe('130000.00')
        ->and(DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->value('allocated_amount'))->toBe('0.00');

    $received = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '100000')]);
    $line = PurchaseWorld::lines($received)[0];
    PurchaseWorld::cost($this->company, $this->actor->id, $line->product_id)->receive($line, '1');
    $this->actingAs($this->actor)->post("/pembelian/{$received->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['code'])->toBe('C-29')->and(purchaseAnswer()['state']['reason'])->toBe('received')->and($received->fresh()->state)->toBe('OPEN');

    // A cancelled purchase answers CONFLICT, its receipt fails against the closed guard.
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['kind'])->toBe('CONFLICT');
    expect(fn () => DB::table('purchase_line_balances')->where('purchase_line_id', $goods->id)->update(['normal_received_net_qty_base' => '1']))->toThrow(Exception::class);
});

it('closes a line ordered 100 with 60 received, 41 refused and 40 closed, its pending share re-spread over the 60, allocated + pending + expensed = the charge, the header "Sisa Ditutup" (AX-32; L-47; PX-06; C-21; C-29; criterion 13)', function () {
    $bill = PurchaseWorld::bill($this->company->id);
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '100', '1000')], ['biaya' => [['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '100000', 'dasar' => 'LINE_VALUE']]]);
    $line = PurchaseWorld::lines($purchase)[0];
    $charge = DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id');
    $cost = PurchaseWorld::cost($this->company, $this->actor->id, $line->product_id);
    $lot = $cost->receive($line, '60');
    $handle = RowHandle::of('purchase_lines', $line->id);

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", reasoned($this, ['baris' => [['baris' => $handle, 'jumlah' => '41']]]));
    expect(purchaseAnswer()['code'])->toBe('C-29')->and(purchaseAnswer()['state']['reason'])->toBe('above_open');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", reasoned($this, ['baris' => [['baris' => $handle, 'jumlah' => '40']]]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $balance = DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->first();

    expect($balance->allocated_amount)->toBe('100000.00')->and($balance->expensed_amount)->toBe('0.00')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe('160000.00')
        ->and(purchaseBalance($line->id)->closed_qty_base)->toBe('40.000')
        ->and($purchase->fresh()->state)->toBe('CLOSED')
        ->and(DB::table('purchase_line_closures')->where('purchase_line_id', $line->id)->value('reason'))->toBe('Salah catat di awal');
});

it('corrects after a receipt, listing what it re-attributes, Σ attributed = the corrected cost; before any receipt the line is simply edited (AX-34; CM-17; CM-35; C-57; challenge 2b; criterion 14)', function () {
    $bill = PurchaseWorld::bill($this->company->id, '200000');
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '1000')], ['biaya' => [['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '1000', 'dasar' => 'LINE_VALUE']]]);
    $line = PurchaseWorld::lines($purchase)[0];
    $handle = RowHandle::of('purchase_lines', $line->id);
    $chargeHandle = RowHandle::of('purchase_charges', (int) DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id'));

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'PRICE_CORRECTION', 'catatan' => $handle, 'nilai' => '12000']));
    expect(purchaseAnswer()['code'])->toBe('L-23')->and(purchaseAnswer()['state']['reason'])->toBe('edit_instead');

    $cost = PurchaseWorld::cost($this->company, $this->actor->id, $line->product_id);
    $project = $this->world->project(['SERVICE'], '1');
    $lot = $cost->receive($line, '10');
    $cost->dispatch($lot, '4', $project);
    $this->actingAs($this->actor)->get("/pembelian/{$purchase->public_id}")->assertInertia(fn ($page) => $page->component('PUR/Purchase', false)
        ->where('cascade.consumptions.0.project', $project->project_number)->where('cascade.consumptions.0.line', 1)->has('cascade.consumptions', 1)->has('cascade.lots', 1)->where('cascade.lots.0.line', 1)->where('cascade.projects', fn ($projects) => $projects->pluck('number')->contains($project->project_number)));

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'PRICE_CORRECTION', 'catatan' => $handle, 'nilai' => '12000']));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $held = DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
    $drawn = DB::table('cost_attributions')->whereIn('stock_consumption_id', DB::table('stock_consumptions')->where('lot_id', $lot)->pluck('id'))->sum('amount');
    expect(Money::of((string) $held)->plus(Money::of((string) $drawn))->toString())->toBe('13000.00')->and(purchaseBalance($line->id)->goods_value)->toBe('12000.00')->and(purchaseBalance($line->id)->posted_goods_value)->toBe('12000.00');

    // CM-17's charge amount, then above its bill.
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'CHARGE_CORRECTION', 'catatan' => $chargeHandle, 'nilai' => '1500']));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'CHARGE_CORRECTION', 'catatan' => $chargeHandle, 'nilai' => '900000']));
    expect(purchaseAnswer()['code'])->toBe('C-20');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'CHARGE_CORRECTION', 'catatan' => $chargeHandle, 'nilai' => '1500']));
    expect(purchaseAnswer()['code'])->toBe('AX-34');
});

it('shows the previous price of the supplier and item per company only with cost.view, no score or rank (SH-01; AC-02; PJ-09; criterion 15)', function () {
    savePurchase($this, [PurchaseWorld::line($this->goods, '10', '4321')]);
    $product = $this->goods['product']->public_id;
    $url = "/pembelian/baru?perusahaan={$this->company->public_id}&pemasok={$this->supplier->public_id}&harga={$product}";

    $this->actingAs($this->actor)->get($url)->assertInertia(fn ($page) => $page->component('PUR/RecordPurchase', false)->where("previous.{$product}.price", '4321.00')->where("previous.{$product}.date", $this->today)->etc());

    $other = InvoiceWorld::make();
    PurchaseWorld::numbered($other->company);
    $stranger = PurchaseWorld::actor($other->company);
    $this->actingAs($stranger)->get("/pembelian/baru?perusahaan={$other->company->public_id}&pemasok={$this->supplier->public_id}&harga={$product}")->assertInertia(fn ($page) => $page->where('previous', []));
});

it('keeps the purchase pages and controls behind their capabilities (PJ-18; IG-01; SM-04; SM-25; criterion 16)', function () {
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods)]);
    $viewer = User::query()->findOrFail(Authorization::admin([$this->company->id], ['cost.view'])->id);
    $blind = User::query()->findOrFail(Authorization::admin([$this->company->id], ['projects.view'])->id);

    $this->actingAs($blind)->get('/pembelian')->assertForbidden();
    $this->actingAs($blind)->get("/pembelian/{$purchase->public_id}")->assertForbidden();
    $this->actingAs($viewer)->get('/pembelian/baru')->assertForbidden();
    $this->actingAs($viewer)->get("/pembelian/{$purchase->public_id}")->assertInertia(fn ($page) => $page->component('PUR/Purchase', false)->where('can.cancel', false)->where('can.close', false)->where('can.correct', false)->where('can.amend', false)->where('disbursements', null)->etc());
    $this->actingAs($viewer)->post("/pembelian/{$purchase->public_id}/batalkan", reasoned($this))->assertForbidden();
    $this->actingAs($viewer)->post("/pembelian/{$purchase->public_id}/tutup-sisa", reasoned($this, ['baris' => []]))->assertForbidden();
    $this->actingAs($viewer)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this))->assertForbidden();
    $this->actingAs($this->actor)->get("/pembelian/{$purchase->public_id}")->assertInertia(fn ($page) => $page->where('can.cancel', true)->where('can.close', true)->where('can.correct', true)->where('can.amend', true)->etc());
});

it('opens the PO\'s draft from the purchase, DOC-02 and DOC-14 two layouts of one record, issuing changing no stock or cash (BR-DOC-04; L-26; OB-02; criterion 17)', function () {
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '3', '2500')]);

    foreach (['DOC-02', 'DOC-14'] as $type) {
        $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => $type, 'subjek' => ['purchase_id' => $purchase->public_id]])->assertRedirect();
    }

    $documents = DB::table('documents')->where('purchase_id', $purchase->id)->orderBy('id')->get();
    expect($documents->pluck('document_type_code')->all())->toBe(['DOC-02', 'DOC-14'])->and(DB::table('stock_movements')->count())->toBe(0)->and(DB::table('disbursements')->count())->toBe(0);

    $facts = app(PurchaseSubjects::class)->facts(new DocumentSubject('DOC-02', 'STANDARD', $this->company->id, ['purchase_id' => $purchase->id]));
    expect($facts->recipient['name'])->toBe($this->supplier->name)->and($facts->lines[0]['amount'])->toBe('7500.00')->and($facts->totals[0]['amount'])->toBe('7500.00');
});

it('replays each command by one command_id, a changed payload CONFLICT (CI-05; M-09; criterion 18)', function () {
    $request = PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $this->company->public_id]);
    $this->actingAs($this->actor)->post('/pembelian', $request);
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post('/pembelian', $request);
    expect(DB::table('purchases')->count())->toBe(1);
    $this->actingAs($this->actor)->post('/pembelian', [...$request, 'referensi' => 'lain']);
    expect(purchaseAnswer()['kind'])->toBe('CONFLICT')->and(DB::table('purchases')->count())->toBe(1);

    $purchase = PurchaseWorld::latest($this->company->id);
    $cancel = reasoned($this);
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", $cancel);
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", $cancel);
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED')->and(DB::table('audit_events')->where('action', 'PUR.PurchaseCancelled')->count())->toBe(1);
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", [...$cancel, 'alasan' => 'Alasan lain sama sekali']);
    expect(purchaseAnswer()['kind'])->toBe('CONFLICT');
});

it('records a late charge after a partial consumption with LATE_CHARGE draws, one with nothing received and nothing open expensed; corrects its basis from its own route (AX-29; CM-35; ROUTE-178; ROUTE-179; criterion 20)', function () {
    $a = $this->goods;
    $b = Projects::product();
    $purchase = savePurchase($this, [PurchaseWorld::line($a, '10', '1000'), PurchaseWorld::line($b, '10', '3000')]);
    [$lineA, $lineB] = PurchaseWorld::lines($purchase);
    $cost = PurchaseWorld::cost($this->company, $this->actor->id, $lineA->product_id);
    $project = $this->world->project(['SERVICE'], '1');
    $lot = $cost->receive($lineA, '10');
    $cost->dispatch($lot, '5', $project);
    $bill = PurchaseWorld::bill($this->company->id, '50000');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/biaya", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '4000', 'dasar' => 'LINE_VALUE']);
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $charge = (int) DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id');
    $balance = DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->first();
    expect($balance->allocated_amount)->toBe('1000.00')->and(DB::table('cost_attributions')->where('cause', 'LATE_CHARGE')->sum('amount'))->toEqual('500.00');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-dasar-alokasi", reasoned($this, ['catatan' => RowHandle::of('purchase_charges', $charge), 'dasar' => 'QUANTITY']));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $after = DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->first();
    expect($after->allocated_amount)->toBe('2000.00')->and(DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $charge)->where('purchase_line_id', $lineA->id)->value('allocation_version'))->toBe(2);

    // Nothing received, the remainder closed: a later charge has nowhere to go but an expense.
    $closed = savePurchase($this, [PurchaseWorld::line($this->goods, '5', '1000')]);
    $line = PurchaseWorld::lines($closed)[0];
    $this->actingAs($this->actor)->post("/pembelian/{$closed->public_id}/tutup-sisa", reasoned($this, ['baris' => [['baris' => RowHandle::of('purchase_lines', $line->id), 'jumlah' => '5']]]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->post("/pembelian/{$closed->public_id}/biaya", ['command_id' => (string) Str::uuid(), 'tanggal' => $this->today, 'tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '700', 'dasar' => 'LINE_VALUE']);
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    $late = (int) DB::table('purchase_charges')->where('purchase_id', $closed->id)->value('id');
    expect(DB::table('purchase_charge_balances')->where('purchase_charge_id', $late)->value('expensed_amount'))->toBe('700.00');
});

it('F1: marks a non-stock line, takes it back with its capability, the cancellation refused for a performed and for a paid line and allowed after an unpaid line\'s mark is taken back; no cost posted by the mark (ROUTE-180; AX-12; DIR-101)', function () {
    $project = $this->world->project(['SERVICE'], '1');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line(Projects::product('SERVICE'), '1', '50000', ['jenis' => 'NONSTOCK']), PurchaseWorld::line($this->goods, '2', '1000')], ['proyek' => $project->public_id]));
    $purchase = PurchaseWorld::latest($this->company->id);
    [$service, $goods] = PurchaseWorld::lines($purchase);
    $url = "/pembelian/{$purchase->public_id}/baris/".RowHandle::of('purchase_lines', $service->id).'/terlaksana';
    $costRows = fn () => DB::table('cost_attributions')->where('purchase_line_id', $service->id)->count();
    $rowsBefore = $costRows();
    $manageOnly = User::query()->findOrFail(Authorization::admin([$this->company->id], ['cost.view', 'purchase.manage'])->id);
    $correctOnly = User::query()->findOrFail(Authorization::admin([$this->company->id], ['cost.view', 'purchase.correct_cost'])->id);

    $this->actingAs($correctOnly)->post($url, ['command_id' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($manageOnly)->post($url, ['command_id' => (string) Str::uuid()]);
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))->and(DB::table('purchase_lines')->where('id', $service->id)->value('performed_at'))->not->toBeNull()->and($costRows())->toBe($rowsBefore);

    // A goods line is no non-stock line; L-23 sees no first effect from the mark.
    $this->actingAs($manageOnly)->post("/pembelian/{$purchase->public_id}/baris/".RowHandle::of('purchase_lines', $goods->id).'/terlaksana', ['command_id' => (string) Str::uuid()]);
    expect(purchaseAnswer()['code'])->toBe('NX-04')->and(PurchaseCorrections::hasFirstEffect($purchase->id))->toBeFalse();

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['code'])->toBe('C-29')->and(purchaseAnswer()['state']['reason'])->toBe('performed');

    $this->actingAs($manageOnly)->post($url, ['command_id' => (string) Str::uuid(), 'batalkan' => true, 'alasan' => 'Salah tandai'])->assertForbidden();
    $this->actingAs($correctOnly)->post($url, ['command_id' => (string) Str::uuid(), 'batalkan' => true])->assertSessionHasErrors('alasan');
    $this->actingAs($correctOnly)->post($url, ['command_id' => (string) Str::uuid(), 'batalkan' => true, 'alasan' => 'Salah tandai']);
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))->and(DB::table('purchase_lines')->where('id', $service->id)->value('performed_at'))->toBeNull()
        ->and(DB::table('audit_events')->where('action', 'PUR.LinePerformedTakenBack')->value('reason'))->toBe('Salah tandai');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))->and($purchase->fresh()->state)->toBe('CANCELLED');
    $this->actingAs($manageOnly)->post($url, ['command_id' => (string) Str::uuid()]);
    expect(purchaseAnswer()['code'])->toBe('NX-04');

    // Paid: a live disbursement citing the purchase, which names no line; the goods-only purchase is not guarded by it (DIR-101).
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line(Projects::product('SERVICE'), '1', '50000', ['jenis' => 'NONSTOCK'])], ['proyek' => $project->public_id]));
    $serviced = PurchaseWorld::latest($this->company->id);
    $account = CompanyBankAccountFactory::new()->createOne(['company_id' => $this->company->id]);
    DB::table('disbursements')->insert(['public_id' => (string) Str::uuid(), 'company_id' => $this->company->id, 'business_date' => $this->today, 'amount' => '100.00', 'kind' => 'PURCHASE_PAYMENT', 'company_bank_account_id' => $account->id, 'purchase_id' => $serviced->id, 'recorded_at' => now(), 'recorded_by' => $this->actor->id]);
    $this->actingAs($this->actor)->post("/pembelian/{$serviced->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['code'])->toBe('C-29')->and(purchaseAnswer()['state']['reason'])->toBe('paid')->and($serviced->fresh()->state)->toBe('OPEN');
});

it('reads PG-015 one company at a time and PG-011\'s Pengadaan through PUR, absent without cost.view (PF-13; CS-07; ROUTE-011)', function () {
    $project = $this->world->project(['WAREHOUSE'], '5');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '4', '100')], ['proyek' => $project->public_id]));
    $purchase = PurchaseWorld::latest($this->company->id);

    $this->actingAs($this->actor)->get('/pembelian')->assertInertia(fn ($page) => $page->component('PUR/Purchases', false)->where('rows.0.number', $purchase->purchase_number)->where('rows.0.project', $project->project_number)->where('rows.0.state', 'OPEN')->etc());
    $this->actingAs($this->actor)->get("/proyek/{$project->public_id}")->assertInertia(fn ($page) => $page->where('parts.procurement.purchases.0.number', $purchase->purchase_number)->where('parts.procurement.purchases.0.remaining', '4.000')->etc());

    $blind = User::query()->findOrFail(Authorization::admin([$this->company->id], ['projects.view'])->id);
    $this->actingAs($blind)->get("/proyek/{$project->public_id}")->assertInertia(fn ($page) => $page->where('parts', [])->etc());
});

it('reports a project\'s open remainder to predicate 8 until its closure (WF-PRJ-03; BR-PUR-04)', function () {
    $project = $this->world->project(['WAREHOUSE'], '5');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '4', '100')], ['proyek' => $project->public_id]));
    $purchase = PurchaseWorld::latest($this->company->id);
    $line = PurchaseWorld::lines($purchase)[0];
    $state = fn () => app(PurchasePredicate::class)->states([$project->id], true)[$project->id][Predicates::STOCK];

    expect($state()->items[0]->key)->toBe('projects.item.purchase_remainders');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", reasoned($this, ['baris' => [['baris' => RowHandle::of('purchase_lines', $line->id), 'jumlah' => '4']]]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED')->and($state()->items)->toBe([]);
});

it('CM-27: supersedes a closure with the exact inverse posting, both rows kept (DATABASE §4.6; TASK-035\'s kinds)', function () {
    $bill = PurchaseWorld::bill($this->company->id);
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '100', '1000')], ['biaya' => [['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '100000', 'dasar' => 'LINE_VALUE']]]);
    $line = PurchaseWorld::lines($purchase)[0];
    $charge = (int) DB::table('purchase_charges')->where('purchase_id', $purchase->id)->value('id');
    $cost = PurchaseWorld::cost($this->company, $this->actor->id, $line->product_id);
    $lot = $cost->receive($line, '60');
    $before = DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost');
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", reasoned($this, ['baris' => [['baris' => RowHandle::of('purchase_lines', $line->id), 'jumlah' => '40']]]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED');
    $closure = (int) DB::table('purchase_line_closures')->where('purchase_line_id', $line->id)->value('id');
    $actor = PurchaseWorld::actor($this->company, ['decisions.supersede']);
    $outcome = app(SupersedeRemainderClosure::class)->run((string) Str::uuid(), $actor->id, $purchase->fresh(), $closure, 'Sisa ternyata masih dikirim', $this->today, false);

    expect($outcome->kind)->toBe('COMMITTED', json_encode($outcome->state))
        ->and(purchaseBalance($line->id)->closed_qty_base)->toBe('0.000')->and($purchase->fresh()->state)->toBe('OPEN')
        ->and(DB::table('lot_cost_balances')->where('lot_id', $lot)->value('remaining_cost'))->toBe($before)
        ->and(DB::table('purchase_charge_balances')->where('purchase_charge_id', $charge)->value('allocated_amount'))->toBe('60000.00')
        ->and(DB::table('purchase_line_closures')->where('purchase_line_id', $line->id)->count())->toBe(2);
});

it('hears every module on predicate 8: a met purchase part never hides another module\'s open item (WF-PRJ-03; ER-06)', function () {
    $project = $this->world->project(['WAREHOUSE'], '5');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods, '4', '100')], ['proyek' => $project->public_id]));
    $line = PurchaseWorld::lines(PurchaseWorld::latest($this->company->id))[0];
    $reservation = new class implements CompletionPredicateProvider
    {
        public int $project = 0;

        public function predicates(): array
        {
            return [Predicates::STOCK];
        }

        public function states(array $projectIds, bool $money): array
        {
            return [$this->project => [Predicates::STOCK => new PredicateState(true, [new JourneyItem('projects.item.reservations_active', ['jumlah' => '1'], 'warehouse', true)])]];
        }
    };
    $reservation->project = $project->id;
    $this->app->instance('test.stock', $reservation);
    $this->app->tag(['test.stock'], CompletionPredicateProvider::TAG);
    $stock = fn () => app(ProjectJourneys::class)->predicates([$project->id], true)[$project->id][Predicates::STOCK];

    expect(array_map(fn ($item) => $item->key, $stock()->items))->toContain('projects.item.purchase_remainders')->toContain('projects.item.reservations_active');

    $purchase = PurchaseWorld::latest($this->company->id);
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", reasoned($this, ['baris' => [['baris' => RowHandle::of('purchase_lines', $line->id), 'jumlah' => '4']]]));
    expect(array_map(fn ($item) => $item->key, $stock()->items))->toBe(['projects.item.reservations_active']);
});

it('voids the issued PO in the cancellation\'s transaction (AX-12; DATABASE §25; criterion 11)', function () {
    $year = substr($this->today, 0, 4);
    Numbering::scheme($this->company, SequenceType::Doc02, "{$year}-01-01");
    Numbering::started($this->company, SequenceType::Doc02, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    DocumentTypeFactory::ensure('DOC-02');
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '3', '2500')]);
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-02', 'subjek' => ['purchase_id' => $purchase->public_id]])->assertRedirect();
    $document = Document::query()->where('purchase_id', $purchase->id)->firstOrFail();
    $draft = DocumentVersion::query()->where('document_id', $document->id)->where('state', 'DRAFT')->firstOrFail();
    $this->actingAs($this->actor)->post("/dokumen/{$document->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'lock_version' => $draft->lock_version, 'draf' => $draft->public_id, 'tanggal' => $this->today, 'tanggal_otomatis' => true]);
    expect(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('ISSUED', json_encode(purchaseAnswer()));

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))
        ->and(DB::table('document_versions')->where('document_id', $document->id)->value('state'))->toBe('VOIDED')
        ->and(DB::table('document_versions')->where('document_id', $document->id)->value('voided_reason'))->toBe('Salah catat di awal');

    // Nothing more is issued from a cancelled purchase (WF-DOC-01).
    DatabaseFactoriesDOCDocumentTypeFactory::ensure('DOC-14');
    TestsSupportNumbering::scheme($this->company, AppModulesORGEnumsSequenceType::Doc14, "{$year}-01-01");
    TestsSupportNumbering::started($this->company, AppModulesORGEnumsSequenceType::Doc14, CarbonCarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
    $this->actingAs($this->actor)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-14', 'subjek' => ['purchase_id' => $purchase->public_id]]);
    $order = AppModulesDOCModelsDocument::query()->where('purchase_id', $purchase->id)->where('document_type_code', 'DOC-14')->firstOrFail();
    $orderDraft = AppModulesDOCModelsDocumentVersion::query()->where('document_id', $order->id)->where('state', 'DRAFT')->firstOrFail();
    $this->actingAs($this->actor)->post("/dokumen/{$order->public_id}/terbitkan", ['command_id' => (string) Str::uuid(), 'lock_version' => $orderDraft->lock_version, 'draf' => $orderDraft->public_id, 'tanggal' => $this->today, 'tanggal_otomatis' => true]);
    expect(purchaseAnswer()['kind'])->toBe('REJECTED')->and(purchaseAnswer()['code'])->toBe('WF-DOC-01');
});

it('corrects the amount of a weighted charge by its kept values, and refuses an expensed charge by rule (CM-17; CM-35; DATABASE §6.3 step 2)', function () {
    $bill = PurchaseWorld::bill($this->company->id, '50000');
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '1000'), PurchaseWorld::line(Projects::product(), '10', '1000')], ['biaya' => [
        ['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '1000', 'dasar' => 'WEIGHT', 'nilai_dasar' => ['1', '3']],
        ['tagihan' => $bill, 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '100'],
    ]]);
    [$a, $b] = PurchaseWorld::lines($purchase);
    $weighted = (int) DB::table('purchase_charges')->where('purchase_id', $purchase->id)->where('classification', 'ATTRIBUTABLE')->value('id');
    $expensed = (int) DB::table('purchase_charges')->where('purchase_id', $purchase->id)->where('classification', 'NON_ATTRIBUTABLE')->value('id');
    $share = fn ($line) => DB::table('purchase_charge_share_balances')->where('purchase_charge_id', $weighted)->where('purchase_line_id', $line->id)->value('share_amount');
    expect($share($a))->toBe('250.00')->and($share($b))->toBe('750.00');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'CHARGE_CORRECTION', 'catatan' => RowHandle::of('purchase_charges', $weighted), 'nilai' => '2000']));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))->and($share($a))->toBe('500.00')->and($share($b))->toBe('1500.00');

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/koreksi-biaya", reasoned($this, ['jenis' => 'CHARGE_CORRECTION', 'catatan' => RowHandle::of('purchase_charges', $expensed), 'nilai' => '200']))->assertRedirect();
    expect(purchaseAnswer()['code'])->toBe('CM-35')->and(DB::table('purchase_line_adjustments')->where('purchase_charge_id', $expensed)->count())->toBe(0);
});

it('amends taxed lines, adds a taxed line and re-spreads two charges over them, the old figures on the audit event (INT-172; L-23; TASK-037 AUDIT)', function () {
    $tax = PurchaseWorld::tax($this->company->id, 'INPUT_TO_ACQUISITION_COST');
    $bill = PurchaseWorld::bill($this->company->id);
    $taxed = fn (string $amount) => ['pajak' => [['kode' => $tax, 'tarif' => '10', 'nilai' => $amount]]];
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '10', '100', $taxed('100'))], ['biaya' => [
        ['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '300', 'dasar' => 'LINE_VALUE'],
        ['tagihan' => $bill, 'klasifikasi' => 'ATTRIBUTABLE', 'jumlah' => '500', 'dasar' => 'QUANTITY'],
    ]]);
    $first = PurchaseWorld::lines($purchase)[0];
    $added = Projects::product();

    $this->actingAs($this->actor)->put("/pembelian/{$purchase->public_id}", ['command_id' => (string) Str::uuid(), 'lock_version' => $purchase->lock_version, 'baris' => [
        ['handle' => RowHandle::of('purchase_lines', $first->id), ...PurchaseWorld::line($this->goods, '20', '100', $taxed('200'))],
        PurchaseWorld::line($added, '20', '100', $taxed('200')),
    ]]);
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()));
    [$one, $two] = PurchaseWorld::lines($purchase->fresh());

    expect(DB::table('purchase_line_tax_components')->count())->toBe(2)
        ->and(purchaseBalance($one->id)->noncreditable_tax_value)->toBe('200.00')->and(purchaseBalance($two->id)->noncreditable_tax_value)->toBe('200.00')
        ->and(DB::table('purchase_charge_share_balances')->where('purchase_line_id', $two->id)->orderBy('purchase_charge_id')->pluck('share_amount')->all())->toBe(['150.00', '250.00']);

    expect(json_encode(DB::table('audit_events')->where('action', 'PUR.PurchaseAmended')->first()))->toContain('10.000')->toContain('1000.00');
});

it('cancels after a partial closure with nothing received, closed to the ordered quantity (AX-12 §7.2; criterion 12)', function () {
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods, '100', '10')]);
    $line = PurchaseWorld::lines($purchase)[0];
    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/tutup-sisa", reasoned($this, ['baris' => [['baris' => RowHandle::of('purchase_lines', $line->id), 'jumlah' => '40']]]));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED');
    $this->actingAs($this->actor)->get("/pembelian/{$purchase->public_id}")->assertInertia(fn ($page) => $page->where('can.cancel', true)->etc());

    $this->actingAs($this->actor)->post("/pembelian/{$purchase->public_id}/batalkan", reasoned($this));
    expect(purchaseAnswer()['kind'])->toBe('COMMITTED', json_encode(purchaseAnswer()))->and(purchaseBalance($line->id)->closed_qty_base)->toBe('100.000')->and($purchase->fresh()->state)->toBe('CANCELLED');
});

it('opens a supplementary purchase on its origin\'s company and supplier (criterion 10; ROUTE_CONTRACTS §4)', function () {
    $purchase = savePurchase($this, [PurchaseWorld::line($this->goods)]);
    $other = InvoiceWorld::make();
    $both = User::query()->findOrFail(Authorization::admin([$other->company->id, $this->company->id], PurchaseWorld::CAPABILITIES)->id);

    $this->actingAs($both)->get("/pembelian/baru?dari={$purchase->public_id}")->assertInertia(fn ($page) => $page->component('PUR/RecordPurchase', false)->where('company', $this->company->public_id)->where('supplement.number', $purchase->purchase_number)->where('supplement.supplier.id', $this->supplier->public_id)->etc());
});

it('names the bill\'s remaining figure in C-20 (L-43)', function () {
    $bill = PurchaseWorld::bill($this->company->id, '100');
    $this->actingAs($this->actor)->post('/pembelian', PurchaseWorld::payload($this->supplier, [PurchaseWorld::line($this->goods)], ['perusahaan' => $this->company->public_id, 'biaya' => [['tagihan' => $bill, 'klasifikasi' => 'NON_ATTRIBUTABLE', 'jumlah' => '500']]]));

    expect(purchaseAnswer()['code'])->toBe('C-20')->and(purchaseAnswer()['text'])->toContain('100');
});
