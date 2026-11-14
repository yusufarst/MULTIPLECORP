<?php

use App\Http\Middleware\RequireStepUp;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;
use Tests\Support\TwoFactor;

// TL-04 (TASK-072 criterion 7; PRQ-04; HO-31; CONCURRENCY_IDEMPOTENCY §15; TEST_STRATEGY §15): a page loaded before a
// deployment — its asset version the previous release's — still posts its command afterwards: the command commits once and,
// resent under its `command_id`, answers as recorded, writing nothing twice; only a navigation from that page is turned
// into a full reload, which brings the new release's assets.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = TwoFactor::enrol(Authorization::owner());
});

afterEach(fn () => @unlink($this->hotFile));

function deploymentAnswer(): array
{
    $flash = session('inertia.flash_data');

    return is_array($flash) && is_array($flash['answer'] ?? null) ? $flash['answer'] : [];
}

it('commits a command posted from a page of the previous release once, and reloads only its navigation', function () {
    $owner = fn () => $this->actingAs($this->owner)->withSession([RequireStepUp::CONFIRMED_AT => time()]);
    $previousRelease = ['X-Inertia' => 'true', 'X-Inertia-Version' => 'the-previous-release'];

    $owner()->get('/pengaturan/perusahaan', $previousRelease)
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');

    $payload = ['code' => 'NEW1', 'legal_name' => 'CV Baru', 'command_id' => (string) Str::uuid()];
    $before = DB::table('companies')->count();

    $owner()->withHeaders($previousRelease)->post('/pengaturan/perusahaan', $payload)->assertRedirect();
    expect(deploymentAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)
        ->and(DB::table('companies')->count())->toBe($before + 1);

    $owner()->withHeaders($previousRelease)->post('/pengaturan/perusahaan', $payload)->assertRedirect();
    expect(deploymentAnswer()['kind'] ?? null)->toBe(Outcome::COMMITTED)
        ->and(DB::table('companies')->count())->toBe($before + 1);
});
