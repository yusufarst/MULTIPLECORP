<?php

use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PTY\Models\ClientOrganization;
use App\Modules\PTY\Models\ClientUnit;
use App\Modules\PTY\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-026; PJ-09; PJ-10; CS-05–CS-07; UXN-23; SM-01; SM-12; SM-25; criterion 6): the party masters'
// S0 names to every active account; a client's NPWP, units, addresses and PICs to `projects.view` with a company in
// scope, a supplier's details to `projects.view` or `cost.view`, its price history to `cost.view`; a client's related
// projects only those in scope, with no count; every command `parties.manage`, refused without it and writing nothing.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->inside = Company::factory()->create();
    $this->outside = Company::factory()->create();
    $this->client = ClientOrganization::factory()->create(['name' => 'Sekolah Bersama', 'npwp' => '01.234.567.8-901.000']);
    ClientUnit::factory()->for($this->client, 'organization')->create(['name' => 'Tata Usaha']);
    $this->supplier = Supplier::factory()->create(['npwp' => '09.876.543.2-109.000']);
});

afterEach(fn () => @unlink($this->hotFile));

it('shows every active account the S0 names and nothing more (criterion 6; RD-01; PJ-10)', function () {
    $plain = Authorization::admin([$this->inside->id], []);

    $this->actingAs($plain)->get('/data-master/klien')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('list.rows.0.name', 'Sekolah Bersama')->where('can', ['manage' => false, 'relations' => false]));
    $this->actingAs($plain)->get("/data-master/klien?klien={$this->client->public_id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('client.name', 'Sekolah Bersama')->missing('client.npwp')->missing('client.units')->missing('client.projects'));
    $this->actingAs($plain)->get("/data-master/pemasok?pemasok={$this->supplier->public_id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('supplier.name', $this->supplier->name)->missing('supplier.details')->missing('supplier.prices'));
});

it('shows a client\'s relations to projects.view and a supplier\'s details to projects.view or cost.view, the price history to cost.view (PJ-09; PJ-10)', function () {
    $projects = Authorization::admin([$this->inside->id], ['projects.view']);
    $cost = Authorization::admin([$this->inside->id], ['cost.view']);

    $this->actingAs($projects)->get("/data-master/klien?klien={$this->client->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('client.npwp', '01.234.567.8-901.000')->where('client.units.0.name', 'Tata Usaha'));
    $this->actingAs($projects)->get("/data-master/pemasok?pemasok={$this->supplier->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('supplier.details.npwp', '09.876.543.2-109.000')->missing('supplier.prices'));
    $this->actingAs($cost)->get("/data-master/pemasok?pemasok={$this->supplier->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('supplier.details')->has('supplier.prices'));
    $this->actingAs($cost)->get("/data-master/klien?klien={$this->client->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('client.units'));
});

it('lists a client\'s related projects in scope only, with no count of the others (criterion 6; UXN-23; SM-12; CS-07)', function () {
    $mine = Project::factory()->for($this->inside)->create(['client_organization_id' => $this->client->id, 'title' => 'Pengadaan Kursi']);
    Project::factory()->for($this->outside)->count(3)->create(['client_organization_id' => $this->client->id]);
    $viewer = Authorization::admin([$this->inside->id], ['projects.view']);

    $this->actingAs($viewer)->get("/data-master/klien?klien={$this->client->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('client.projects.rows', [[
            'id' => $mine->public_id, 'number' => $mine->project_number, 'title' => 'Pengadaan Kursi', 'state' => 'DRAFT', 'company' => $this->inside->code,
        ]])->where('client.projects.more', false)->missing('client.projects.count')->missing('client.projects.total'));
});

it('refuses every command without parties.manage and writes nothing (TL-06; ROUTE-141–ROUTE-144)', function (string $method, string $path, array $body) {
    $viewer = Authorization::admin([$this->inside->id], ['projects.view', 'cost.view']);
    $before = [DB::table('client_organizations')->count(), DB::table('client_units')->count(), DB::table('suppliers')->count(), DB::table('command_log')->count()];
    $path = str_replace(['{klien}', '{pemasok}'], [$this->client->public_id, $this->supplier->public_id], $path);

    $response = $this->actingAs($viewer)->call($method, $path, ['command_id' => (string) Str::uuid(), ...$body]);

    expect($response->status())->toBeIn([403, 404])
        ->and([DB::table('client_organizations')->count(), DB::table('client_units')->count(), DB::table('suppliers')->count(), DB::table('command_log')->count()])->toBe($before);
})->with([
    ['POST', '/data-master/klien', ['kind' => 'organization', 'name' => 'Klien Baru']],
    ['PUT', '/data-master/klien/{klien}', ['action' => 'archive', 'kind' => 'organization', 'lock_version' => 0, 'reason' => 'x']],
    ['POST', '/data-master/pemasok', ['name' => 'Pemasok Baru']],
    ['PUT', '/data-master/pemasok/{pemasok}', ['action' => 'archive', 'lock_version' => 0, 'reason' => 'x']],
]);

it('re-authorizes a command at commit: parties.manage withdrawn after the page was read refuses it (CI-05; RV-01)', function () {
    $admin = Authorization::admin([$this->inside->id], ['projects.view', 'parties.manage']);
    Authorization::revoke($admin, 'parties.manage');

    $this->actingAs($admin)->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), 'name' => 'Pemasok Tanpa Hak'])->assertForbidden();
    expect(Supplier::query()->where('name', 'Pemasok Tanpa Hak')->exists())->toBeFalse();
});

it('drops parties.manage without a company in scope, so a scope-less account commands nothing (CS-05)', function () {
    $scopeless = Authorization::admin([], ['projects.view', 'parties.manage']);

    $this->actingAs($scopeless)->post('/data-master/pemasok', ['command_id' => (string) Str::uuid(), 'name' => 'Pemasok Tanpa Cakupan'])->assertForbidden();
    $this->actingAs($scopeless)->get('/data-master/pemasok')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can.manage', false));
});
