<?php

use App\Modules\ADM\Queries\AdministrativeDocuments;
use App\Modules\ADM\Services\AdministrationPredicate;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Administration;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-035; QB-02; QB-12; PFM-01; PFM-02; PFM-20; PF-01): PG-039 reads a page in the same number of statements
// for few unmet items and a full page; PG-011's documents group with its checklist and the administration predicate do
// not grow with the checklist's items — each status derived in one statement over the projects asked —; a checklist
// command writes in a bounded number of statements.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'evidence.view', 'requirements.manage', 'requirements.waive', 'decisions.supersede']);
    $this->project = Administration::project($this->admin->id, $this->company);
});

afterEach(fn () => @unlink($this->hotFile));

function administrationStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** Items of each kind with a link and a waiver, so every part of a row is read. */
function administrationItems(object $test, int $count): void
{
    foreach (range(1, $count) as $number) {
        $item = Administration::requirement($test->project, ['waiver_eligible' => $number % 2 === 0]);
        DB::table('admin_requirement_links')->insert(['admin_requirement_id' => $item, 'company_id' => $test->company->id, 'evidence_document_id' => Administration::evidence($test->company)->id,
            'linked_by' => $test->admin->id, 'recorded_at' => now(), 'recorded_by' => $test->admin->id]);

        if ($number % 2 === 0) {
            DB::table('admin_requirement_waivers')->insert(['admin_requirement_id' => $item, 'company_id' => $test->company->id, 'reason' => 'Tidak diminta', 'decided_by' => $test->admin->id,
                'recorded_at' => now(), 'recorded_by' => $test->admin->id]);
        }
    }
}

it('reads PG-039 in the same number of statements for two unmet items and a full page (QB-12; PFM-01; PFM-02)', function () {
    $read = fn (): int => administrationStatements(fn () => app(AdministrativeDocuments::class)->screen($this->admin->id, ['cabang' => null, 'tab' => 'semua', 'status' => null, 'urut' => null, 'setelah' => null]));
    administrationItems($this, 2);
    $read();
    $few = $read();
    administrationItems($this, AdministrativeDocuments::PAGE + 5);

    expect($few)->toBeLessThanOrEqual(4)->and($read())->toBe($few);
});

it('reads PG-011\'s documents group and the administration predicate without growing with the checklist (QB-02; PFM-01; PF-01)', function () {
    $workspace = app(ProjectWorkspace::class);
    $counts = fn (): array => [
        administrationStatements(fn () => $workspace->documents($this->admin->id, $this->project)),
        administrationStatements(fn () => app(AdministrationPredicate::class)->states([$this->project->id], true)),
    ];
    administrationItems($this, 2);
    $workspace->documents($this->admin->id, $this->project);
    $few = $counts();
    administrationItems($this, 25);

    expect($few[0])->toBeLessThanOrEqual(10)->and($few[1])->toBe(1)->and($counts())->toBe($few);
});

it('writes a checklist command in a bounded number of statements, the same with a long checklist (PFM-20)', function () {
    $add = fn (): int => administrationStatements(fn () => $this->actingAs($this->admin)->post("/proyek/{$this->project->public_id}/persyaratan", [
        'command_id' => (string) Str::uuid(), 'jenis' => 'FREE_TEXT', 'uraian' => 'Foto lokasi', 'wajib' => true, 'cara' => 'UPLOADED', 'asli_klien' => false,
    ]));
    $add();
    $few = $add();
    administrationItems($this, 30);

    expect($add())->toBe($few);
});
