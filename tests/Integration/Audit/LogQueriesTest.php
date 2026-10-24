<?php

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Queries\AuditEventQuery;
use App\Modules\OPS\Queries\SecurityEventQuery;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-010): the read models of PG-063 and PG-064 (SECURITY LG-05) — keyset order across a WIB
// midnight (GAP-016), the backdated gap (LG-01; TM-44) and the keyed-hash search (LG-03). TASK-011
// registers the capabilities; here the gates stand in for them.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    Gate::define(AuditEventQuery::CAPABILITY, fn (User $user) => $user->is($this->owner));
    Gate::define(SecurityEventQuery::CAPABILITY, fn (User $user) => $user->is($this->owner));
});

function auditAt(string $instant, string $action, ?string $businessDate = null): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($instant));
    DB::transaction(fn () => app(AuditRecorder::class)->record($action, AuthorityClass::System, null, null, businessDate: $businessDate));
}

it('refuses an audit row outside a command\'s transaction, the test\'s own not counting (LG-01)', function () {
    app(AuditRecorder::class)->record('users.rename', AuthorityClass::System, null, null);
})->throws(LogicException::class);

it('orders the audit newest first across a WIB midnight and filters by WIB business day (GAP-016)', function () {
    auditAt('2026-10-24T16:59:59.999999Z', 'before.midnight');
    auditAt('2026-10-24T17:00:00.000002Z', 'after.midnight.second');
    auditAt('2026-10-24T17:00:00.000001Z', 'after.midnight.first');
    auditAt('2026-10-24T17:00:00.000001Z', 'after.midnight.same_instant');

    $actions = fn (array $filters = []) => app(AuditEventQuery::class)->page($this->owner, ...$filters)->items->pluck('action')->all();

    expect($actions())->toBe(['after.midnight.second', 'after.midnight.same_instant', 'after.midnight.first', 'before.midnight'])
        ->and($actions(['from' => '2026-10-24', 'to' => '2026-10-24']))->toBe(['before.midnight'])
        ->and($actions(['from' => '2026-10-25']))->toBe(['after.midnight.second', 'after.midnight.same_instant', 'after.midnight.first']);
});

it('pages the audit by an opaque cursor without skipping events within one second', function () {
    foreach (['2026-10-24T17:00:00.000001Z', '2026-10-24T17:00:00.000002Z', '2026-10-24T17:00:00.000003Z'] as $i => $instant) {
        auditAt($instant, "event.n{$i}");
    }

    $seen = [];
    $cursor = null;

    do {
        $page = app(AuditEventQuery::class)->page($this->owner, perPage: 1, cursor: $cursor);
        $seen = [...$seen, ...$page->items->pluck('action')->all()];
        $cursor = $page->nextCursor;
    } while ($cursor !== null);

    expect($seen)->toBe(['event.n2', 'event.n1', 'event.n0']);
});

it('refuses a forged cursor', function () {
    app(AuditEventQuery::class)->page($this->owner, cursor: base64_encode('["1 or 1=1", 1]'));
})->throws(InvalidArgumentException::class);

it('finds a backdated command by the gap between its business date and its recorded day (LG-01; TM-44)', function () {
    // 2026-10-25 00:30 WIB: a command dated five WIB days earlier, one dated the same day, one undated.
    auditAt('2026-10-24T17:30:00Z', 'backdated.five_days', '2026-10-20');
    auditAt('2026-10-24T17:30:00Z', 'same.day', '2026-10-25');
    auditAt('2026-10-24T17:30:00Z', 'undated');

    $backdated = fn (int $days) => app(AuditEventQuery::class)->page($this->owner, minimumBackdatedDays: $days)->items->pluck('action')->all();

    expect($backdated(5))->toBe(['backdated.five_days'])
        ->and($backdated(6))->toBe([])
        ->and($backdated(0))->toEqualCanonicalizing(['backdated.five_days', 'same.day']);
});

it('filters the security log by kind and by a searched identifier through its keyed hash (LG-03)', function () {
    $recorder = app(SecurityEventRecorder::class);
    $recorder->record(SecurityEventKind::LoginFailed, identifier: 'owner@example.com', ipAddress: '203.0.113.7');
    $recorder->record(SecurityEventKind::LoginFailed, identifier: 'admin@example.com');
    $recorder->record(SecurityEventKind::Logout, userId: $this->owner->id);

    $kinds = fn (array $filters) => app(SecurityEventQuery::class)->page($this->owner, ...$filters)->items->pluck('event_kind')->all();

    expect($kinds(['kinds' => [SecurityEventKind::LoginFailed]]))->toBe(['LOGIN_FAILED', 'LOGIN_FAILED'])
        ->and($kinds(['identifier' => ' OWNER@example.com']))->toBe(['LOGIN_FAILED'])
        ->and($kinds(['ipAddress' => '203.0.113.7']))->toBe(['LOGIN_FAILED'])
        ->and($kinds(['userId' => $this->owner->id]))->toBe(['LOGOUT']);
});
