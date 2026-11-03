<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\InvoiceWorld;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-055; PFM-02; PF-24; QB-02): PG-012 reads in the same number of statements whatever the project's lines and invoices, each predicate a set query.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->world = InvoiceWorld::make();
    $this->owner = Authorization::owner();
});

afterEach(fn () => @unlink($this->hotFile));

function completionStatements(callable $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $work();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    Auth::forgetGuards();

    return $count;
}

it('reads PG-012 in the same number of statements for one line and several, one invoice and several (PFM-02; PF-24)', function () {
    $small = $this->world->project(['SERVICE'], '1');
    $large = $this->world->project(['SERVICE', 'SERVICE', 'SERVICE'], '5');

    foreach ([1, 2, 3] as $round) {
        $this->world->issued($large, '100000');
    }

    $this->world->issued($small, '100000');
    $read = fn ($project): int => completionStatements(fn () => $this->actingAs($this->owner)->get("/proyek/{$project->public_id}/penyelesaian")->assertOk());
    // The session's first page load writes what later loads only read: measured warm.
    $read($small);

    expect($read($large))->toBe($read($small));
});
