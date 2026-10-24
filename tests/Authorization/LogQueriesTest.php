<?php

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Queries\AuditEventQuery;
use App\Modules\OPS\Queries\SecurityEventQuery;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\RefreshDatabase;

// TL-06 (TASK-010): the read models of PG-063 and PG-064 return rows only through `audit.view` and
// `security_log.view` (OWNER_ONLY; SECURITY LG-05). TASK-011 registers the capabilities as gates; here
// each gate grants one account only.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();

    DB::transaction(fn () => app(AuditRecorder::class)->record('users.rename', AuthorityClass::Adm, $this->admin->id, null));
    app(SecurityEventRecorder::class)->record(SecurityEventKind::AccessDenied, userId: $this->admin->id);
});

it('refuses each read to an account without its capability', function (string $query) {
    Gate::define(AuditEventQuery::CAPABILITY, fn (User $user) => $user->is($this->owner));
    Gate::define(SecurityEventQuery::CAPABILITY, fn (User $user) => $user->is($this->owner));

    expect(fn () => app($query)->page($this->admin))->toThrow(AuthorizationException::class)
        ->and(app($query)->page($this->owner)->items)->toHaveCount(1);
})->with([
    'PG-063 under audit.view' => [AuditEventQuery::class],
    'PG-064 under security_log.view' => [SecurityEventQuery::class],
]);

it('refuses each read while its capability is not registered at all', function (string $query) {
    app($query)->page($this->owner);
})->with([AuditEventQuery::class, SecurityEventQuery::class])->throws(AuthorizationException::class);

it('does not let one capability open the other read', function () {
    Gate::define(AuditEventQuery::CAPABILITY, fn () => true);

    app(SecurityEventQuery::class)->page($this->owner);
})->throws(AuthorizationException::class);
