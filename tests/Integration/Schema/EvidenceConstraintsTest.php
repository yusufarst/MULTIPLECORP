<?php

use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\DOC\Models\EvidenceBalance;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\EvidenceLink;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\CorrectionCase;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-006), the evidence rows of DATABASE §18 on these tables (§4.10, §22): one evidence per
// identity in its company and type, a re-attachment being its new version (C-49); pool evidence of no
// company and of the pool types, linked only to pool records, and statement lines citing BANK_STATEMENT
// evidence only (C-58); one active line per real bank receipt, voided once (C-19; C-59); what is cited
// and expensed never above what is proven (C-18–C-20; C-23); lines and links within their company
// (C-32); files and versions as stored (C-53).

uses(RefreshDatabase::class);

/**
 * A guard row, written as EvidencePosting writes it: a balance has no factory (ARCHITECTURE §7, §19).
 *
 * @param  array<string, mixed>  $columns
 */
function guardRow(array $columns): int
{
    return DB::table('evidence_balances')->insertGetId(['proven_amount' => '1000000.00', ...$columns]);
}

/** @param  array<string, mixed>  $columns */
function linkEvidence(EvidenceDocument $evidence, array $columns): void
{
    DB::table('evidence_links')->insert([
        'evidence_document_id' => $evidence->id,
        'evidence_scope' => $evidence->evidence_scope,
        'company_id' => $evidence->company_id,
        'role' => 'SUPPORTING',
        ...$columns,
    ]);
}

