<?php

use App\Modules\IAM\Queries\ScopedRecords;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\OPS\Models\SecurityEvent;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-011): the company scope inside the query, before pagination (PERMISSIONS_MATRIX CS-07; AZ-04),
// and a public identifier resolved through it — an out-of-scope record answering as a missing one (CS-04;
// AZ-11; SM-01 on the paths that exist).

uses(RefreshDatabase::class);

beforeEach(function () {
    Authorization::catalogue();
    [$this->a, $this->b, $this->c] = [Company::factory()->create(), Company::factory()->create(), Company::factory()->create()];
    $this->admin = Authorization::admin([$this->a->id, $this->c->id], ['projects.view']);
    $this->authority = app(EffectiveCapabilities::class)->for($this->admin);
});

it('filters inside the query, so a page, a count and a total never hold another company (CS-07)', function () {
    $page = $this->authority->scope->apply(Company::query(), 'companies.id')->orderBy('id')->paginate(1);

    expect($page->total())->toBe(2)
        ->and($page->lastPage())->toBe(2)
        ->and($page->items()[0]->id)->toBe($this->a->id)
        ->and($this->authority->scope->apply(Company::query(), 'companies.id')->pluck('id')->all())->not->toContain($this->b->id);
});

it('resolves a public identifier in scope', function () {
    $found = app(ScopedRecords::class)->find(Company::query(), strtoupper($this->c->public_id), $this->authority, 'id');

    expect($found->is($this->c))->toBeTrue();
});

it('answers another company\'s, a missing and a malformed identifier alike, logging only the real probe (CS-04; AZ-11)', function (Closure $publicId, bool $logged, int $queries) {
    $exception = null;
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        app(ScopedRecords::class)->find(Company::query(), $publicId->call($this), $this->authority, 'id');
    } catch (ModelNotFoundException $caught) {
        $exception = $caught;
    }

    expect(DB::getQueryLog())->toHaveCount($queries);

    app(DeferredCallbackCollection::class)->invoke();

    expect($exception)->toBeInstanceOf(ModelNotFoundException::class)
        ->and($exception?->getModel())->toBe(Company::class)
        ->and(SecurityEvent::query()->where('event_kind', 'ACCESS_DENIED')->where('reason_code', 'OUT_OF_SCOPE')->count())->toBe($logged ? 1 : 0);
})->with([
    'another company\'s record' => [fn () => $this->b->public_id, true, 2],
    'a missing record' => [fn () => '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b', false, 2],
    'a malformed identifier' => [fn () => '42', false, 0],
]);

it('answers a request for another company\'s and a missing record with the same response (AZ-11; WS-10)', function () {
    // As in production, where APP_DEBUG is false (WS-10).
    config(['app.debug' => false]);
    Route::middleware(['web', 'auth', 'capability:projects.view'])->get('/__test/perusahaan/{id}', function (string $id) {
        $authority = app(EffectiveCapabilities::class)->for(request()->user());

        return app(ScopedRecords::class)->find(Company::query(), $id, $authority, 'id')->public_id;
    });

    $foreign = $this->actingAs($this->admin)->getJson("/__test/perusahaan/{$this->b->public_id}");
    $missing = $this->actingAs($this->admin)->getJson('/__test/perusahaan/0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b');

    $foreign->assertNotFound();
    expect($foreign->getContent())->toBe($missing->getContent())
        ->and($foreign->getContent())->not->toContain('Company', $this->b->public_id);
    $this->actingAs($this->admin)->getJson("/__test/perusahaan/{$this->a->public_id}")->assertOk()->assertSee($this->a->public_id);
});

it('keeps the scope around a query\'s own or-conditions (CS-07)', function () {
    $query = Company::query()->where('companies.id', $this->a->id)->orWhere('companies.id', $this->b->id);

    expect($this->authority->scope->apply($query, 'companies.id')->pluck('id')->all())->toBe([$this->a->id]);
});

it('gives the Owner every company and a new Admin none', function () {
    $owner = app(EffectiveCapabilities::class)->for(Authorization::owner());
    $none = app(EffectiveCapabilities::class)->for(Authorization::admin());

    expect($owner->scope->apply(Company::query(), 'companies.id')->count())->toBe(3)
        ->and($none->scope->apply(Company::query(), 'companies.id')->count())->toBe(0);
});
