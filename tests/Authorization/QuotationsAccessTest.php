<?php

use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Services\QuotationRecords;
use App\Support\BusinessDate;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-06 and TL-14 (TASK-033; PERMISSIONS_MATRIX §4 `quotation.manage`, `projects.confirm`, §7.2 rows 33–34, PJ-15; RG-06;
// AZ-11; SM-04; SM-25; criterion 9): the draft, the issue and the revision only with `quotation.manage` and its
// prerequisite `finance.view`; the approval only with `projects.confirm`, the rejection only with `quotation.manage`;
// another company's project unknown; PG-014's controls present with their capability and absent without; the values and
// DOC-01's paper only with `finance.view`.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-01');
    $this->company = Projects::company();
    $this->manager = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'quotation.manage']);
    expect(Projects::create($this->manager->id, $this->company, Projects::client(), [Projects::product()]))->toStartWith('COMMITTED');
    $this->project = Project::query()->sole();
    $this->path = "/proyek/{$this->project->public_id}/penawaran";
});

afterEach(fn () => @unlink($this->hotFile));

it('writes the draft, the issue and the revision only with quotation.manage and finance.view, nothing otherwise (ROUTE-154–ROUTE-156; SM-04)', function () {
    $command = fn (): array => ['command_id' => (string) Str::uuid(), 'revisi' => (string) Str::uuid(), 'lock_version' => 0, 'tanggal' => BusinessDate::today()];

    foreach ([['projects.view', 'finance.view', 'projects.confirm'], ['projects.view', 'quotation.manage']] as $capabilities) {
        $actor = Authorization::admin([$this->company->id], $capabilities);
        $this->actingAs($actor)->post($this->path, ['command_id' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs($actor)->post("{$this->path}/terbitkan", $command())->assertForbidden();
        $this->actingAs($actor)->post("{$this->path}/revisi", $command())->assertForbidden();
    }

    expect(DB::table('quotation_revisions')->count())->toBe(0)->and(DB::table('command_log')->where('command_type', 'like', 'PRJ.%Quotation%')->count())->toBe(0);

    $this->actingAs($this->manager)->post($this->path, ['command_id' => (string) Str::uuid()])->assertRedirect($this->path);
    expect(DB::table('quotation_revisions')->count())->toBe(1);
});

it('answers another company\'s project and a forged identifier alike, unknown (AZ-11; CS-03)', function () {
    $outsider = Authorization::admin([Projects::company()->id], ['projects.view', 'finance.view', 'quotation.manage', 'projects.confirm']);

    $this->actingAs($outsider)->get($this->path)->assertNotFound();
    $this->actingAs($outsider)->post($this->path, ['command_id' => (string) Str::uuid()])->assertNotFound();
    $this->actingAs($outsider)->post("{$this->path}/jawaban", ['command_id' => (string) Str::uuid(), 'revisi' => (string) Str::uuid(), 'jawaban' => 'SETUJU', 'tanggal' => BusinessDate::today()])->assertNotFound();
    $this->actingAs($this->manager)->post('/proyek/'.Str::uuid().'/penawaran', ['command_id' => (string) Str::uuid()])->assertNotFound();
});

it('shows PG-014\'s controls with their capability and its values and paper only with finance.view (AZ-01; PJ-15; SM-25)', function () {
    $this->actingAs($this->manager)->post($this->path, ['command_id' => (string) Str::uuid()]);
    expect(QuotationRecords::latest($this->project->id)['state'])->toBe('DRAFT');

    $this->actingAs($this->manager)->get($this->path)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can', ['manage' => true, 'approve' => false, 'reject' => true, 'supersede' => null])->has('current.lines.0.price')->where('current.paper.layout', 'DOC-01'));

    $confirmer = Authorization::admin([$this->company->id], ['projects.view', 'projects.confirm']);
    $this->actingAs($confirmer)->get($this->path)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can', ['manage' => false, 'approve' => true, 'reject' => false, 'supersede' => null])->missing('current.lines.0.price')->where('current.paper', null));
    $this->actingAs($confirmer)->get('/dokumen/penawaran')->assertInertia(fn (AssertableInertia $page) => $page->where('money', false)->missing('rows.0.value'));
});
