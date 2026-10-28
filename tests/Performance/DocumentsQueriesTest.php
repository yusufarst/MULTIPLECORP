<?php

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\ORG\Models\Company;
use Database\Factories\DOC\DocumentTypeFactory;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Documents\SyntheticSubjects;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-029; QB-12; QB-16; PFM-01; PFM-02): PG-035 reads one page of a branch in the same number of statements for
// few documents and many — the current version and the pre-payment state each one statement for the page, never one
// per row —, and a document's page and its draft's composer do not grow with the documents beside it.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    SyntheticSubjects::register();

    foreach (range(1, 14) as $number) {
        DocumentTypeFactory::ensure(sprintf('DOC-%02d', $number));
    }

    $this->company = Company::factory()->create();
    $this->viewer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'documents.issue']);
});

afterEach(fn () => @unlink($this->hotFile));

function documentStatements(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function documentsOf(Company $company, int $count): void
{
    foreach (range(1, $count) as $number) {
        $document = Document::factory()->create(['company_id' => $company->id, 'document_type_code' => 'DOC-09', 'project_id' => ProjectFactory::new()->createOne(['company_id' => $company->id])->id]);
        DocumentVersion::factory()->issued()->create(['document_id' => $document->id, 'company_id' => $company->id, 'document_type_code' => 'DOC-09']);
    }
}

it('renders PG-035 in the same number of statements for few documents and many (QB-12; PFM-01)', function () {
    $page = fn () => documentStatements(fn () => $this->actingAs($this->viewer)->get('/dokumen')->assertOk());
    documentsOf($this->company, 2);
    $page();
    $few = $page();

    documentsOf($this->company, 30);
    $many = $page();

    expect($many)->toBe($few);
});

it('renders a document\'s draft composer without growing with the company\'s other documents (QB-16; PFM-02)', function () {
    $project = ProjectFactory::new()->createOne(['company_id' => $this->company->id]);
    $this->actingAs($this->viewer)->post('/dokumen', ['command_id' => (string) Str::uuid(), 'jenis' => 'DOC-09', 'subjek' => ['project_id' => $project->public_id]]);
    $document = Document::query()->where('project_id', $project->id)->sole();
    $page = fn () => documentStatements(fn () => $this->actingAs($this->viewer)->get("/dokumen/{$document->public_id}")->assertOk());
    $page();
    $few = $page();

    documentsOf($this->company, 20);

    expect($page())->toBe($few);
});
