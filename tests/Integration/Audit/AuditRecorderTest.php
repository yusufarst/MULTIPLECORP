<?php

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\AuditEvent;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditLinkage;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuditRelation;
use App\Support\Audit\AuthorityClass;
use App\Support\CorrelationId;
use App\Support\Redaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;

// TL-05 (TASK-010): the business audit is written in its command's own transaction (SECURITY LG-01;
// DATABASE §23; architecture challenge 10b). The tests commit, so they run without a test-wide transaction.

uses(DatabaseTruncation::class);

/**
 * A test command: renames an account and audits it in one transaction.
 */
function renameAudited(User $user, string $name, ?Closure $after = null): void
{
    DB::transaction(function () use ($user, $name, $after) {
        $before = ['name' => $user->name];
        DB::table('users')->where('id', $user->id)->update(['name' => $name]);

        app(AuditRecorder::class)->record(
            action: 'users.rename',
            authorityClass: AuthorityClass::AdmPlus,
            actorUserId: $user->id,
            companyId: null,
            entityType: 'users',
            entityId: $user->id,
            entityPublicId: $user->public_id,
            reason: 'Nama mengikuti KTP',
            commandId: '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
            change: AuditChange::updated($before, ['name' => $name]),
        );

        $after?->__invoke();
    });
}

it('commits the audit row with its command', function () {
    $user = User::factory()->create(['name' => 'Lama']);

    renameAudited($user, 'Baru');

    $event = AuditEvent::query()->sole();

    expect($event->action)->toBe('users.rename')
        ->and($event->actor_user_id)->toBe($user->id)
        ->and($event->entity_public_id)->toBe($user->public_id)
        ->and($event->authority_class)->toBe('ADM_PLUS')
        ->and($event->reason)->toBe('Nama mengikuti KTP')
        ->and($event->command_id)->toBe('0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b')
        ->and($event->correlation_id)->toBe(CorrelationId::current())
        ->and($event->before)->toBe(['name' => 'Lama'])
        ->and($event->after)->toBe(['name' => 'Baru']);
});

it('leaves no audit row when its command rolls back', function () {
    $user = User::factory()->create(['name' => 'Lama']);

    expect(fn () => renameAudited($user, 'Baru', fn () => throw new RuntimeException('refused')))->toThrow(RuntimeException::class);

    expect(AuditEvent::query()->count())->toBe(0)
        ->and(DB::table('users')->where('id', $user->id)->value('name'))->toBe('Lama');
});

it('rolls its command back when the audit row cannot be written', function () {
    $user = User::factory()->create(['name' => 'Lama']);

    expect(fn () => DB::transaction(function () use ($user) {
        DB::table('users')->where('id', $user->id)->update(['name' => 'Baru']);
        app(AuditRecorder::class)->record('users.rename', AuthorityClass::Adm, 999_999_999, null);
    }))->toThrow(QueryException::class);

    expect(DB::table('users')->where('id', $user->id)->value('name'))->toBe('Lama');
});

it('refuses to write outside a transaction', function () {
    app(AuditRecorder::class)->record('users.rename', AuthorityClass::System, null, null);
})->throws(LogicException::class);

it('keeps the audit row when the process ends right after the commit (10b)', function () {
    $user = User::factory()->create(['name' => 'Lama']);

    $id = $user->id;
    $correlationId = CorrelationId::start();

    // The other process has no test file loaded, so its command is written out here.
    expect(fn () => Sessions::run([
        'command' => static function () use ($id): void {
            DB::transaction(function () use ($id) {
                DB::table('users')->where('id', $id)->update(['name' => 'Baru']);
                app(AuditRecorder::class)->record('users.rename', AuthorityClass::AdmPlus, $id, null, 'users', $id,
                    change: AuditChange::updated(['name' => 'Lama'], ['name' => 'Baru']));
            });
            // The process ends at once, before any later step of the request could run.
            posix_kill(getmypid(), 9);
        },
    ]))->toThrow(RuntimeException::class, 'signal "9"');

    expect(AuditEvent::query()->sole()->after)->toBe(['name' => 'Baru'])
        // The correlation id flows into the other process with the framework's context.
        ->and(AuditEvent::query()->sole()->correlation_id)->toBe($correlationId)
        ->and(DB::table('users')->where('id', $user->id)->value('name'))->toBe('Baru');
});

it('records an operator command without an actor, a backdated business date and the linkage (LG-01; BR-CR-01)', function () {
    $original = '0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

    DB::transaction(fn () => app(AuditRecorder::class)->record(
        action: 'guard_maintenance.run',
        authorityClass: AuthorityClass::System,
        actorUserId: null,
        companyId: null,
        change: AuditChange::none(),
        linkage: AuditLinkage::make()->link(AuditRelation::Reverses, 'stock_movements', $original),
        businessDate: '2026-10-20',
        metadata: ['operator' => 'ops-1', 'token' => 'never-stored'],
    ));

    $event = AuditEvent::query()->sole();

    expect($event->actor_user_id)->toBeNull()
        ->and($event->authority_class)->toBe('SYSTEM')
        ->and($event->before)->toBeNull()
        ->and($event->metadata)->toEqual([
            'links' => [['relation' => 'REVERSES', 'entity_type' => 'stock_movements', 'public_id' => $original]],
            'token' => Redaction::MARK,
            'operator' => 'ops-1',
            'business_date' => '2026-10-20',
        ]);
});

it('refuses an actorless event that is not SYSTEM, and a malformed action or business date', function (string $action, AuthorityClass $class, ?string $date) {
    DB::transaction(fn () => app(AuditRecorder::class)->record($action, $class, null, null, businessDate: $date));
})->with([
    'an actorless ADM event' => ['users.rename', AuthorityClass::Adm, null],
    'an action with a line break' => ["users.rename\nforged", AuthorityClass::System, null],
    'an impossible day' => ['users.rename', AuthorityClass::System, '2026-02-30'],
])->throws(InvalidArgumentException::class);
