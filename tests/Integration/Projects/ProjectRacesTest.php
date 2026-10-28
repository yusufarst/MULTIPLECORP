<?php

use App\Modules\PRJ\Models\Project;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Numbering;
use Tests\Support\ProjectRaces;
use Tests\Support\Projects;

// TL-05 (TASK-032; NX-03; NM-08; C-10; C-51; ST-01; PX-07; criteria 1, 4, 7) on real PostgreSQL sessions: two creations
// in one company at once draw distinct numbers from its counter, the last lock; an amendment changing the channel that
// waited on the first channel-dependent commit of the same project is refused; two saves of one draft at the version
// both read — one wins, the other is CONFLICT.

uses(DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    Authorization::catalogue();
    $this->goLive = substr(BusinessDate::today(), 0, 4).'-01-01';
    $this->company = Projects::company();
    $this->client = Projects::client();
    $this->goods = Projects::product();
    $this->actors = [
        Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view'])->id,
        Authorization::admin([$this->company->id], ['projects.view', 'projects.manage', 'finance.view'])->id,
    ];
});

it('draws distinct numbers for two creations in one company at once (criterion 1; NM-08; C-10)', function () {
    [$first, $second, $company, $client, $product, $goLive] = [$this->actors[0], $this->actors[1], $this->company->id, $this->client['organization']->id, $this->goods['product']->id, $this->goLive];

    $results = Sessions::run([
        'first' => static fn (): string => ProjectRaces::create($first, $company, $client, $product, $goLive),
        'second' => static fn (): string => ProjectRaces::create($second, $company, $client, $product, $goLive),
    ]);
    $year = substr($goLive, 0, 4);

    expect([$results['first'], $results['second']])->toEqualCanonicalizing(["COMMITTED UJI/PRJ/{$year}/0001", "COMMITTED UJI/PRJ/{$year}/0002"])
        ->and(Project::query()->pluck('sequence_no')->sort()->values()->all())->toBe([1, 2]);
});

it('refuses a channel change that waited on the project\'s first channel-dependent commit (criterion 4; BR-PRJ-05; C-51)', function () {
    expect(Projects::create($this->actors[0], $this->company, $this->client, [$this->goods]))->toStartWith('COMMITTED');
    $project = Project::query()->sole();
    [$confirmer, $amender, $id, $version, $goLive] = [$this->actors[0], $this->actors[1], $project->id, $project->lock_version, $this->goLive];

    $results = Sessions::run([
        'confirmation' => static function () use ($confirmer, $id, $goLive): string {
            Numbering::goLive($goLive);

            return ProjectRaces::holdThenConfirm($confirmer, $id);
        },
        'amendment' => static function () use ($amender, $id, $version, $goLive): string {
            Numbering::goLive($goLive);
            Barrier::await('the confirmation holds the project');

            return ProjectRaces::amend($amender, $id, $version, 'SIPLAH', 'Pengadaan buku perpustakaan');
        },
    ]);

    expect($results)->toBe(['confirmation' => 'COMMITTED', 'amendment' => 'REJECTED BR-PRJ-05'])
        ->and(Project::query()->sole()->channel)->toBe('REGULAR');
});

it('answers the later of two saves of one draft at the version both read CONFLICT (criterion 7; ST-01; PX-07)', function () {
    expect(Projects::create($this->actors[0], $this->company, $this->client, [$this->goods]))->toStartWith('COMMITTED');
    $project = Project::query()->sole();
    [$first, $second, $id, $version, $goLive] = [$this->actors[0], $this->actors[1], $project->id, $project->lock_version, $this->goLive];

    $results = Sessions::run([
        'first' => static function () use ($first, $id, $version, $goLive): string {
            Numbering::goLive($goLive);

            return ProjectRaces::amend($first, $id, $version, 'REGULAR', 'Judul pertama');
        },
        'second' => static function () use ($second, $id, $version, $goLive): string {
            Numbering::goLive($goLive);

            return ProjectRaces::amend($second, $id, $version, 'REGULAR', 'Judul kedua');
        },
    ]);

    expect(array_values($results))->toEqualCanonicalizing(['COMMITTED', 'CONFLICT ST-01'])
        ->and(DB::table('audit_events')->where('action', 'PRJ.ProjectAmended')->count())->toBe(1);
});
