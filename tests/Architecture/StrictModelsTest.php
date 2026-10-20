<?php

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PERFORMANCE PF-02 and SECURITY WS-01: outside production a lazy-loaded relation and a silently
// discarded attribute fail the run; PF-03: automatic eager loading is not relied on.

class StrictParent extends Model
{
    protected $connection = 'strict';

    protected $table = 'parents';

    protected $fillable = ['name'];

    public $timestamps = false;

    /** @return HasMany<StrictChild, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(StrictChild::class, 'parent_id');
    }
}

class StrictChild extends Model
{
    protected $connection = 'strict';

    protected $table = 'children';

    public $timestamps = false;
}

beforeEach(function () {
    config(['database.connections.strict' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

    Schema::connection('strict')->create('parents', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::connection('strict')->create('children', function (Blueprint $table) {
        $table->id();
        $table->foreignId('parent_id');
    });

    StrictParent::query()->create(['name' => 'ARJ']);
    StrictParent::query()->create(['name' => 'BTN']);
});

it('turns the strict model checks on outside production', function () {
    expect(app()->isProduction())->toBeFalse()
        ->and(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::isAutomaticallyEagerLoadingRelationships())->toBeFalse();
});

it('fails a lazy-loaded relation', function () {
    $parents = StrictParent::query()->get();

    expect(fn () => $parents->first()->children)->toThrow(LazyLoadingViolationException::class);
});

it('accepts a declared eager load', function () {
    $parents = StrictParent::query()->with('children')->get();

    expect($parents->first()->children)->toHaveCount(0);
});

it('fails a silently discarded attribute', function () {
    expect(fn () => new StrictParent(['name' => 'CKP', 'company_id' => 1]))
        ->toThrow(MassAssignmentException::class);
});
