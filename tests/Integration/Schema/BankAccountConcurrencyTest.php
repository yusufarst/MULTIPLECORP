<?php

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\ORG\Models\CompanyBankAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Barrier;
use Tests\Support\Concurrency\Sessions;
use Tests\Support\DatabaseTruncation;
use Tests\Support\Refusal;

// TL-05 (TASK-006), BR-SN-02 under two connections (TEST_STRATEGY §6): a session changing a bank
// account's details waits for a session whose uncommitted fact references the account, and its trigger
// then sees that fact — so a referenced account's details never change, however the two interleave.

uses(DatabaseTruncation::class);

afterEach(function () {
    // The sessions commit, so the tests after these in this process start from empty tables.
    $this->truncateTablesForAllConnections();
});

it('refuses a detail change that meets a fact referencing the account at the same time (BR-SN-02)', function () {
    $account = CompanyBankAccount::factory()->create();
    $statement = EvidenceDocument::factory()->bankStatement()->create(['company_id' => $account->company_id]);
    [$accountId, $companyId, $statementId] = [$account->id, $account->company_id, $statement->id];

    $referrer = static function () use ($accountId, $companyId, $statementId): string {
        DB::beginTransaction();
        DB::table('bank_statement_lines')->insert([
            'company_id' => $companyId, 'company_bank_account_id' => $accountId, 'statement_evidence_id' => $statementId,
            'statement_evidence_type' => 'BANK_STATEMENT', 'business_date' => '2026-10-01', 'amount' => '1500000.00', 'line_reference' => 'TRX-CTH-RACE',
        ]);
        Barrier::await('the fact references the account');
        Sessions::awaitBlocked();
        DB::commit();

        return 'committed';
    };
    $editor = static function () use ($accountId): string {
        Barrier::await('the fact references the account');

        try {
            DB::table('company_bank_accounts')->where('id', $accountId)->update(['account_holder' => 'PT Nama Lain']);
        } catch (QueryException $exception) {
            return $exception->getCode().' '.$exception->getMessage();
        }

        return 'committed';
    };

    $outcomes = Sessions::run(['referrer' => $referrer, 'editor' => $editor]);

    expect($outcomes['referrer'])->toBe('committed')
        ->and($outcomes['editor'])->toStartWith(Refusal::CHECK)->toContain('company_bank_accounts_detail_immutable')
        ->and(DB::table('company_bank_accounts')->where('id', $accountId)->value('account_holder'))->toBe($account->account_holder);
});
