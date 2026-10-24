<?php

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-012): a list, count, facet, total and page apply the scope inside the query, before ranking,
// aggregation and pagination, so seeded out-of-scope rows change no result (CS-07; SM-12's oracle; DP-08;
// architecture challenge 8b).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    $this->own = Company::factory()->create();
    $this->other = Company::factory()->create();
    $this->admin = Authorization::admin([$this->own->id], ['projects.view']);
    Project::factory()->count(2)->create(['company_id' => $this->own->id]);
    $this->scope = fn () => app(EffectiveCapabilities::class)->for($this->admin)->scope;
});

/**
 * The scoped figures a page and its filters show.
 *
 * @return array<string, mixed>
 */
function scopedFigures(Closure $scope): array
{
    $projects = fn () => $scope()->apply(Project::query(), 'projects.company_id');

    return [
        'count' => $projects()->count(),
        'facet' => $projects()->toBase()->select('channel', DB::raw('count(*) as n'))->groupBy('channel')->pluck('n', 'channel')->all(),
        'pages' => $projects()->orderBy('id')->paginate(1)->lastPage(),
        'first' => $projects()->orderBy('project_number')->value('project_number'),
        'search' => $projects()->where('project_number', 'like', '%/PRJ/%')->count(),
    ];
}

it('changes no count, facet, page, ranking or search when out-of-scope rows are seeded', function () {
    $before = scopedFigures($this->scope);

    // Ranked first and matching the search, were the scope applied after them.
    Project::factory()->count(3)->sequence(fn ($sequence) => ['project_number' => sprintf('AAA/PRJ/2026/%04d', $sequence->index + 1)])
        ->create(['company_id' => $this->other->id]);

    expect(scopedFigures($this->scope))->toBe($before)
        ->and($before['count'])->toBe(2);
});
