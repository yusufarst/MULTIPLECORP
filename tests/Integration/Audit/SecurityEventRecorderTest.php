<?php

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\AuditEvent;
use App\Modules\OPS\Models\SecurityEvent;
use App\Support\CorrelationId;
use App\Support\SecurityLog\SecurityEventKind;
use App\Support\SecurityLog\SecurityEventRecorder;
use App\Support\SecurityLog\SecurityLogHash;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\DatabaseTruncation;

// TL-05 (TASK-010): the security events of SECURITY LG-02–LG-04, written on a connection of their own,
// apart from the business audit. The tests commit, so they run without a test-wide transaction.

uses(DatabaseTruncation::class);

function securityEvents(): SecurityEventRecorder
{
    return app(SecurityEventRecorder::class);
}

/**
 * The messages logged while the callback runs.
 *
 * @return list<MessageLogged>
 */
function loggedDuring(Closure $callback): array
{
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged) {
        $logged[] = $message;
    });

    $callback();

    return $logged;
}

function undisclosedDuplicate(int $userId, string $relatedPublicId = '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d', SecurityEventKind $kind = SecurityEventKind::EvidenceDuplicateUndisclosed): void
{
    securityEvents()->recordUndisclosed(
        kind: $kind,
        userId: $userId,
        commandId: '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
        targetEntityType: 'documents',
        targetPublicId: '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c',
        relatedEntityType: 'documents',
        relatedPublicId: $relatedPublicId,
        companyId: 1,
        relatedCompanyId: 2,
        reasonCode: 'SHA256',
    );
}

it('writes the event of a command that rolls back once its transaction has ended (LG-02)', function () {
    $user = User::factory()->create();

    DB::beginTransaction();
    DB::table('users')->where('id', $user->id)->update(['name' => 'Ditolak']);
    securityEvents()->record(SecurityEventKind::AccessDenied, userId: $user->id, routeName: 'invoices.show', targetPublicId: $user->public_id);

    expect(SecurityEvent::query()->count())->toBe(0);

    DB::rollBack();

    expect(SecurityEvent::query()->sole()->event_kind)->toBe('ACCESS_DENIED')
        ->and(DB::table('users')->where('id', $user->id)->value('name'))->not->toBe('Ditolak');
});

it('never waits on the lock its own transaction holds on the event\'s account', function () {
    $user = User::factory()->create();

    $logged = loggedDuring(function () use ($user) {
        DB::transaction(function () use ($user) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $started = hrtime(true);
            securityEvents()->record(SecurityEventKind::PasswordChanged, userId: $user->id);

            expect(hrtime(true) - $started)->toBeLessThan(500_000_000);
        });
    });

    expect(SecurityEvent::query()->sole()->event_kind)->toBe('PASSWORD_CHANGED')
        ->and($logged)->toBe([]);
});

it('writes once when a savepoint rolls back and its outer transaction commits', function () {
    $user = User::factory()->create();

    DB::transaction(function () use ($user) {
        try {
            DB::transaction(function () use ($user) {
                securityEvents()->record(SecurityEventKind::PasswordConfirmationFailed, userId: $user->id);

                throw new RuntimeException('refused');
            });
        } catch (RuntimeException) {
        }
    });

    expect(SecurityEvent::query()->count())->toBe(1);
});

