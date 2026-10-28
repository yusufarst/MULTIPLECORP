<?php

use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Support\RowHandle;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-032; PERMISSIONS_MATRIX §4, §7.2 rows 28–38, PJ-12, PJ-13, XL-4; AZ-11; SM-01; SM-04; SM-25;
// criteria 2, 8, 10, 12): the views by `projects.view`, the form and its commands by `projects.manage` — positive and
// denial cases —, the controls present with their capability and absent without; another company's project unknown,
// its forged identifier answered like a missing one; prices and the financial position only with `finance.view`; a
// linked project outside scope named only as another company's.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->manager = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view']);
    $this->actingAs($this->manager)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods], ['pic' => null]));
    $this->project = Project::query()->sole();
});

afterEach(fn () => @unlink($this->hotFile));

it('opens PG-010 and PG-011 with projects.view only, "Buat Proyek" and Tindakan Lain with projects.manage only (ROUTE-010, ROUTE-011; AZ-01)', function () {
    $this->actingAs(Authorization::admin([$this->company->id], ['finance.view']))->get('/proyek')->assertForbidden();
    $this->actingAs(Authorization::admin([$this->company->id], ['finance.view']))->get("/proyek/{$this->project->public_id}")->assertForbidden();

    $viewer = Authorization::admin([$this->company->id], ['projects.view']);
    $this->actingAs($viewer)->get('/proyek')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('PRJ/Projects')->where('can.create', false)->has('rows', 1));
    $this->actingAs($viewer)->get("/proyek/{$this->project->public_id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can', ['edit' => false, 'discard' => false]));
    $this->actingAs($this->manager)->get("/proyek/{$this->project->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can', ['edit' => true, 'discard' => true]));
});

it('runs PG-013 and its three commands with projects.manage only, writing nothing otherwise (ROUTE-013, ROUTE-151–ROUTE-153; SM-04)', function () {
    $viewer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view']);

    $this->actingAs($viewer)->get('/proyek/baru')->assertForbidden();
    $this->actingAs($viewer)->get("/proyek/{$this->project->public_id}/ubah")->assertForbidden();
    $this->actingAs($viewer)->post('/proyek', Projects::payload($this->company, $this->client, [$this->goods]))->assertForbidden();
    $this->actingAs($viewer)->put("/proyek/{$this->project->public_id}", Projects::payload($this->company, $this->client, [$this->goods], ['lock_version' => 0]))->assertForbidden();
    $this->actingAs($viewer)->post("/proyek/{$this->project->public_id}/hapus-draf", ['command_id' => (string) Str::uuid()])->assertForbidden();

    expect(Project::query()->count())->toBe(1)->and(DB::table('command_log')->where('command_type', 'like', 'PRJ.%')->count())->toBe(1);
});

it('answers another company\'s project and a forged identifier alike, on the views and the commands (SM-01; AZ-11; CS-04)', function () {
    $stranger = Authorization::admin([Company::factory()->create()->id], ['projects.view', 'projects.manage']);
    $forged = (string) Str::uuid();

    foreach ([$this->project->public_id, $forged] as $id) {
        $this->actingAs($stranger)->get("/proyek/{$id}")->assertNotFound();
        $this->actingAs($stranger)->get("/proyek/{$id}/ubah")->assertNotFound();
        $this->actingAs($stranger)->put("/proyek/{$id}", Projects::payload($this->company, $this->client, [$this->goods], ['lock_version' => 0]))->assertNotFound();
        $this->actingAs($stranger)->post("/proyek/{$id}/hapus-draf", ['command_id' => (string) Str::uuid()])->assertNotFound();
    }

    $this->actingAs($stranger)->get('/proyek')->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
    expect($this->project->fresh())->not->toBeNull();
});

it('shows prices, SIPLAH amounts and the financial position only with finance.view, never as zero without it (PJ-12; SM-25; criteria 10, 12)', function () {
    $plain = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage']);

    $this->actingAs($this->manager)->get("/proyek/{$this->project->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('project.lines.0.price', '15000.00')->where('financial', ['confirmed' => '0.00', 'invoiced' => '0.00']));
    $this->actingAs($plain)->get("/proyek/{$this->project->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('project.lines.0.price')->where('financial', null));
    $this->actingAs($plain)->get("/proyek/{$this->project->public_id}/ubah")
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('project.lines.0.price')->where('companies.0.money', false)->where('companies.0.taxes', []));
});

it('keeps a line\'s price when an Admin without finance.view amends the draft (PJ-12; criterion 12)', function () {
    $plain = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage']);
    $line = DB::table('project_lines')->where('project_id', $this->project->id)->sole();
    $this->actingAs($plain)->put("/proyek/{$this->project->public_id}", Projects::payload($this->company, $this->client, [], [
        'pic' => null, 'lock_version' => $this->project->lock_version, 'judul' => 'Judul diubah',
        'baris' => [['baris' => RowHandle::of('project_lines', (int) $line->id), 'produk' => $this->goods['product']->public_id, 'satuan' => $this->goods['base']->code, 'jumlah' => '4', 'harga' => '1', 'mode' => 'WAREHOUSE']],
    ]));

    expect(DB::table('project_lines')->where('id', $line->id)->first(['entered_qty', 'unit_price']))->toEqual((object) ['entered_qty' => '4.000', 'unit_price' => '15000.00'])
        ->and($this->project->fresh()?->title)->toBe('Judul diubah');
});

it('names a linked project outside the viewer\'s scope only as another company\'s, with the viewer\'s own reason (XL-4)', function () {
    $other = Projects::company();
    $partner = ProjectFactory::new()->createOne(['company_id' => $other->id, 'period_key' => '2019']);
    DB::table('projects')->where('id', $this->project->id)->update(['linked_project_id' => $partner->id, 'link_reason' => 'Pengadaan bersama']);
    $both = Authorization::admin([$this->company->id, $other->id], ['projects.view']);

    $this->actingAs(Authorization::admin([$this->company->id], ['projects.view']))->get("/proyek/{$this->project->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('project.link', ['id' => null, 'number' => null, 'title' => null, 'visible' => false, 'reason' => 'Pengadaan bersama']));
    $this->actingAs($both)->get("/proyek/{$this->project->public_id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('project.link.id', $partner->public_id)->where('project.link.visible', true));
});