it('keeps one evidence per issuer reference in its company and type, a re-attachment being its new version (C-49; L-41, L-45)', function () {
    $evidence = EvidenceDocument::factory()->create();
    $identity = $evidence->only(['company_id', 'evidence_type', 'declared_issuer', 'issuer_reference', 'document_date', 'identity_key']);

    Refusal::assert(Refusal::UNIQUE, 'evidence_documents_c49_identity', fn () => EvidenceDocument::factory()->create($identity));

    // Pool evidence, of no company, keeps one registration per identity too.
    $pool = EvidenceDocument::factory()->pool()->create(['issuer_reference' => 'BA/GDG/1', 'identity_key' => 'gudang bersama|WAREHOUSE_RECORD|ba/gdg/1|2026-10-01']);

    Refusal::assert(Refusal::UNIQUE, 'evidence_documents_c49_identity', fn () => EvidenceDocument::factory()->pool()->create($pool->only(['evidence_type', 'issuer_reference', 'identity_key'])));

    // Another company's document is another identity; one registered again under another type gains no
    // second capacity in any role (C-58) and is the duplicate warning's, as is a document without the
    // issuer's reference (P5).
    expect(EvidenceDocument::factory()->create([...$identity, 'company_id' => Company::factory()->create()->id])->exists)->toBeTrue()
        ->and(EvidenceDocument::factory()->create([...$identity, 'evidence_type' => 'OTHER'])->exists)->toBeTrue()
        ->and(EvidenceDocument::factory()->create([...$identity, 'issuer_reference' => null])->exists)->toBeTrue()
        ->and(EvidenceDocument::factory()->create([...$identity, 'issuer_reference' => null])->exists)->toBeTrue();

    $first = EvidenceDocumentVersion::factory()->for($evidence, 'evidence')->create();
    $evidence->forceFill(['current_version_id' => $first->id])->save();
    $second = EvidenceDocumentVersion::factory()->for($evidence, 'evidence')->create(['version_no' => 2, 'reason' => 'Pindaian yang lebih jelas']);
    $evidence->forceFill(['current_version_id' => $second->id])->save();

    Refusal::assert(Refusal::UNIQUE, 'evidence_document_versions_number_unique', fn () => EvidenceDocumentVersion::factory()->for($evidence, 'evidence')->create(['version_no' => 2]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_documents_current_version_foreign', fn () => DB::table('evidence_documents')->where('id', $evidence->id)->update(['current_version_id' => EvidenceDocumentVersion::factory()->create()->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table evidence_documents', fn () => DB::table('evidence_documents')->where('id', $evidence->id)->update(['identity_key' => 'lain']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table evidence_document_versions', fn () => DB::table('evidence_document_versions')->where('id', $first->id)->update(['file_object_id' => FileObject::factory()->create()->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table evidence_document_versions', fn () => DB::table('evidence_document_versions')->where('id', $first->id)->delete());

    expect($evidence->refresh()->currentVersion?->is($second))->toBeTrue()
        ->and($evidence->versions()->count())->toBe(2);
});

it('keeps pool evidence of no company and of the pool types, and company evidence in its company (C-58; DIR-027)', function (array $attributes, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => EvidenceDocument::factory()->create($attributes));
})->with([
    'pool evidence with a company' => [['evidence_scope' => 'POOL', 'evidence_type' => 'WAREHOUSE_RECORD'], 'evidence_documents_c58_pool_has_no_company'],
    'company evidence without one' => [['company_id' => null], 'evidence_documents_c58_pool_has_no_company'],
    'a pool bank statement' => [['evidence_scope' => 'POOL', 'company_id' => null, 'evidence_type' => 'BANK_STATEMENT'], 'evidence_documents_c58_pool_types'],
    'a negative proven amount' => [['proven_amount' => '-0.01'], 'evidence_documents_proven_amount_not_negative'],
    'an unknown scope' => [['evidence_scope' => 'SHARED', 'evidence_type' => 'OTHER'], 'evidence_documents_scope_code'],
    'an unknown type' => [['evidence_type' => 'RECEIPT'], 'evidence_documents_type_code'],
]);

it('accepts pool evidence of each pool type', function (string $type) {
    expect(EvidenceDocument::factory()->pool()->create(['evidence_type' => $type])->company_id)->toBeNull();
})->with(['WAREHOUSE_RECORD', 'OPENING_SIGNOFF', 'OTHER']);

it('keeps one active line per real bank receipt, voided once with its reason and actor, the void freeing its identity (C-19; C-49; C-59)', function () {
    $line = BankStatementLine::factory()->create();
    $identity = $line->only(['company_id', 'company_bank_account_id', 'business_date', 'amount', 'line_reference']);
    $row = fn () => DB::table('bank_statement_lines')->where('id', $line->id);

    // The same receipt in a second, overlapping statement is the same line.
    Refusal::assert(Refusal::UNIQUE, 'bank_statement_lines_c19_identity', fn () => BankStatementLine::factory()->create($identity));
    Refusal::assert(Refusal::CHECK, 'bank_statement_lines_void_columns', fn () => $row()->update(['state' => 'VOIDED']));
    Refusal::assert(Refusal::CHECK, 'bank_statement_lines_c19_void_once', fn () => $row()->update(['voided_reason' => 'Tanpa pembatalan']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table bank_statement_lines', fn () => $row()->update(['amount' => '1.00']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table bank_statement_lines', fn () => $row()->delete());

    $row()->update(['state' => 'VOIDED', 'voided_by' => User::factory()->create()->id, 'voided_at' => now(), 'voided_reason' => 'Salah ketik saat entri']);

    Refusal::assert(Refusal::CHECK, 'bank_statement_lines_c19_void_once', fn () => $row()->update(['voided_reason' => 'Alasan lain']));
    Refusal::assert(Refusal::CHECK, 'bank_statement_lines_c19_void_once', fn () => $row()->update(['state' => 'ACTIVE', 'voided_by' => null, 'voided_at' => null, 'voided_reason' => null]));

    // The true line is entered; a VOIDED line holds no identity.
    expect(BankStatementLine::factory()->create($identity)->exists)->toBeTrue();
});

it('refuses a line of no amount or of an unknown state (§4.10)', function (array $attributes, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => BankStatementLine::factory()->create($attributes));
})->with([
    'zero' => [['amount' => '0.00'], 'bank_statement_lines_amount_positive'],
    'an unknown state' => [['state' => 'PENDING'], 'bank_statement_lines_state_code'],
]);

it('enters a line on its own company\'s account and BANK_STATEMENT evidence (C-32; C-58)', function () {
    [$own, $other] = [Company::factory()->create(), Company::factory()->create()];
    $account = CompanyBankAccount::factory()->create(['company_id' => $own->id]);
    $statement = EvidenceDocument::factory()->bankStatement()->create(['company_id' => $own->id]);

    expect(BankStatementLine::factory()->create(['company_id' => $own->id, 'company_bank_account_id' => $account->id, 'statement_evidence_id' => $statement->id])->exists)->toBeTrue();

    Refusal::assert(Refusal::FOREIGN_KEY, 'bank_statement_lines_c32_bank_account_foreign', fn () => BankStatementLine::factory()->create(['company_id' => $other->id, 'company_bank_account_id' => $account->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'bank_statement_lines_c32_statement_foreign', fn () => BankStatementLine::factory()->create(['company_id' => $other->id, 'statement_evidence_id' => $statement->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'bank_statement_lines_c58_statement_type_foreign', fn () => BankStatementLine::factory()->create([
        'company_id' => $own->id, 'company_bank_account_id' => $account->id, 'statement_evidence_id' => EvidenceDocument::factory()->create(['company_id' => $own->id])->id,
    ]));
    Refusal::assert(Refusal::CHECK, 'bank_statement_lines_c58_statement_type', fn () => BankStatementLine::factory()->create(['statement_evidence_type' => 'OTHER']));
});

it('guards one evidence or one bank line, each by one row (§3.1; §4.10)', function () {
    $evidence = EvidenceDocument::factory()->create();
    $line = BankStatementLine::factory()->create();
    $guard = guardRow(['evidence_document_id' => $evidence->id]);
    guardRow(['bank_statement_line_id' => $line->id, 'proven_amount' => $line->amount]);

    Refusal::assert(Refusal::UNIQUE, 'evidence_balances_evidence_unique', fn () => guardRow(['evidence_document_id' => $evidence->id]));
    Refusal::assert(Refusal::UNIQUE, 'evidence_balances_bank_line_unique', fn () => guardRow(['bank_statement_line_id' => $line->id]));
    Refusal::assert(Refusal::CHECK, 'evidence_balances_one_subject', fn () => guardRow(['evidence_document_id' => EvidenceDocument::factory()->create()->id, 'bank_statement_line_id' => BankStatementLine::factory()->create()->id]));
    Refusal::assert(Refusal::CHECK, 'evidence_balances_one_subject', fn () => guardRow([]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table evidence_balances', fn () => DB::table('evidence_balances')->where('id', $guard)->update(['evidence_document_id' => EvidenceDocument::factory()->create()->id]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table evidence_balances', fn () => DB::table('evidence_balances')->where('id', $guard)->delete());

    expect(EvidenceBalance::query()->findOrFail($guard)->evidence?->is($evidence))->toBeTrue()
        ->and($line->balance()->sole()->proven_amount)->toBe($line->amount);
});

it('cites and expenses no more than the evidence proves, a charge correction moving the bill\'s citation within its cap (C-18; C-20; C-23)', function () {
    $guard = guardRow(['evidence_document_id' => EvidenceDocument::factory()->create(['evidence_type' => 'SUPPLIER_BILL'])->id, 'proven_amount' => '1000000.00']);
    $balance = fn () => DB::table('evidence_balances')->where('id', $guard);

    $balance()->update(['cited_amount' => '1000000.00']);

    Refusal::assert(Refusal::CHECK, 'evidence_balances_c18_cited_within_proven', fn () => $balance()->update(['cited_amount' => '1000000.01']));
    Refusal::assert(Refusal::CHECK, 'evidence_balances_c18_cited_within_proven', fn () => $balance()->update(['cited_amount' => '-0.01']));

    // A charge corrected down moves the bill's citation by its delta; one corrected above the bill
    // needs the bill's evidence corrected first (DATABASE §6).
    $balance()->update(['cited_amount' => '600000.00']);

    Refusal::assert(Refusal::CHECK, 'evidence_balances_c18_cited_within_proven', fn () => $balance()->update(['proven_amount' => '599999.99']));

    // A fee is expensed at most once — at most its evidence (C-23).
    $balance()->update(['expensed_amount' => '1000000.00']);

    Refusal::assert(Refusal::CHECK, 'evidence_balances_c23_expensed_within_proven', fn () => $balance()->update(['expensed_amount' => '1000000.01']));
    Refusal::assert(Refusal::CHECK, 'evidence_balances_c23_expensed_within_proven', fn () => $balance()->update(['expensed_amount' => '-0.01']));

    expect(EvidenceBalance::query()->findOrFail($guard)->only(['proven_amount', 'cited_amount', 'expensed_amount']))
        ->toBe(['proven_amount' => '1000000.00', 'cited_amount' => '600000.00', 'expensed_amount' => '1000000.00']);
});

it('caps the Cash-In citing a bank line at its amount, the line\'s void proving 0 and so waiting for its citations to move (C-19; L-22)', function () {
    $line = BankStatementLine::factory()->create(['amount' => '1500000.00']);
    $guard = guardRow(['bank_statement_line_id' => $line->id, 'proven_amount' => '1500000.00']);
    $balance = fn () => DB::table('evidence_balances')->where('id', $guard);
    $void = function () use ($line, $balance): void {
        DB::table('bank_statement_lines')->where('id', $line->id)
            ->update(['state' => 'VOIDED', 'voided_by' => User::factory()->create()->id, 'voided_at' => now(), 'voided_reason' => 'Salah ketik saat entri']);
        $balance()->update(['proven_amount' => '0.00']);
    };

    $balance()->update(['cited_amount' => '1500000.00']);

    Refusal::assert(Refusal::CHECK, 'evidence_balances_c18_cited_within_proven', fn () => $balance()->update(['cited_amount' => '1500000.01']));
    Refusal::assert(Refusal::CHECK, 'evidence_balances_c18_cited_within_proven', $void);

    expect($line->refresh()->state)->toBe('ACTIVE');

    // The Cash-In's citation moves first, through its reasoned correction (DATABASE §11).
    $balance()->update(['cited_amount' => '0.00']);
    DB::transaction($void);

    expect($line->refresh()->state)->toBe('VOIDED')
        ->and(EvidenceBalance::query()->findOrFail($guard)->proven_amount)->toBe('0.00');
});

it('links company evidence within its company and pool evidence only to pool records, one target each (C-32; C-58; D-DB-10)', function () {
    [$own, $other] = [Company::factory()->create(), Company::factory()->create()];
    $evidence = EvidenceDocument::factory()->create(['company_id' => $own->id]);
    $pool = EvidenceDocument::factory()->pool()->create();

    linkEvidence($evidence, ['project_id' => 7]);
    linkEvidence($evidence, ['correction_case_id' => CorrectionCase::factory()->create(['company_id' => $own->id])->id]);
    linkEvidence($pool, ['stock_movement_id' => 7]);
    linkEvidence($pool, ['unattributed_loss_case_id' => 7]);
    EvidenceLink::factory()->create(['evidence_document_id' => $pool->id]);

    Refusal::assert(Refusal::CHECK, 'evidence_links_c58_pool_targets', fn () => linkEvidence($pool, ['project_id' => 7]));
    Refusal::assert(Refusal::CHECK, 'evidence_links_c58_company_when_company_scoped', fn () => linkEvidence($pool, ['company_id' => $own->id, 'stock_movement_id' => 7]));
    Refusal::assert(Refusal::CHECK, 'evidence_links_c58_company_when_company_scoped', fn () => linkEvidence($evidence, ['company_id' => null, 'project_id' => 7]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_links_c58_scope_foreign', fn () => linkEvidence($evidence, ['evidence_scope' => 'POOL', 'company_id' => null, 'stock_movement_id' => 7]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_links_c32_evidence_foreign', fn () => linkEvidence($evidence, ['company_id' => $other->id, 'project_id' => 7]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_links_c32_correction_case_foreign', fn () => linkEvidence($evidence, ['correction_case_id' => CorrectionCase::factory()->create(['company_id' => $other->id])->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'evidence_links_import_batch_foreign', fn () => linkEvidence($pool, ['import_batch_id' => 999_999]));
    Refusal::assert(Refusal::CHECK, 'evidence_links_one_target', fn () => linkEvidence($evidence, ['project_id' => 7, 'payment_id' => 8]));
    Refusal::assert(Refusal::CHECK, 'evidence_links_one_target', fn () => linkEvidence($evidence, []));
    Refusal::assert(Refusal::CHECK, 'evidence_links_role_code', fn () => linkEvidence($evidence, ['project_id' => 7, 'role' => 'ATTACHMENT']));

    expect(EvidenceLink::query()->where('evidence_document_id', $evidence->id)->count())->toBe(2)
        ->and(EvidenceLink::query()->where('evidence_document_id', $pool->id)->whereNotNull('company_id')->exists())->toBeFalse();
});

it('unlinks a link once, with its reason and actor, changing nothing else (§4.10, §19)', function () {
    $link = EvidenceLink::factory()->create();
    $row = fn () => DB::table('evidence_links')->where('id', $link->id);

    Refusal::assert(Refusal::CHECK, 'evidence_links_unlink_columns', fn () => $row()->update(['unlinked_at' => now()]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table evidence_links', fn () => $row()->update(['role' => 'SUPPORTING']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table evidence_links', fn () => $row()->delete());

    $row()->update(['unlinked_at' => now(), 'unlinked_by' => User::factory()->create()->id, 'unlink_reason' => 'Salah lampir']);

    Refusal::assert(Refusal::CHECK, 'evidence_links_unlink_once', fn () => $row()->update(['unlink_reason' => 'Alasan lain']));
    Refusal::assert(Refusal::CHECK, 'evidence_links_unlink_once', fn () => $row()->update(['unlinked_at' => null, 'unlinked_by' => null, 'unlink_reason' => null]));

    // Linked again, it is a new link.
    expect(EvidenceLink::factory()->create(['evidence_document_id' => $link->evidence_document_id, 'correction_case_id' => $link->correction_case_id])->exists)->toBeTrue();
});

it('stores each file once under its key, with its size, hash, sensitivity and purpose, as uploaded (§22; C-53)', function () {
    $file = FileObject::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'file_objects_storage_key_unique', fn () => FileObject::factory()->create(['storage_key' => $file->storage_key]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table file_objects', fn () => DB::table('file_objects')->where('id', $file->id)->update(['sha256' => str_repeat('0', 64)]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table file_objects', fn () => DB::table('file_objects')->where('id', $file->id)->delete());

    expect(fn () => $file->forceFill(['original_filename' => 'lain.pdf'])->save())->toThrow(LogicException::class, 'append-only')
        // The same content stored twice is two files, found by its hash (IX-02).
        ->and(FileObject::factory()->create(['sha256' => $file->sha256])->exists)->toBeTrue();
});

it('refuses a file of no size, a hash that is no SHA-256, or an unknown sensitivity or purpose (§22)', function (array $attributes, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, fn () => FileObject::factory()->create($attributes));
})->with([
    'no size' => [['size_bytes' => 0], 'file_objects_size_positive'],
    'an upper-case hash' => [['sha256' => str_repeat('A', 64)], 'file_objects_sha256_hex'],
    'a short hash' => [['sha256' => 'abc'], 'file_objects_sha256_hex'],
    'S5' => [['sensitivity' => 'S5'], 'file_objects_sensitivity_code'],
    'an unknown purpose' => [['purpose' => 'AVATAR'], 'file_objects_purpose_code'],
]);
