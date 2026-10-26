<?php

use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;

// TL-15 (TASK-017; PF-37–PF-40; PFM-19): authentication adds no business query — a sign-in reads the account and
// writes its session and security event, and touches no company, project, stock or finance table.

uses(RefreshDatabase::class);

it('signs in reading only the account, the session and the security log (PFM-19)', function () {
    config(['session.driver' => 'database']);
    User::factory()->create(['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->postJson('/masuk', ['email' => 'laras@contoh.test', 'password' => 'Kata-Sandi-Uji-9'])->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $tables = collect($queries)->flatMap(fn (array $query): array => preg_match_all('/(?:from|into|update|join)\s+"?([a-z_]+)"?/i', $query['query'], $match) > 0 ? $match[1] : [])->unique()->sort()->values()->all();

    expect($tables)->toBe(['security_events', 'sessions', 'users'])
        ->and(count($queries))->toBeLessThanOrEqual(8);
});
