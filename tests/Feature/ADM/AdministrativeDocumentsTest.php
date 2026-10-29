<?php

use App\Modules\ADM\Queries\AdministrativeDocuments;
use App\Modules\PRJ\Models\ProjectStateTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Administration;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-035; ROUTE-039; PG-039; SCR-37; NAV-039; IP-18; CS-07; CS-08; criterion 13): the unmet items of the open
// projects of one company of the viewer's scope at a time, each with its project and company; the tab, the status filter
// and the sort from the address; a met, removed or closed project's item never listed; the cursor continuing exactly
// after the last row shown.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'requirements.waive']);
    $this->first = Administration::project($this->admin->id, $this->company);
    $this->second = Administration::project($this->admin->id, $this->company);
});

afterEach(fn () => @unlink($this->hotFile));

function administrativeRows(object $test, string $query = ''): array
{
    $rows = [];
    $test->actingAs($test->admin)->get('/dokumen/administrasi'.$query)->assertOk()->assertInertia(function (AssertableInertia $page) use (&$rows) {
        $rows = $page->toArray()['props'];
    });

    return $rows;
}

it('lists the unmet items with their project and company, the tab, filter and sort from the address (ROUTE-039; IP-18; criterion 13)', function () {
    $required = Administration::requirement($this->first);
    $optional = Administration::requirement($this->first, ['is_required' => false, 'requirement_kind' => 'FREE_TEXT', 'document_type_code' => null, 'description' => 'Foto lokasi', 'satisfaction_mode' => 'UPLOADED']);
    $prepared = Administration::requirement($this->second);
    Administration::document($this->second, 'DOC-09', null, false);
    $met = Administration::requirement($this->second, ['document_type_code' => 'DOC-09']);
    DB::table('admin_requirement_links')->insert(['admin_requirement_id' => $met, 'company_id' => $this->company->id, 'document_version_id' => Administration::document($this->second)->id,
        'linked_by' => $this->admin->id, 'recorded_at' => now(), 'recorded_by' => $this->admin->id]);
    Administration::requirement($this->first, ['state' => 'REMOVED', 'removed_by' => $this->admin->id, 'removed_at' => now(), 'removed_reason' => 'Tidak diminta']);

    $page = administrativeRows($this);
    expect($page['filters'])->toBe(['tab' => 'wajib', 'status' => null, 'urut' => 'terbaru'])
        ->and(collect($page['rows'])->pluck('handle')->all())->toBe([Administration::handle($prepared), Administration::handle($required)])
        ->and($page['rows'][0])->toMatchArray(['company' => $this->company->code, 'status' => 'DRAFT_ONLY'])
        ->and($page['rows'][0]['project'])->toMatchArray(['id' => $this->second->public_id, 'number' => $this->second->project_number]);

    expect(collect(administrativeRows($this, '?tab=semua&urut=terlama')['rows'])->pluck('handle')->all())
        ->toBe([Administration::handle($required), Administration::handle($optional), Administration::handle($prepared)])
        ->and(collect(administrativeRows($this, '?status=disiapkan')['rows'])->pluck('handle')->all())->toBe([Administration::handle($prepared)])
        ->and(collect(administrativeRows($this, '?status=belum-ada')['rows'])->pluck('handle')->all())->toBe([Administration::handle($required)])
        // An unknown filter value falls back to the default, never an error.
        ->and(administrativeRows($this, '?tab=x&urut=y&status=z')['filters'])->toBe(['tab' => 'wajib', 'status' => null, 'urut' => 'terbaru']);

    // A cancelled project's items leave the list (L-11).
    DB::transaction(function () {
        ProjectStateTransition::factory()->create(['project_id' => $this->second->id, 'company_id' => $this->company->id, 'from_state' => 'ACTIVE', 'to_state' => 'CANCELLED', 'transition_kind' => 'CANCEL', 'reason' => 'Klien membatalkan proyek']);
        DB::table('projects')->where('id', $this->second->id)->update(['state' => 'CANCELLED']);
    });
    expect(collect(administrativeRows($this)['rows'])->pluck('handle')->all())->toBe([Administration::handle($required)]);
});

it('continues exactly after the last row shown with the cursor in the address (PT-08; IP-18; criterion 13)', function () {
    foreach (range(1, AdministrativeDocuments::PAGE - 1) as $ignored) {
        Administration::requirement($this->first);
    }

    foreach (range(1, 3) as $ignored) {
        Administration::requirement($this->second);
    }

    $first = administrativeRows($this);
    expect($first['rows'])->toHaveCount(AdministrativeDocuments::PAGE)->and($first['more'])->toBeString();

    $next = administrativeRows($this, '?setelah='.urlencode($first['more']));
    $all = [...collect($first['rows'])->pluck('handle')->all(), ...collect($next['rows'])->pluck('handle')->all()];
    expect($next['rows'])->toHaveCount(2)->and($next['more'])->toBeNull()
        ->and(array_unique($all))->toHaveCount(AdministrativeDocuments::PAGE + 2);
});
