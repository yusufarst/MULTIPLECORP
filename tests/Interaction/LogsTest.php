<?php

use App\Modules\IAM\Models\User;
use Database\Factories\OPS\SecurityEventFactory;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-08 (TASK-061; INT-020; NAV-064; DP-11; criterion 5): a security notice opens its record — the event on PG-064, with
// "Kembali" to the origin, or the account on PG-060 —, the notice's target read when it is shown, not stored; a target
// that no longer resolves answers MSG-09 (IP-21). Both pages are read-only: nothing on them edits, hides or deletes an
// event, and no route of theirs accepts a write (C-53; criterion 7).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = Authorization::owner();
});

afterEach(fn () => @unlink($this->hotFile));

it('opens a notice\'s event on PG-064 with "Kembali", an account\'s notice on PG-060 (INT-020; NAV-064; criterion 5)', function () {
    $admin = Authorization::admin([], ['projects.view']);
    SecurityEventFactory::new()->createOne(['event_kind' => 'OWNER_RECOVERY_LINK_ISSUED', 'user_id' => $this->owner->id]);
    SecurityEventFactory::new()->createOne(['event_kind' => 'AUTHORIZATION_ANOMALY', 'user_id' => $admin->id]);
    $events = $this->actingAs($this->owner)->get('/aktivitas/log-keamanan')->viewData('page')['props']['rows'];

    foreach ($events as $event) {
        $this->actingAs($this->owner)->get('/aktivitas/log-keamanan?peristiwa='.urlencode($event['key']).'&asal=beranda')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('focus.key', $event['key'])->where('focus.kind', $event['kind'])->where('from_notice', true));
    }

    // An account's notice opens PG-060 on that account.
    $this->actingAs($this->owner)->get("/pengaturan/pengguna?akun={$admin->public_id}")->assertOk();
    // A target that no longer resolves is a record out of reach (MSG-09; IP-21).
    $this->actingAs($this->owner)->get('/aktivitas/log-keamanan?peristiwa=AAAA.forged&asal=beranda')->assertNotFound();
});

it('offers no route that writes to either log (C-53; BR-XC-03; criterion 7)', function () {
    foreach (['/aktivitas/riwayat', '/aktivitas/log-keamanan'] as $path) {
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->actingAs(User::query()->findOrFail($this->owner->id))->{$method}($path)->assertStatus(405);
        }
    }
});
