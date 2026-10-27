<?php

use App\Modules\CAT\Models\Product;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientAddress;
use App\Modules\PTY\Models\ClientContact;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Models\Supplier;
use App\Modules\PUR\Models\Purchase;
use App\Modules\PUR\Models\PurchaseLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-026; PERFORMANCE_TARGETS PFM-01, PFM-02, PFM-20): PG-054's and PG-055's lists and one record's detail in a
// number of statements that never depends on the rows they show — units, addresses, PICs, related projects, prices —;
// an NX-01 command within its budget.

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

function partyStatements(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('renders PG-054\'s list and a client in the same number of statements for few rows and many (PFM-01; PFM-02)', function () {
    $client = ClientOrganization::factory()->create();
    $unit = ClientUnit::factory()->for($client, 'organization')->create();
    $page = fn (string $path) => partyStatements(fn () => $this->actingAs($this->admin)->get($path)->assertOk());
    $detail = "/data-master/klien?klien={$client->public_id}";
    $page('/data-master/klien');
    [$list, $one] = [$page('/data-master/klien'), $page($detail)];

    ClientOrganization::factory()->count(30)->create();
    foreach (ClientUnit::factory()->for($client, 'organization')->count(10)->create() as $more) {
        ClientAddress::factory()->for($more, 'unit')->count(2)->create();
        ClientContact::factory()->for($more, 'unit')->count(2)->create();
    }
    ClientAddress::factory()->for($unit, 'unit')->create();

    expect($page('/data-master/klien'))->toBe($list)->and($page($detail))->toBe($one)->and($one)->toBeLessThanOrEqual(40);
});

it('renders PG-055\'s list and a supplier with its price history in the same number of statements whatever the rows (PFM-01; PFM-02)', function () {
    $supplier = Supplier::factory()->create();
    $page = fn (string $path) => partyStatements(fn () => $this->actingAs($this->admin)->get($path)->assertOk());
    $detail = "/data-master/pemasok?pemasok={$supplier->public_id}";
    $page('/data-master/pemasok');
    [$list, $one] = [$page('/data-master/pemasok'), $page($detail)];

    Supplier::factory()->count(30)->create();
    foreach (range(1, 5) as $number) {
        PurchaseLine::factory()->for(Purchase::factory()->for($this->company)->for($supplier)->create())->for(Product::factory()->create())->create();
    }

    expect($page('/data-master/pemasok'))->toBe($list)->and($page($detail))->toBe($one)->and($one)->toBeLessThanOrEqual(40);
});

it('runs a creation with its first unit, address and PIC within an NX-01 command\'s budget (PFM-20)', function () {
    $count = partyStatements(fn () => $this->actingAs($this->admin)->post('/data-master/klien', ['command_id' => (string) Str::uuid(), 'kind' => 'organization',
        'name' => 'Klien Anggaran', 'unit_name' => 'Unit', 'address_lines' => 'Jl. Anggaran', 'contact_name' => 'PIC'])->assertRedirect());

    expect($count)->toBeLessThanOrEqual(45);
});
