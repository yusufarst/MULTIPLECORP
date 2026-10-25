<?php

use App\Http\CompanyView;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\Request;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-016; acceptance criterion 6): the working filter "Tampilkan" narrows within the server's scope and
// never widens it (INFORMATION_ARCHITECTURE §7; WF-ACC-01; CS-03; DP-02; INT-007) — a company outside the scope,
// a malformed value or an unknown key changes nothing; the Owner's scope narrows to the companies asked for.

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    [$this->a, $this->b, $this->c] = [Company::factory()->create(), Company::factory()->create(), Company::factory()->create()];
    $this->view = fn ($user, array $query) => CompanyView::scope(Request::create('/pekerjaan', 'GET', $query), app(EffectiveCapabilities::class)->fresh($user->id));
});

it('narrows an Admin\'s scope to the companies asked for within it', function () {
    $admin = Authorization::admin([$this->a->id, $this->b->id], ['projects.view']);

    expect(($this->view)($admin, ['perusahaan' => $this->b->public_id])->companyIds)->toBe([$this->b->id])
        ->and(($this->view)($admin, [])->companyIds)->toBe([$this->a->id, $this->b->id]);
});

it('never widens: a company outside the scope, a malformed value and a list of them change nothing', function () {
    $admin = Authorization::admin([$this->a->id, $this->b->id], ['projects.view']);
    $scope = [$this->a->id, $this->b->id];

    expect(($this->view)($admin, ['perusahaan' => $this->c->public_id])->companyIds)->toBe($scope)
        ->and(($this->view)($admin, ['perusahaan' => "{$this->a->public_id},{$this->c->public_id}"])->companyIds)->toBe([$this->a->id])
        ->and(($this->view)($admin, ['perusahaan' => "x' or 1=1"])->companyIds)->toBe($scope)
        ->and(($this->view)($admin, ['perusahaan' => ['bad' => ['nested']]])->companyIds)->toBe($scope);
});

it('narrows the Owner\'s every-company scope to the companies asked for', function () {
    $owner = Authorization::owner();

    $scope = ($this->view)($owner, ['perusahaan' => "{$this->c->public_id},{$this->a->public_id}"]);

    expect($scope->all)->toBeFalse()
        ->and($scope->companyIds)->toBe([$this->a->id, $this->c->id])
        ->and(($this->view)($owner, [])->all)->toBeTrue();
});
