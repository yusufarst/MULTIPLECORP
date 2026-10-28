<?php

use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-031; QB-12; PFM-01): PG-040 reads a branch's page of cases in the same number of statements for few cases
// and many — the residuals in the page's one statement, never one per case.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Company::factory()->create();
    $this->viewer = Authorization::admin([$this->company->id], ['projects.view', 'finance.view', 'cost.view', 'cases.close']);
});

afterEach(fn () => @unlink($this->hotFile));

function caseStatements(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('renders PG-040 in the same number of statements for few cases and many (QB-12; PFM-01)', function () {
    $page = fn () => caseStatements(fn () => $this->actingAs($this->viewer)->get('/dokumen/kasus')->assertOk());
    CorrectionCase::factory()->count(2)->create(['company_id' => $this->company->id]);
    $page();
    $few = $page();

    foreach (range(1, 30) as $number) {
        CorrectionCase::factory()->create(['company_id' => $this->company->id, 'project_id' => ProjectFactory::new()->createOne(['company_id' => $this->company->id])->id,
            'case_kind' => $number % 2 === 0 ? 'PURCHASE_RETURN' : 'SALES_RETURN', 'residual_settlement_amount' => $number % 3 === 0 ? '100.00' : '0.00']);
    }

    expect($page())->toBe($few);
});
