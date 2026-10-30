<?php

use App\Modules\OPS\Queries\SecurityLog;
use App\Modules\ORG\Models\Company;
use Carbon\CarbonImmutable;
use Database\Factories\OPS\SecurityEventFactory;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\Authorization;
use Tests\Support\RefreshDatabase;

// TL-04 (TASK-061; ROUTE-064; LG-02; LG-03; INT-020; criteria 3, 5, 6): PG-064 lists the security events newest first
// with the fields LG-02 records — the identifier and the address only as their keyed hashes —, filtered by kind, period
// and company; a security notice's event opens above the list with "Kembali"; "Muat Lagi" by a signed cursor.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');
    file_put_contents($this->hotFile, 'http://localhost:5173');
    Vite::useHotFile($this->hotFile);
    Authorization::catalogue();
    $this->owner = Authorization::owner();
    $this->company = Company::factory()->create(['code' => 'AAA']);
    $this->other = Company::factory()->create(['code' => 'BBB']);
    $this->admin = Authorization::admin([$this->company->id], ['projects.view']);
});

afterEach(fn () => @unlink($this->hotFile));

function securityEvent(array $attributes): void
{
    SecurityEventFactory::new()->createOne($attributes);
}

function securityRows(object $test, string $query = ''): array
{
    return $test->actingAs($test->owner)->get("/aktivitas/log-keamanan?{$query}")->viewData('page')['props']['rows'];
}

it('lists the security events newest first with LG-02\'s fields, the identifier and address only as keyed hashes (LG-02; LG-03; criterion 3)', function () {
    securityEvent(['occurred_at' => '2026-10-27 03:00:00+00', 'event_kind' => 'LOGIN_FAILED', 'reason_code' => 'CREDENTIALS', 'route_name' => 'login',
        'identifier_hash' => str_repeat('a', 64), 'ip_hash' => str_repeat('b', 64), 'user_agent_summary' => 'Chrome · Windows', 'details' => ['attempt' => 3]]);
    securityEvent(['occurred_at' => '2026-10-27 04:00:00+00', 'event_kind' => 'TOTP_RESET', 'user_id' => $this->admin->id, 'actor_user_id' => $this->owner->id, 'reason_code' => 'OWNER']);

    $this->actingAs($this->owner)->get('/aktivitas/log-keamanan')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('OPS/SecurityLog', false)->has('rows', 2)->where('focus', null)->where('from_notice', false)->has('kinds', 39)
        ->where('rows.0.kind', 'TOTP_RESET')->where('rows.0.account', $this->admin->name)->where('rows.0.actor', $this->owner->name)
        ->where('rows.1.kind', 'LOGIN_FAILED')->where('rows.1.account', null)->where('rows.1.actor', null)->where('rows.1.reason', 'CREDENTIALS')
        ->where('rows.1.route', 'login')->where('rows.1.identifier', str_repeat('a', 12))->where('rows.1.address', str_repeat('b', 12))
        ->where('rows.1.agent', 'Chrome · Windows')->where('rows.1.details', ['attempt' => '3']));
});

it('filters by kind, period and company (PF-42; criterion 3)', function () {
    securityEvent(['occurred_at' => '2026-10-20 03:00:00+00', 'event_kind' => 'LOGIN_SUCCEEDED']);
    securityEvent(['occurred_at' => '2026-10-27 03:00:00+00', 'event_kind' => 'ACCESS_DENIED']);
    securityEvent(['occurred_at' => '2026-10-27 04:00:00+00', 'event_kind' => 'EVIDENCE_DUPLICATE_UNDISCLOSED', 'command_id' => (string) Str::uuid(),
        'target_entity_type' => 'evidence_document', 'target_public_id' => (string) Str::uuid(), 'related_entity_type' => 'evidence_document',
        'related_public_id' => (string) Str::uuid(), 'company_id' => $this->company->id, 'related_company_id' => $this->other->id]);
    $kinds = fn (string $query): array => array_column(securityRows($this, $query), 'kind');

    expect($kinds('jenis=ACCESS_DENIED'))->toBe(['ACCESS_DENIED'])
        ->and($kinds('dari=2026-10-27'))->toBe(['EVIDENCE_DUPLICATE_UNDISCLOSED', 'ACCESS_DENIED'])
        ->and($kinds('sampai=2026-10-20'))->toBe(['LOGIN_SUCCEEDED'])
        ->and($kinds('perusahaan=BBB'))->toBe(['EVIDENCE_DUPLICATE_UNDISCLOSED'])
        ->and(securityRows($this, 'perusahaan=AAA')[0]['companies'])->toBe(['AAA', 'BBB'])
        // An unknown kind is no filter at all; an unknown company reads nothing (WS-03; SM-01).
        ->and($kinds('jenis=SOMETHING'))->toHaveCount(3)
        ->and($kinds('perusahaan=ZZZ'))->toBe([]);
});

it('opens a security notice\'s event above the list with "Kembali" (INT-020; NAV-064; criterion 5)', function () {
    securityEvent(['occurred_at' => '2026-10-27 03:00:00+00', 'event_kind' => 'AUTHORIZATION_ANOMALY', 'user_id' => $this->admin->id]);
    $key = securityRows($this)[0]['key'];

    $this->actingAs($this->owner)->get('/aktivitas/log-keamanan?peristiwa='.urlencode($key).'&asal=beranda')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('focus.kind', 'AUTHORIZATION_ANOMALY')->where('focus.account', $this->admin->name)->where('from_notice', true));
    // An event that does not resolve is a record out of reach (MSG-09; IP-21).
    $this->actingAs($this->owner)->get('/aktivitas/log-keamanan?peristiwa=forged.key&asal=beranda')->assertNotFound();
});

it('continues by keyset with "Muat Lagi" and answers a tampered cursor with MSG-26 (PF-13–PF-15; criterion 6)', function () {
    $start = CarbonImmutable::parse('2026-10-01 00:00:00+00');

    foreach (range(1, SecurityLog::PAGE + 2) as $minute) {
        securityEvent(['occurred_at' => $start->addMinutes($minute)->toIso8601String(), 'event_kind' => 'LOGOUT', 'reason_code' => "n{$minute}"]);
    }

    $first = $this->actingAs($this->owner)->get('/aktivitas/log-keamanan')->viewData('page')['props'];
    expect($first['rows'])->toHaveCount(SecurityLog::PAGE);
    $rest = securityRows($this, 'setelah='.urlencode((string) $first['more']));
    expect(array_column($rest, 'reason'))->toBe(['n2', 'n1']);

    $this->actingAs($this->owner)->get('/aktivitas/log-keamanan?setelah=AAAA.bbbb')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('cursor_failed', true)->has('rows', 0));
});
