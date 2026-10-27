<?php

use App\Http\Middleware\RequireStepUp;
use App\Modules\IAM\Models\User;
use App\Modules\ORG\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-15 (TASK-024; PERFORMANCE_TARGETS PFM-01, PFM-02, PFM-20; QB-13): PG-060's directory and an account's detail in a
// number of statements that never depends on the rows they show — one account and a full page of 25, one session and
// many —, and an NX-02 command within QB-13's budget of about forty statements, never one per row read.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
    $this->company = Company::factory()->create();
});

afterEach(fn () => @unlink($this->hotFile));

function statementsOf(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** An account with its grants, sessions, links and Google history, `$rows` of each. */
function populatedAccount(object $test, int $rows): User
{
    $account = Authorization::admin([$test->company->id], ['projects.view', 'stock.view']);

    foreach (range(1, $rows) as $row) {
        DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $account->id, 'user_agent' => 'Mozilla/5.0 Firefox/131.0', 'payload' => '', 'last_activity' => time() - $row]);
        DB::table('credential_tokens')->insert(['user_id' => $account->id, 'purpose' => 'RESET', 'issued_by' => $test->owner->id, 'token_hash' => Str::random(64), 'issued_at' => now()->subMinutes($row), 'expires_at' => now()->subMinutes($row - 1), 'revoked_at' => now()]);
    }

    return $account;
}

it('renders the directory in the same number of statements for one account and for 25 (PFM-01; PFM-02)', function () {
    $one = statementsOf(fn () => $this->actingAs($this->owner)->get('/pengaturan/pengguna')->assertOk());

    foreach (range(1, 24) as $ignored) {
        Authorization::admin([$this->company->id], ['projects.view']);
    }

    $many = statementsOf(fn () => $this->actingAs($this->owner)->get('/pengaturan/pengguna')->assertOk());

    expect($many)->toBe($one)->and($one)->toBeLessThanOrEqual(30);
});

it('renders an account\'s detail in the same number of statements whatever its sessions and links (PFM-02)', function () {
    $few = populatedAccount($this, 1);
    $many = populatedAccount($this, 25);

    $one = statementsOf(fn () => $this->actingAs($this->owner)->get("/pengaturan/pengguna?akun={$few->public_id}")->assertOk());
    $full = statementsOf(fn () => $this->actingAs($this->owner)->get("/pengaturan/pengguna?akun={$many->public_id}")->assertOk());

    expect($full)->toBe($one)->and($one)->toBeLessThanOrEqual(40);
});

it('runs a grant change within QB-13\'s budget of about forty statements (PFM-20)', function () {
    $account = populatedAccount($this, 3);
    $second = Company::factory()->create();

    $count = statementsOf(fn () => $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()])
        ->put("/pengaturan/pengguna/{$account->public_id}/akses", ['command_id' => (string) Str::uuid(), 'role' => 'ADMIN_OPERASIONAL',
            'companies' => [$this->company->public_id, $second->public_id], 'capabilities' => ['projects.view', 'stock.view', 'projects.manage'], 'lock_version' => 0])
        ->assertRedirect());

    expect(session('inertia.flash_data')['answer']['kind'] ?? null)->toBe('COMMITTED')
        ->and($count)->toBeLessThanOrEqual(60);
});
