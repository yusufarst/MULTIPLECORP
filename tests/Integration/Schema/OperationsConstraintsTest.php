<?php

use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\CommandLog;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\OPS\Models\SecurityEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-005): DATABASE §4.13's constraints on the OPS tables, each violated as the application
// runtime and refused by PostgreSQL — C-46 on `correction_cases`, C-47 on the import tables, the
// command log's lifecycle and purge (§19; JB-08) and the security log's kinds and typed columns
// (SECURITY LG-02; TECH-022).

uses(RefreshDatabase::class);

it('closes a case only without residuals (C-46)', function (string $counter) {
    $case = CorrectionCase::factory()->create([$counter => '150000.00']);

    Refusal::assert(Refusal::CHECK, 'correction_cases_c46_no_residual_when_closed', fn () => $case->forceFill(['state' => 'CLOSED', 'closed_at' => now()])->save());

    $case->forceFill([$counter => '0.00', 'state' => 'CLOSED', 'closed_at' => now()])->save();

    expect($case->fresh()?->state)->toBe('CLOSED');
})->with(['unbacked_refund_amount', 'residual_settlement_amount']);

it('closes a purchase-return case only when its equation balances over posted figures (C-46)', function () {
    $case = CorrectionCase::factory()->create([
        'case_kind' => 'PURCHASE_RETURN',
        'returned_cost' => '1000000.00',
        'refunded_value' => '600000.00',
        'replaced_value' => '300000.00',
        'unrecovered_value' => '99999.99',
    ]);

    Refusal::assert(Refusal::CHECK, 'correction_cases_c46_purchase_return_equation', fn () => $case->forceFill(['state' => 'CLOSED'])->save());

    $case->forceFill(['unrecovered_value' => '100000.00', 'state' => 'CLOSED'])->save();

    // A later cost correction that unbalances the closed case must reopen it in the same transaction.
    Refusal::assert(Refusal::CHECK, 'correction_cases_c46_purchase_return_equation', fn () => $case->forceFill(['returned_cost' => '1000500.00'])->save());

    $case->forceFill(['returned_cost' => '1000500.00', 'state' => 'OPEN', 'reopened_at' => now(), 'reopen_reason' => 'Koreksi biaya contoh'])->save();

    expect($case->fresh()?->state)->toBe('OPEN');
});

it('refuses an unknown case kind or state', function () {
    Refusal::assert(Refusal::CHECK, 'correction_cases_kind_code', fn () => CorrectionCase::factory()->create(['case_kind' => 'REFUND']));
    Refusal::assert(Refusal::CHECK, 'correction_cases_state_code', fn () => CorrectionCase::factory()->create(['state' => 'VOIDED']));
});

it('refuses a second batch of one source for one import kind (C-47)', function () {
    $batch = ImportBatch::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'import_batches_c47_source', fn () => ImportBatch::factory()->create(['import_kind' => $batch->import_kind, 'source_sha256' => $batch->source_sha256]));

    expect(ImportBatch::factory()->create(['import_kind' => 'CONTOH_LAIN', 'source_sha256' => $batch->source_sha256])->exists)->toBeTrue();
});

it('refuses a source hash that is not a SHA-256 in lowercase hexadecimal', function () {
    Refusal::assert(Refusal::CHECK, 'import_batches_source_sha256_format', fn () => ImportBatch::factory()->create(['source_sha256' => strtoupper(hash('sha256', 'contoh'))]));
});

it('commits one legacy identity once per import kind, so a rerun creates nothing twice (C-47; AX-28)', function () {
    $first = ImportBatch::factory()->create();
    $rerun = ImportBatch::factory()->create();
    ImportRow::factory()->for($first, 'batch')->create(['import_identity' => 'PELANGGAN-7', 'validation_state' => 'COMMITTED']);

    // The rerun's row for the same legacy identity is found, never committed again.
    $again = ImportRow::factory()->for($rerun, 'batch')->create(['import_identity' => 'PELANGGAN-7', 'validation_state' => 'VALID']);

    Refusal::assert(Refusal::UNIQUE, 'import_rows_c47_committed_identity', fn () => $again->forceFill(['validation_state' => 'COMMITTED'])->save());

    $again->forceFill(['validation_state' => 'DUPLICATE'])->save();

    expect(ImportRow::query()->where('import_identity', 'PELANGGAN-7')->count())->toBe(2);
});

it('keeps one row per row number of a batch and refuses an unknown validation state', function () {
    $row = ImportRow::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'import_rows_row_number', fn () => ImportRow::factory()->create(['import_batch_id' => $row->import_batch_id, 'row_number' => $row->row_number]));
    Refusal::assert(Refusal::CHECK, 'import_rows_validation_state_code', fn () => ImportRow::factory()->create(['validation_state' => 'PENDING']));
});

