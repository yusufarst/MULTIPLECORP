<?php

use App\Http\Middleware\RequireStepUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-15 (TASK-023; PERFORMANCE_TARGETS PFM-01, PFM-02, PFM-19): PG-065 within a bounded number of statements, and an
// identity step that reads only the account's own rows — its user row, its grants for the high-risk test, its Google
// link — and writes its audit and security events, the limiter on the store, no business table.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->admin = TwoFactor::enrol(Authorization::admin());
});

afterEach(fn () => @unlink($this->hotFile));

/** @return list<string> the tables a request's statements name */
function tablesOf(callable $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $queries = array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
    DB::disableQueryLog();
    preg_match_all('/(?:from|into|update|join)\s+"?([a-z_]+)"?/i', implode("\n", $queries), $matches);

    // A row lock's `for update` names no table.
    return array_values(array_diff(array_unique($matches[1]), ['update', 'set', 'select']));
}

it('renders PG-065 within its statement budget (PFM-01; PFM-02)', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($this->admin)->get('/akun')->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(25);
});

it('touches only the account\'s own rows and its events at an identity step (PFM-19)', function () {
    $tables = tablesOf(fn () => $this->actingAs($this->admin)->withSession([RequireStepUp::CONFIRMED_AT => time()])
        ->post('/akun/kode-pemulihan/baru', ['code' => TwoFactor::code()])->assertRedirect('/akun'));
    $allowed = ['users', 'audit_events', 'security_events', 'sessions', 'user_roles', 'roles', 'role_capabilities', 'capabilities', 'user_capability_grants', 'user_company_grants', 'user_external_identities'];

    expect(array_values(array_diff($tables, $allowed)))->toBe([]);
});
