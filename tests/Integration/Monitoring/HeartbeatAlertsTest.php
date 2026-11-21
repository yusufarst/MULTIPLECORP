<?php

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Support\Backup\BackupLog;
use App\Support\Heartbeat;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;

// TL-05 (TASK-075; INFRASTRUCTURE §6; GAP-013; HO-25; BK-10; BK-11; acceptance criteria 1–3), on real PostgreSQL: seeded
// faults — a failed job, an owed and an exhausted rendition, the scheduler's tasks without a success, the disk past a
// lowered warning level, a reconciliation difference, a deadlock, a failed backup and a failed copy — each raise their
// alert through the simulated channel as a neutral signal name; a healthy heartbeat raises none; a slow statement is
// logged with its text and no value.

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'monitoring.channel.url' => 'https://email.example.invalid/send',
        'monitoring.channel.token' => 'token-uji',
        'monitoring.recipients' => ['operator@example.invalid'],
    ]);
    Cache::flush();
    Http::fake(['email.example.invalid/*' => Http::response(['success' => true])]);
});

/** @return list<string> the subjects the simulated channel received */
function alertSubjects(): array
{
    return collect(Http::recorded())->map(fn (array $pair): string => (string) ($pair[0]['subject'] ?? ''))->values()->all();
}

it('raises nothing while every heartbeat signal passes (criterion 1)', function () {
    foreach (array_keys(Heartbeat::TASKS) as $task) {
        (Heartbeat::stamp($task))();
    }

    // The runner's own disk is not the fault under test: its thresholds sit above any usage here.
    config(['monitoring.heartbeat.disk_warn_pct' => 101, 'monitoring.heartbeat.disk_critical_pct' => 101]);

    $this->artisan('ops:heartbeat')->doesntExpectOutputToContain('FAIL')->expectsOutputToContain('every signal passes')->assertExitCode(0);
    expect(alertSubjects())->toBe([]);
});

it('raises a probe that cannot read as its signal unavailable and still reads the others (Codex on #99)', function () {
    foreach (array_keys(Heartbeat::TASKS) as $task) {
        (Heartbeat::stamp($task))();
    }

    config(['monitoring.heartbeat.disk_warn_pct' => 101, 'monitoring.heartbeat.disk_critical_pct' => 101]);
    Queue::shouldReceive('size')->andThrow(new RuntimeException('the queue store is down'));

    $this->artisan('ops:heartbeat')->expectsOutputToContain('FAIL queue — unavailable: RuntimeException')->assertExitCode(0);

    expect(alertSubjects())->toBe(['MULTIPLECORP alert: Heartbeat: queue failing (INFRASTRUCTURE §6).']);
});

it('raises each seeded heartbeat fault as its alert (criterion 1; GAP-013; BK-11)', function () {
    DocumentTypeFactory::ensure('DOC-09');
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'contoh', 'failed_at' => now()]);
    $owed = DocumentVersion::factory()->issued()->for(Document::factory(), 'document')->create();
    (new DocumentRendition)->forceFill(['document_version_id' => $owed->id, 'attempt_no' => 1, 'state' => 'PENDING', 'recorded_at' => CarbonImmutable::now()->subHour()])->save();
    $exhausted = DocumentVersion::factory()->issued()->for(Document::factory(), 'document')->create();
    (new DocumentRendition)->forceFill(['document_version_id' => $exhausted->id, 'attempt_no' => 5, 'state' => 'FAILED', 'recorded_at' => CarbonImmutable::now()->subHour()])->save();
    config(['monitoring.heartbeat.disk_warn_pct' => 1, 'monitoring.heartbeat.disk_critical_pct' => 2]);

    $this->artisan('ops:heartbeat')->expectsOutputToContain('failing, alerted')->assertExitCode(0);

    expect(alertSubjects())->toContain(
        'MULTIPLECORP alert: Heartbeat: failed jobs failing (INFRASTRUCTURE §6).',
        'MULTIPLECORP alert: Heartbeat: renditions failing (INFRASTRUCTURE §6).',
        'MULTIPLECORP alert: Heartbeat: renditions exhausted failing (INFRASTRUCTURE §6).',
        'MULTIPLECORP alert: Heartbeat: scheduler guards:reconcile failing (INFRASTRUCTURE §6).',
        'MULTIPLECORP alert: Heartbeat: scheduler command-log:purge failing (INFRASTRUCTURE §6).',
        'MULTIPLECORP alert: Heartbeat: disk failing (INFRASTRUCTURE §6).',
    );

    // Once per signal per window while it stays failing.
    $before = count(alertSubjects());
    $this->artisan('ops:heartbeat')->assertExitCode(0);
    expect(count(alertSubjects()))->toBe($before);
});

it('raises HO-25\'s signals and the backup tooling\'s failures, and logs a slow statement without its values (criteria 2, 3; SZ-06)', function () {
    Log::warning('A guard differs from its facts (ReconcileGuards; GAP-031).', ['table' => 'invoice_balances', 'key' => ['invoice_id' => 7]]);
    Log::error('A command met a deadlock and runs again (SQ-13; RY-01).', ['command_type' => 'FIN.RecordPayment']);
    Log::error('A command failed (CONCURRENCY_IDEMPOTENCY §12).', ['command_type' => 'IAM.AssignRole', 'reason' => 'refused']);
    BackupLog::alert('the backup run failed', ['reason' => 'disk penuh 9876543']);
    BackupLog::alert('the off-server copy failed', ['generation' => '20261120T200000Z-abc1234']);

    expect(alertSubjects())->toBe([
        'MULTIPLECORP alert: HO-25 guard reconciliation difference',
        'MULTIPLECORP alert: HO-25 deadlock (40P01)',
        'MULTIPLECORP alert: HO-25 failed access change',
        'MULTIPLECORP alert: Backup alert (BK-10): the backup run failed',
        'MULTIPLECORP alert: Backup alert (BK-10): the off-server copy failed',
    ])->and(collect(Http::recorded())->contains(fn (array $pair): bool => str_contains($pair[0]->body(), '9876543') || str_contains($pair[0]->body(), 'invoice_id')))->toBeFalse();

    Log::spy();
    config(['monitoring.slow_statement_ms' => 0]);
    DB::select('select ? as nilai_rahasia', ['nilai-yang-tidak-boleh-tercatat']);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'slower than its bound')
        && str_contains((string) $context['sql'], 'nilai_rahasia') && ! str_contains(json_encode($context), 'nilai-yang-tidak-boleh-tercatat'))->atLeast()->once();
});

it('reports the heaviest statements and the largest tables for the monthly review, changing nothing (criterion 7; PFM-18)', function () {
    config(['monitoring.report.table_bytes' => 2]);

    $this->artisan('ops:statement-report', ['--top' => 3])->expectsOutputToContain('REVIEW')->expectsOutputToContain('nothing was changed')->assertExitCode(0);
});
