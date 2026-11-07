<?php

use App\Modules\CAT\Models\Product;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\Reporting\Queries\Search;
use App\Support\Search\Term;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-059; QB-03; PF-21; PF-22; PFM-01; PFM-02; PFM-03; criteria 3, 9): the product type-ahead of PG-009 and the
// form pickers within QB-03's three statements, the same for few products and many; a company-scoped title search
// reading the projects through an index condition on the company, so the scope chooses before the fragment (CS-07). The scan lookup's QB-06
// stays in ReceivingQueriesTest; times are measured on staging (PERFORMANCE_TARGETS §1), never here.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
});

function searchStatements(Closure $read): int
{
    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });
    $read();

    return $count;
}

it('answers the product type-ahead within QB-03 for few products and many (QB-03; PFM-01; PFM-02; criterion 9)', function () {
    $viewer = app(EffectiveCapabilities::class)->fresh(Authorization::owner()->id);
    $search = app(Search::class);
    Product::factory()->create(['name' => 'Kertas HVS A4']);
    $few = searchStatements(fn () => $search->run($viewer, new Term('kertas'), ['product']));

    Product::factory()->count(60)->sequence(fn ($sequence) => ['name' => 'Kertas HVS '.$sequence->index])->create();
    $many = searchStatements(fn () => $search->run($viewer, new Term('kertas'), ['product']));

    expect($few)->toBeLessThanOrEqual(3)->and($many)->toBe($few);
});

it('reads a company-scoped title search through an index condition on the company, the scope choosing first (PF-22; PFM-03; criterion 3)', function () {
    [$a, $b] = [Company::factory()->create(), Company::factory()->create()];
    Project::factory()->count(30)->sequence(fn ($sequence) => ['company_id' => $sequence->index % 2 === 0 ? $a->id : $b->id, 'title' => 'Pengadaan Meja '.$sequence->index])->create();
    DB::statement('analyze projects');
    $viewer = app(EffectiveCapabilities::class)->fresh(Authorization::admin([$a->id], ['projects.view'])->id);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'lower(projects.title) like')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    app(Search::class)->run($viewer, new Term('pengadaan'), ['project']);

    expect($queries)->toHaveCount(1);
    DB::statement('set local enable_seqscan = off');
    [$sql, $bindings] = $queries[0];
    $plan = implode("\n", array_map(fn (object $row): string => (string) array_values((array) $row)[0], DB::select("explain {$sql}", $bindings)));

    // The projects are reached through an index condition on the company, never chosen by the fragment first.
    expect($plan)->toMatch('/Scan using \S+ on projects\s.*\n\s+Index Cond: \(+company_id/');
});