it('freezes a committed import row (DATABASE §28)', function () {
    $row = ImportRow::factory()->create(['validation_state' => 'VALID']);
    $row->forceFill(['validation_state' => 'COMMITTED', 'result_entity_type' => 'contoh', 'result_entity_id' => 1])->save();

    Refusal::assert(Refusal::CHECK, 'A committed import row never changes', fn () => $row->forceFill(['errors' => ['ulang' => true]])->save());
});

it('writes a terminal command outcome with its completion and expiry (CI-03, CI-07)', function () {
    $commandId = (string) Str::uuid7();
    DB::table('command_log')->insert(['command_id' => $commandId, 'command_type' => 'contoh.perintah', 'payload_fingerprint' => 'v1:'.hash('sha256', 'contoh'), 'state' => 'IN_PROGRESS']);

    Refusal::assert(Refusal::CHECK, 'command_log_terminal_expiry', fn () => DB::table('command_log')->where('command_id', $commandId)->update(['state' => 'COMMITTED']));
    Refusal::assert(Refusal::CHECK, 'command_log_state_code', fn () => DB::table('command_log')->where('command_id', $commandId)->update(['state' => 'FAILED', 'completed_at' => now(), 'expires_at' => now()->addDays(30)]));

    DB::table('command_log')->where('command_id', $commandId)->update(['state' => 'REJECTED', 'outcome_code' => 'AZ-08', 'completed_at' => now(), 'expires_at' => now()->addDays(30)]);

    Refusal::assert(Refusal::UNIQUE, 'command_log_pkey', fn () => DB::table('command_log')->insert(['command_id' => $commandId, 'command_type' => 'contoh.perintah', 'payload_fingerprint' => 'v1:'.hash('sha256', 'lain'), 'state' => 'IN_PROGRESS']));
});

it('deletes only an expired command log row (DATABASE §19; JB-08)', function () {
    $live = CommandLog::factory()->create(['expires_at' => now()->addDay()]);
    $expired = CommandLog::factory()->create(['completed_at' => now()->subDays(31), 'expires_at' => now()->subDay()]);

    Refusal::assert(Refusal::CHECK, 'Only an expired command log row is deleted', fn () => DB::table('command_log')->where('command_id', $live->command_id)->delete());

    expect(DB::table('command_log')->where('expires_at', '<', now())->delete())->toBe(1)
        ->and(CommandLog::query()->pluck('command_id')->all())->toBe([$live->command_id])
        ->and($expired->command_id)->not->toBe($live->command_id);
});

it('refuses a security event kind outside SECURITY LG-02', function () {
    Refusal::assert(Refusal::CHECK, 'security_events_kind', fn () => SecurityEvent::factory()->create(['event_kind' => 'LOGIN_ATTEMPTED']));
});

it('gives the two undisclosed-match kinds their typed columns and every other kind none (TECH-022)', function () {
    $typed = [
        'command_id' => (string) Str::uuid7(),
        'target_entity_type' => 'evidence_document',
        'target_public_id' => (string) Str::uuid7(),
        'related_entity_type' => 'evidence_document',
        'related_public_id' => (string) Str::uuid7(),
        'company_id' => 1001,
        'related_company_id' => 1002,
    ];
    $user = User::factory()->create();

    SecurityEvent::factory()->create(['event_kind' => 'EVIDENCE_DUPLICATE_UNDISCLOSED', 'user_id' => $user->id, ...$typed]);

    Refusal::assert(Refusal::UNIQUE, 'security_events_undisclosed_unique', fn () => SecurityEvent::factory()->create(['event_kind' => 'EVIDENCE_DUPLICATE_UNDISCLOSED', ...$typed]));
    Refusal::assert(Refusal::CHECK, 'security_events_undisclosed_columns', fn () => SecurityEvent::factory()->create(['event_kind' => 'LOGIN_FAILED', 'command_id' => $typed['command_id']]));
    Refusal::assert(Refusal::CHECK, 'security_events_undisclosed_columns', fn () => SecurityEvent::factory()->create(['event_kind' => 'IMPORT_COLLISION_UNDISCLOSED', 'command_id' => $typed['command_id']]));

    // A pool evidence document and a batch have no company: both sides may stay empty.
    expect(SecurityEvent::factory()->create(['event_kind' => 'IMPORT_COLLISION_UNDISCLOSED', ...$typed, 'company_id' => null, 'related_company_id' => null])->exists)->toBeTrue();
});
