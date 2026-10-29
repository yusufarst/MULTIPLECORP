<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\PRJ\Models\Project;
use App\Modules\PRJ\Queries\ProjectFulfilment;
use Database\Factories\DOC\EvidenceDocumentFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\Projects;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-034; QB-02; PFM-01; J-FUL-01): PG-011's *Item dan pemenuhan* read in at most six statements, the same for
// one confirmed line and many — the progress derived in the statements, never per line.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->company = Projects::company();
    $this->admin = Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'projects.confirm', 'projects.cancel_scope', 'finance.view', 'stock.view', 'evidence.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function fulfilmentStatements(callable $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function confirmedProject(object $test, int $lines): Project
{
    $products = array_map(fn (): array => Projects::product(), range(1, $lines));
    expect(Projects::create($test->admin->id, $test->company, Projects::client(), $products))->toStartWith('COMMITTED');
    $project = Project::query()->orderByDesc('id')->firstOrFail();
    $evidence = EvidenceDocumentFactory::new()->createOne(['company_id' => $test->company->id, 'evidence_type' => 'CLIENT_SPK']);
    $test->actingAs($test->admin)->post("/proyek/{$project->public_id}/konfirmasi", ['command_id' => (string) Str::uuid(), 'bukti' => $evidence->public_id, 'tanggal' => date('Y-m-d'), 'tanggal_otomatis' => true]);

    return $project->fresh() ?? $project;
}

it('reads Item dan pemenuhan in at most six statements, the same for one line and fifteen (QB-02; PFM-01)', function () {
    $viewer = app(EffectiveCapabilities::class)->for($this->admin->id);
    $read = app(ProjectFulfilment::class);
    $one = confirmedProject($this, 1);
    $many = confirmedProject($this, 15);
    $read->of($viewer, $one);

    $few = fulfilmentStatements(fn () => $read->of($viewer, $one));

    expect(count($read->of($viewer, $many)['lines']))->toBe(15)
        ->and($few)->toBeLessThanOrEqual(6)
        ->and(fulfilmentStatements(fn () => $read->of($viewer, $many)))->toBe($few);
});
