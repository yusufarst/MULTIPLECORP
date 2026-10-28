<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Queries\ProjectJourneys;
use App\Modules\PRJ\Queries\ProjectList;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-032; QB-01, QB-02; PFM-01, PFM-02; criteria 8–10): PG-010's list in at most four statements with one
// bounded branch per company, its journey summary group in at most twelve, the same for one row and a full page; PG-011
// at once in at most ten and each of its three deferred groups in at most ten, whatever the project's lines; and no
// stage filter offered (PFM-04).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->companies = [Projects::company(), Projects::company()];
    $this->viewer = Authorization::admin(array_map(fn ($company): int => $company->id, $this->companies), ['projects.view', 'projects.manage', 'finance.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function projectStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** Projects of both companies, each with lines of every mode, outside this year's numbering. */
function seedProjects(array $companies, int $each): void
{
    $goods = Projects::product();
    $service = Projects::product('SERVICE');

    foreach ($companies as $company) {
        foreach (range(1, $each) as $number) {
            $project = ProjectFactory::new()->createOne(['company_id' => $company->id, 'period_key' => '2019', 'state' => 'ACTIVE']);

            foreach ([[$goods, 'WAREHOUSE'], [$goods, 'DROPSHIP'], [$service, 'SERVICE']] as $index => [$line, $mode]) {
                DB::table('project_lines')->insert(['project_id' => $project->id, 'company_id' => $company->id, 'line_no' => $index + 1, 'kind' => $line['product']->kind,
                    'product_id' => $line['product']->id, 'quantity_scale' => 0, 'description' => 'Baris', 'entered_qty' => '1', 'entered_unit_id' => $line['base']->id,
                    'ratio_to_base' => 1, 'qty_base' => '1', 'fulfillment_mode' => $mode, 'recorded_at' => now()]);
            }
        }
    }
}

it('reads PG-010\'s page in at most four statements and its journey summary in at most twelve, the same for few rows and many (QB-01; PF-41; PFM-02; criterion 8)', function () {
    $list = app(ProjectList::class);
    $viewer = app(EffectiveCapabilities::class)->for($this->viewer->id);
    $ids = array_map(fn ($company): int => $company->id, $this->companies);
    $measure = function () use ($list, $viewer, $ids): array {
        $rows = [];
        $page = projectStatements(function () use ($list, $viewer, $ids, &$rows): void {
            $rows = $list->page($viewer, $ids, null, null)['rows'];
        });
        $projects = Project::query()->whereIn('public_id', array_column($rows, 'id'))->pluck('id')->all();
        $journey = projectStatements(fn () => app(ProjectJourneys::class)->of($projects, array_fill_keys($projects, true)));

        return [$page, $journey, count($rows)];
    };

    seedProjects($this->companies, 1);
    [$fewPage, $fewJourney, $few] = $measure();
    seedProjects($this->companies, 15);
    [$manyPage, $manyJourney, $many] = $measure();

    expect([$few, $many])->toBe([2, ProjectList::PAGE])
        ->and($fewPage)->toBeLessThanOrEqual(4)->and($manyPage)->toBe($fewPage)
        ->and($fewJourney)->toBeLessThanOrEqual(12)->and($manyJourney)->toBe($fewJourney);
});

it('serves PG-010 and its journey group in the same number of statements for one row and a full page (PFM-01; PFM-02)', function () {
    $request = function (): int {
        $version = $this->actingAs($this->viewer)->get('/proyek')->viewData('page')['version'];

        return projectStatements(fn () => $this->actingAs($this->viewer)->get('/proyek', [
            'X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, 'X-Inertia-Partial-Component' => 'PRJ/Projects', 'X-Inertia-Partial-Data' => 'journeys',
        ])->assertOk());
    };

    seedProjects([$this->companies[0]], 1);
    $request();
    $one = $request();
    seedProjects($this->companies, 15);

    expect($request())->toBe($one);
});

it('reads PG-011 at once in at most ten statements and each deferred group in at most ten, whatever its lines (QB-02; UXS-44; criterion 10)', function () {
    seedProjects([$this->companies[0]], 1);
    $project = Project::query()->firstOrFail();
    $workspace = app(ProjectWorkspace::class);
    $counts = fn (): array => [
        projectStatements(fn () => $workspace->screen($this->viewer->id, $project)),
        projectStatements(fn () => $workspace->predicates($this->viewer->id, $project)),
        projectStatements(fn () => $workspace->documents($this->viewer->id, $project)),
        projectStatements(fn () => $workspace->history($this->viewer->id, $project)),
    ];
    $workspace->screen($this->viewer->id, $project);
    $few = $counts();

    $line = DB::table('project_lines')->where('project_id', $project->id)->first();
    foreach (range(4, 30) as $number) {
        DB::table('project_lines')->insert([...array_diff_key((array) $line, ['id' => true]), 'line_no' => $number]);
    }

    expect(max($few))->toBeLessThanOrEqual(10)->and($counts())->toBe($few);
});

it('offers no stage filter on PG-010 — a stage is derived, never stored (PFM-04; L-01; criterion 9)', function () {
    $this->actingAs($this->viewer)->get('/proyek?tahap=warehouse&status=ACTIVE')->assertOk()
        ->assertInertia(fn ($page) => $page->where('filter', ['perusahaan' => [], 'status' => 'ACTIVE']));

    expect(DB::getSchemaBuilder()->hasColumn('projects', 'stage'))->toBeFalse();
});
