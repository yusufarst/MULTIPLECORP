<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-016): the shell's hints — the account's name, capabilities and companies (WS-07) — cost the same
// queries for one company and one capability as for many: the entries are drawn on the client from the
// capabilities, so the shell adds no query per entry, and the companies in scope are read at once, never one
// query per grant (PF-02).

uses(RefreshDatabase::class);

beforeEach(fn () => Authorization::catalogue());

function shellHintQueries(mixed $user): int
{
    Authorization::forget();
    $request = Request::create('/pekerjaan');
    $request->setUserResolver(fn () => $user);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(HandleInertiaRequests::class)->share($request)['auth']();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

it('adds no query per navigation entry or per company in scope (TL-15; PF-02)', function () {
    $one = Authorization::admin([Company::factory()->create()->id], ['projects.view']);
    $many = Authorization::admin(Company::factory()->count(8)->create()->pluck('id')->all(), ['projects.view', 'stock.view', 'finance.view']);

    $few = shellHintQueries($one);

    expect($few)->toBeGreaterThan(0)
        ->and(shellHintQueries($many))->toBe($few)
        ->and(shellHintQueries(Authorization::owner()))->toBeLessThanOrEqual($few);
});
