<?php

use App\Modules\ADM\Queries\ProjectChecklist;
use App\Modules\PRJ\Data\ProjectRef;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Administration;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-035; PERMISSIONS_MATRIX §4 `requirements.manage`, `requirements.waive`, OWNER_ONLY
// `requirements.set_waiver_eligibility` and `requirements.relax`, `decisions.supersede`; W4-03, W4-04, W4-15, W4-18,
// W4-24; §7.2 rows 96–98; OD-05; OD-06; OD-15; IP-21; AZ-01; AZ-11; SM-01; SM-04): each command only with its
// capability, the Owner's answering an Admin as a missing page; another company's project, requirement, document or
// evidence unknown; the checklist's controls present with their capability and absent without, waiver eligibility the
// Owner's to read; PG-039 by `projects.view`.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->creator = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view']);
    $this->project = Administration::project($this->creator->id, $this->company);
    $this->item = Administration::requirement($this->project, ['waiver_eligible' => true]);
    $this->path = "/proyek/{$this->project->public_id}/persyaratan";
    $this->handle = Administration::handle($this->item);
});

afterEach(fn () => @unlink($this->hotFile));

function requirementAccessAdd(object $test, object $actor, ?string $path = null): TestResponse
{
    return $test->actingAs($actor)->post($path ?? $test->path, ['command_id' => (string) Str::uuid(), 'jenis' => 'FREE_TEXT', 'uraian' => 'Foto lokasi', 'wajib' => true, 'cara' => 'UPLOADED', 'asli_klien' => false]);
}

it('runs each command only with its capability, the Owner\'s answering an Admin as a missing page (ROUTE-164–ROUTE-169; SM-04; OD-05; OD-06; IP-21)', function () {
    $viewer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'evidence.view']);
    $manager = Authorization::admin([$this->company->id], ['projects.view', 'requirements.manage', 'requirements.waive']);

    requirementAccessAdd($this, $viewer)->assertForbidden();
    $this->actingAs($viewer)->post("{$this->path}/{$this->handle}/tautkan", ['command_id' => (string) Str::uuid(), 'bukti' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($viewer)->post("{$this->path}/{$this->handle}/tidak-berlaku", ['command_id' => (string) Str::uuid(), 'alasan' => 'Uji'])->assertForbidden();
    $this->actingAs($manager)->post("{$this->path}/{$this->handle}/kelayakan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'layak' => false])->assertNotFound();
    $this->actingAs($manager)->post("{$this->path}/{$this->handle}/longgarkan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'hapus' => true, 'alasan' => 'Uji'])->assertNotFound();
    $this->actingAs($manager)->post('/keputusan/'.str_repeat('a', 32).'/ganti', ['command_id' => (string) Str::uuid(), 'proyek' => $this->project->public_id, 'alasan' => 'Uji'])->assertForbidden();

    requirementAccessAdd($this, $manager);
    expect(DB::table('admin_requirements')->where('project_id', $this->project->id)->count())->toBe(2);
    $owner = Authorization::owner();
    $this->actingAs($owner)->post("{$this->path}/{$this->handle}/kelayakan", ['command_id' => (string) Str::uuid(), 'lock_version' => 0, 'layak' => false])->assertRedirect();
    expect((bool) DB::table('admin_requirements')->where('id', $this->item)->value('waiver_eligible'))->toBeFalse();
});

it('answers another company\'s project, requirement, document and evidence as unknown (AZ-11; CS-03; CS-04; SM-01)', function () {
    $outsider = Authorization::admin([Projects::company()->id], ['projects.view', 'requirements.manage', 'requirements.waive', 'decisions.supersede', 'evidence.view']);
    requirementAccessAdd($this, $outsider)->assertNotFound();
    $this->actingAs($outsider)->post("{$this->path}/{$this->handle}/tidak-berlaku", ['command_id' => (string) Str::uuid(), 'alasan' => 'Uji'])->assertNotFound();

    $manager = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'evidence.view', 'requirements.manage']);
    // A requirement of another project of the same company is no requirement of this one.
    $other = Administration::project($this->creator->id, $this->company);
    $this->actingAs($manager)->post("{$this->path}/".Administration::handle(Administration::requirement($other)).'/tautkan', ['command_id' => (string) Str::uuid(), 'bukti' => (string) Str::uuid()])->assertNotFound();

    // A foreign document or evidence fails at its field exactly like a nonexistent one.
    $foreign = Projects::company();
    $foreignProject = Administration::project(Authorization::admin([$foreign->id], ['projects.view', 'projects.manage', 'finance.view'])->id, $foreign);
    $this->actingAs($manager)->post("{$this->path}/{$this->handle}/tautkan", ['command_id' => (string) Str::uuid(), 'dokumen' => Administration::publicIdOf(Administration::document($foreignProject))])->assertSessionHasErrors('dokumen');
    $this->actingAs($manager)->post("{$this->path}/{$this->handle}/tautkan", ['command_id' => (string) Str::uuid(), 'dokumen' => (string) Str::uuid()])->assertSessionHasErrors('dokumen');
    $this->actingAs($manager)->post("{$this->path}/{$this->handle}/tautkan", ['command_id' => (string) Str::uuid(), 'bukti' => Administration::evidence($foreign)->public_id])->assertSessionHasErrors('bukti');

    expect(DB::table('admin_requirement_links')->count())->toBe(0)->and(DB::table('admin_requirements')->where('project_id', $this->project->id)->count())->toBe(1);
});

