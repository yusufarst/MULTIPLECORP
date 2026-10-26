<?php

use App\Modules\IAM\Authentication\GoogleSignIn;
use App\Modules\IAM\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-019): INT-102 and INT-103 on the server's side — the start present only without a session and with
// a client configured, the callback's denied and unknown cases answered alike on PG-001, nothing created or linked
// by it (AU-22; AU-23).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    config(['services.google.client_id' => 'klien-uji', 'services.google.client_secret' => 'rahasia-klien-uji']);
});

afterEach(fn () => @unlink($this->hotFile));

it('offers INT-102 to a guest only, with a client configured (INT-102; GLC-09)', function () {
    $this->get('/masuk/google')->assertRedirectContains('accounts.google.com');
    $this->actingAs(User::factory()->create())->get('/masuk/google')->assertRedirect('/');
});

it('answers INT-103\'s denied and unknown callbacks alike on PG-001, creating and linking nothing (INT-103; AU-22, AU-23)', function () {
    $answers = collect([
        '/masuk/google/callback',
        '/masuk/google/callback?error=access_denied',
        '/masuk/google/callback?state=x&code=y',
    ])->map(fn (string $uri) => $this->get($uri));

    foreach ($answers as $answer) {
        $answer->assertRedirect('/masuk');
    }

    expect(DB::table('users')->count())->toBe(0)
        ->and(DB::table('user_external_identities')->count())->toBe(0)
        ->and(DB::table('security_events')->where('event_kind', 'GOOGLE_SIGNIN_FAILED')->count())->toBe(3)
        ->and(session(GoogleSignIn::FAILED))->toBeTrue();
});
