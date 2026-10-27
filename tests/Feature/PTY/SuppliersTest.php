<?php

use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PTY\Queries\PartyLookup;
use App\Modules\PUR\Models\Purchase;
use App\Support\Command\Outcome;
use App\Support\Search\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-026; ROUTE-055, ROUTE-143, ROUTE-144; criteria 3, 4, 5): a supplier created directly — no comparison
// step —, a likely duplicate name answered with its list first; its identity changed at the version read, a stale edit
// CONFLICT, while a purchase keeps the supplier it was committed with; an archive that blocks new purchases only.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'cost.view', 'parties.manage']);
});

afterEach(fn () => @unlink($this->hotFile));

function supplierAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('creates a supplier directly, a likely duplicate name answered with its list first (ROUTE-143; J-MD-03; PF-21)', function () {
    $body = ['name' => 'Toko  Sinar Jaya', 'npwp' => '01.234.567.8-901.000', 'address_lines' => 'Jl. Magelang 5', 'city' => 'Sleman', 'phone' => '0274 123456', 'email' => 'sinar@example.test'];

    $this->actingAs($this->admin)->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), ...$body])->assertRedirect();
    $supplier = Supplier::query()->sole();
    expect(supplierAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($supplier->name)->toBe('Toko Sinar Jaya')->and($supplier->city)->toBe('Sleman')
        ->and(DB::table('audit_events')->where('action', 'PTY.SupplierCreated')->value('authority_class'))->toBe('ADM');

    $this->actingAs($this->admin)->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), 'name' => 'toko sinar'])->assertRedirect();
    expect(supplierAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'PF-21'])->and(supplierAnswer()['state']['matches'][0]['id'])->toBe($supplier->public_id);

    $this->actingAs($this->admin)->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), 'name' => 'Toko Sinar', 'acknowledged' => [$supplier->public_id], 'reason' => 'Toko berbeda'])->assertRedirect();
    expect(supplierAnswer()['kind'])->toBe(Outcome::COMMITTED)->and(Supplier::query()->count())->toBe(2);

    $this->actingAs($this->admin)->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), 'name' => '', 'email' => 'x'])->assertSessionHasErrors(['name', 'email']);
});

it('changes a supplier at the version read, a stale edit CONFLICT, a purchase keeping its snapshot (criterion 3; ST-01; BR-SN-01)', function () {
    $supplier = Supplier::factory()->create(['name' => 'CV Lama', 'city' => 'Bantul'])->refresh();
    $purchase = Purchase::factory()->for($this->company)->for($supplier)->create(['supplier_snapshot' => ['nama' => 'CV Lama', 'alamat' => 'Bantul']]);
    $edit = fn (int $version, string $name) => $this->actingAs($this->admin)->put("/data-master/pemasok/{$supplier->public_id}", [
        'command_id' => (string) Str::uuid(), 'action' => 'change', 'lock_version' => $version, 'name' => $name, 'city' => 'Kulon Progo']);

    $edit(0, 'CV Baru')->assertRedirect();
    expect(supplierAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($supplier->refresh()->name)->toBe('CV Baru')->and($supplier->city)->toBe('Kulon Progo')
        ->and(DB::table('purchases')->where('id', $purchase->id)->value('supplier_snapshot'))->toContain('CV Lama');

    $edit(0, 'CV Basi')->assertRedirect();
    expect(supplierAnswer())->toMatchArray(['kind' => Outcome::CONFLICT, 'cause' => 'stale'])->and($supplier->refresh()->name)->toBe('CV Baru');
    $this->actingAs($this->admin)->put('/data-master/pemasok/'.Str::uuid(), ['command_id' => (string) Str::uuid(), 'action' => 'change', 'lock_version' => 0, 'name' => 'X'])->assertNotFound();
});

it('archives a supplier, no longer offered for a new purchase while its open purchase continues (criterion 4; WF-MD-03)', function () {
    $supplier = Supplier::factory()->create(['name' => 'Arsip Pemasok Uji'])->refresh();
    $open = Purchase::factory()->for($this->company)->for($supplier)->create();

    $this->actingAs($this->admin)->put("/data-master/pemasok/{$supplier->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'archive', 'lock_version' => 0, 'reason' => 'Tutup'])->assertRedirect();
    expect(supplierAnswer()['kind'])->toBe(Outcome::COMMITTED)->and($supplier->refresh()->is_active)->toBeFalse()
        ->and(app(PartyLookup::class)->suppliers(new Term('Arsip Pemasok'), 20)['rows'])->toBe([])
        ->and(DB::table('purchases')->where('id', $open->id)->value('state'))->toBe('OPEN');

    $this->actingAs($this->admin)->put("/data-master/pemasok/{$supplier->public_id}", ['command_id' => (string) Str::uuid(), 'action' => 'change', 'lock_version' => 1, 'name' => 'Hidup Lagi'])->assertRedirect();
    expect(supplierAnswer())->toMatchArray(['kind' => Outcome::REJECTED, 'code' => 'WF-MD-03'])->and($supplier->refresh()->name)->toBe('Arsip Pemasok Uji');
});

it('renders PG-055 with the supplier chosen directly — no comparison, scoring or ranking (criterion 5; CAP-02)', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->admin)->get("/data-master/pemasok?pemasok={$supplier->public_id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('PTY/Suppliers')->where('supplier.id', $supplier->public_id)
            ->has('supplier.details')->has('supplier.prices')->missing('supplier.score')->missing('supplier.ranking')->missing('supplier.comparison'));
});