it('shows the checklist\'s controls with their capability and waiver eligibility to the Owner only (AZ-01; row 96; OD-05; J-ADM-01)', function () {
    $checklist = fn (object $viewer): array => app(ProjectChecklist::class)->checklist($viewer->id, new ProjectRef($this->project->id, $this->company->id, $this->project->public_id)) ?? [];

    $admin = $checklist(Authorization::admin([$this->company->id], ['projects.view', 'requirements.manage', 'requirements.waive']));
    expect($admin['can'])->toBe(['manage' => true, 'waive' => true, 'owner' => false, 'relax' => false, 'supersede' => false])
        ->and($admin['items'][0])->not->toHaveKey('waiver_eligible')->and($admin['items'][0]['may_waive'])->toBeTrue()
        ->and($admin['options'])->not->toBeNull();

    $reader = $checklist(Authorization::admin([$this->company->id], ['projects.view']));
    expect($reader['can'])->toBe(['manage' => false, 'waive' => false, 'owner' => false, 'relax' => false, 'supersede' => false])
        ->and($reader['items'][0]['may_waive'])->toBeFalse()->and($reader['options'])->toBeNull();

    $owner = $checklist(Authorization::owner());
    expect($owner['can'])->toMatchArray(['owner' => true, 'relax' => true])->and($owner['items'][0]['waiver_eligible'])->toBeTrue();

    expect(app(ProjectChecklist::class)->checklist(Authorization::admin([Projects::company()->id], ['projects.view'])->id, new ProjectRef($this->project->id, $this->company->id, $this->project->public_id)))->toBeNull();
});

it('opens PG-039 with projects.view, listing only the companies in scope (ROUTE-039; CS-07; CS-08; SM-04)', function () {
    $this->actingAs(Authorization::admin([$this->company->id], ['finance.view']))->get('/dokumen/administrasi')->assertForbidden();

    $foreign = Projects::company();
    $foreignProject = Administration::project(Authorization::admin([$foreign->id], ['projects.view', 'projects.manage', 'finance.view'])->id, $foreign);
    Administration::requirement($foreignProject);
    $viewer = Authorization::admin([$this->company->id], ['projects.view']);

    $this->actingAs($viewer)->get('/dokumen/administrasi?cabang='.$foreign->public_id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ADM/AdministrativeDocuments', false)
        ->where('branch', $this->company->public_id)
        ->has('branches', 1)
        ->has('rows', 1)
        ->where('rows.0.project.id', $this->project->public_id)
        ->where('rows.0.handle', RowHandle::of('admin_requirements', $this->item))
        ->where('can', ['waive' => false, 'relax' => false, 'owner' => false])
        ->missing('rows.0.waiver_eligible'));
});