it('keeps the event of a refused attempt and leaves the business audit untouched', function () {
    $user = User::factory()->create();

    try {
        DB::transaction(function () use ($user) {
            securityEvents()->record(SecurityEventKind::PasswordConfirmationFailed, userId: $user->id, reasonCode: 'WRONG_PASSWORD');

            throw new RuntimeException('refused');
        });
    } catch (RuntimeException) {
    }

    expect(SecurityEvent::query()->sole()->reason_code)->toBe('WRONG_PASSWORD')
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('writes every kind of LG-02 into the security log and none into the business audit', function () {
    $user = User::factory()->create();

    foreach (SecurityEventKind::cases() as $kind) {
        $kind->isUndisclosed() ? undisclosedDuplicate($user->id, (string) Str::uuid7(), $kind) : securityEvents()->record($kind, userId: $user->id);
    }

    expect(SecurityEvent::query()->count())->toBe(39)
        ->and(SecurityEvent::query()->distinct()->pluck('event_kind')->count())->toBe(39)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('lists the kinds of the security_events_kind CHECK', function () {
    $definition = (string) DB::scalar("select pg_get_constraintdef(oid) from pg_constraint where conname = 'security_events_kind'");
    preg_match_all("/'([A-Z_]+)'/", $definition, $codes);

    expect(array_map(fn (SecurityEventKind $kind): string => $kind->value, SecurityEventKind::cases()))->toEqualCanonicalizing($codes[1])
        ->and(SecurityEventKind::cases())->toHaveCount(39);
});

it('stores an identifier, an address and a provider subject only as keyed hashes, and a user agent as its summary (LG-03)', function () {
    $user = User::factory()->create();
    $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    securityEvents()->record(SecurityEventKind::LoginFailed, identifier: 'Owner@Example.com', ipAddress: '203.0.113.7', userAgent: $agent);
    securityEvents()->record(SecurityEventKind::GoogleSigninFailed, userId: $user->id, providerSubject: '1098765432101234567890');

    $failed = SecurityEvent::query()->where('event_kind', 'LOGIN_FAILED')->sole();
    $rows = json_encode(DB::table('security_events')->get());

    expect($failed->identifier_hash)->toBe(SecurityLogHash::identifier('owner@example.com'))
        ->and($failed->ip_hash)->toBe(SecurityLogHash::address('203.0.113.7'))
        ->and($failed->user_agent_summary)->toBe('Chrome 129 on Windows')
        ->and($failed->correlation_id)->toBe(CorrelationId::current())
        ->and(SecurityEvent::query()->where('event_kind', 'GOOGLE_SIGNIN_FAILED')->sole()->identifier_hash)->toBe(SecurityLogHash::subject('1098765432101234567890'))
        ->and($rows)->not->toContain('Owner@Example.com', 'owner@example.com', '203.0.113.7', '1098765432101234567890', 'AppleWebKit');
});

it('keeps only the whitelisted details, neutralized, and drops every other key (LG-02, LG-04)', function () {
    $logged = loggedDuring(fn () => securityEvents()->record(SecurityEventKind::TwoFactorCooldownStarted, details: [
        'path' => "PASSWORD\nFORGED",
        'escalation_level' => 2,
        'code' => '123456',
        'password' => 'kata-sandi-salah',
        'payload' => 'full request',
    ]));

    expect(SecurityEvent::query()->sole()->details)->toEqual(['path' => 'PASSWORD\nFORGED', 'escalation_level' => 2])
        ->and(json_encode(DB::table('security_events')->get()))->not->toContain('123456', 'kata-sandi-salah', 'full request')
        ->and($logged[0]->level)->toBe('warning')
        ->and($logged[0]->context['keys'])->toBe(['code', 'password', 'payload']);
});

it('changes no outcome when the event cannot be written, and logs it with the correlation id (LG-02, LG-04)', function () {
    $user = User::factory()->create(['name' => 'Lama']);
    config(['database.connections.'.SecurityEventRecorder::CONNECTION.'.database' => 'multiplecorp_no_such_database']);
    DB::purge(SecurityEventRecorder::CONNECTION);

    $logged = loggedDuring(fn () => DB::transaction(function () use ($user) {
        DB::table('users')->where('id', $user->id)->update(['name' => 'Baru']);
        securityEvents()->record(SecurityEventKind::PasswordChanged, userId: $user->id);
    }));

    expect(DB::table('users')->where('id', $user->id)->value('name'))->toBe('Baru')
        ->and(SecurityEvent::query()->count())->toBe(0)
        ->and($logged)->toHaveCount(1)
        ->and($logged[0]->level)->toBe('error')
        ->and($logged[0]->context)->toMatchArray(['event_kind' => 'PASSWORD_CHANGED', 'correlation_id' => CorrelationId::current()]);
});

it('logs a missing hash key as a failed write and throws nothing', function () {
    config(['security.log_hmac_key' => null]);

    $logged = loggedDuring(fn () => securityEvents()->record(SecurityEventKind::LoginFailed, identifier: 'owner@example.com'));

    expect(SecurityEvent::query()->count())->toBe(0)
        ->and($logged[0]->context['error'])->toBe(RuntimeException::class)
        ->and(json_encode($logged))->not->toContain('owner@example.com');
});

it('writes a TECH-022 kind only once its command commits, once per command and matched record (LG-02)', function () {
    $user = User::factory()->create();

    DB::transaction(function () use ($user) {
        undisclosedDuplicate($user->id);

        expect(SecurityEvent::query()->count())->toBe(0);
    });

    expect(SecurityEvent::query()->sole())
        ->event_kind->toBe('EVIDENCE_DUPLICATE_UNDISCLOSED')
        ->command_id->toBe('0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b')
        ->related_public_id->toBe('0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d')
        ->company_id->toBe(1)
        ->related_company_id->toBe(2);

    undisclosedDuplicate($user->id);
    DB::transaction(fn () => undisclosedDuplicate($user->id));
    undisclosedDuplicate($user->id, '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5e');

    expect(SecurityEvent::query()->count())->toBe(2);
});

it('writes no TECH-022 event for a command that rolls back', function () {
    $user = User::factory()->create();

    try {
        DB::transaction(function () use ($user) {
            undisclosedDuplicate($user->id);

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    expect(SecurityEvent::query()->count())->toBe(0);
});

it('keeps each writer to its own kinds', function (Closure $write) {
    $write();
})->with([
    'a TECH-022 kind through record()' => [fn () => securityEvents()->record(SecurityEventKind::ImportCollisionUndisclosed)],
    'another kind through recordUndisclosed()' => [fn () => securityEvents()->recordUndisclosed(SecurityEventKind::LoginFailed, 1, '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b', 'documents', '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c', 'documents', '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d', null, null, 'SHA256')],
])->throws(LogicException::class);
