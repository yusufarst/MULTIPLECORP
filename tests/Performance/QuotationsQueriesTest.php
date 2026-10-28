<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Queries\QuotationList;
use App\Modules\PRJ\Queries\QuotationWorkspace;
use App\Modules\PRJ\Services\QuotationRecords;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-033; PFM-01, PFM-02; INT-012; criterion 8): PG-014 read in at most fourteen statements whatever the
// draft's lines — one line or thirty —, and PG-037's page in at most four with one bounded branch, the same for one row
// and a full page.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    DocumentTypeFactory::ensure('DOC-01');
    $this->company = Projects::company();
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->viewer = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view', 'quotation.manage', 'projects.confirm']);
});

afterEach(fn () => @unlink($this->hotFile));

function quotationStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** A project with its quotation drafted from its lines. */
function draftedProject(object $test): Project
{
    expect(Projects::create($test->viewer->id, $test->company, $test->client, [$test->goods]))->toStartWith('COMMITTED');
    $project = Project::query()->orderByDesc('id')->firstOrFail();
    expect(app(SaveQuotationDraft::class)->run((string) Str::uuid(), $test->viewer->id, $project, null, null, null, [], [])->kind)->toBe('COMMITTED');

    return $project;
}

it('reads PG-014 in at most fourteen statements, the same for one line and thirty (PFM-01; INT-012; criterion 8)', function () {
    $project = draftedProject($this);
    $workspace = app(QuotationWorkspace::class);
    $workspace->screen($this->viewer->id, $project);
    $one = quotationStatements(fn () => $workspace->screen($this->viewer->id, $project));

    $revision = QuotationRecords::latest($project->id);
    $line = DB::table('quotation_revision_lines')->where('quotation_revision_id', $revision['id'])->first();
    foreach (range(2, 30) as $number) {
        DB::table('quotation_revision_lines')->insert([...array_diff_key((array) $line, ['id' => true, 'public_id' => true]), 'command_line_no' => $number]);
    }

    expect($one)->toBeLessThanOrEqual(14)
        ->and(quotationStatements(fn () => $workspace->screen($this->viewer->id, $project)))->toBe($one)
        ->and(count($workspace->screen($this->viewer->id, $project)['current']['lines']))->toBe(30);
});

it('reads PG-037\'s page in at most four statements, the same for one row and a full page (PFM-02; CS-07)', function () {
    $list = app(QuotationList::class);
    $viewer = app(EffectiveCapabilities::class)->for($this->viewer->id);
    draftedProject($this);
    $list->page($viewer, $this->company->id, null);
    $one = quotationStatements(fn () => $list->page($viewer, $this->company->id, null));

    foreach (range(1, QuotationList::PAGE + 2) as $number) {
        draftedProject($this);
    }

    expect($one)->toBeLessThanOrEqual(4)
        ->and(quotationStatements(fn () => $list->page($viewer, $this->company->id, null)))->toBe($one)
        ->and(count($list->page($viewer, $this->company->id, null)['rows']))->toBe(QuotationList::PAGE);
});
