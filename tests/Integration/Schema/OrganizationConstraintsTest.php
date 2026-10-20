<?php

use App\Modules\DOC\Models\BankStatementLine;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use App\Modules\OPS\Models\ImportRow;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\CompanyBankAccount;
use App\Modules\ORG\Models\CompanyTaxProfile;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\ORG\Models\RetiredNumber;
use App\Modules\ORG\Models\TaxRateVersion;
use App\Modules\ORG\Models\TaxTreatmentRule;
use App\Modules\ORG\Models\TaxType;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\Support\Refusal;

// TL-05 (TASK-006), the ORG rows of DATABASE §18 on these tables, each violated and refused where its row
// says: the number counters never lowered and the retired numbers never reissued (C-10), the effective
// tax configuration never overlapping (C-52), the company-scoped keys of seeds and identity assets
// (C-32), and a referenced bank account's details kept (BR-SN-02).

uses(RefreshDatabase::class);

/** @param  array<string, mixed>  $columns */
function insertCounter(Company $company, array $columns = []): void
{
    DB::table('number_sequences')->insert(['company_id' => $company->id, 'sequence_type' => 'DOC-06', 'period_key' => '2026', ...$columns]);
}

it('draws a number by one statement and never lowers the counter or its seed, whose key the runtime cannot change (C-10; NM-02, NM-03)', function () {
    $company = Company::factory()->create();
    insertCounter($company);
    $counter = fn () => DB::table('number_sequences')->where(['company_id' => $company->id, 'sequence_type' => 'DOC-06', 'period_key' => '2026']);

    $drawn = DB::scalar("update number_sequences set next_value = next_value + 1
        where company_id = ? and sequence_type = 'DOC-06' and period_key = '2026' returning next_value - 1", [$company->id]);

    expect($drawn)->toBe(1)
        ->and($counter()->value('next_value'))->toBe(2);

    // The Owner's raise after a restore only raises, and moves no seed (NM-03, NM-04).
    $counter()->update(['next_value' => 10]);

    Refusal::assert(Refusal::CHECK, 'number_sequences_c10_never_lowered', fn () => $counter()->update(['next_value' => 9]));

    // A seed that meets the counter raises it and moves its seed value with it (NM-03).
    $counter()->update(['next_value' => 42, 'seed_value' => 42]);

    Refusal::assert(Refusal::CHECK, 'number_sequences_c10_never_lowered', fn () => $counter()->update(['seed_value' => 41]));
    Refusal::assert(Refusal::CHECK, 'number_sequences_c10_never_lowered', fn () => $counter()->update(['seed_value' => null]));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table number_sequences', fn () => $counter()->update(['period_key' => '2027']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table number_sequences', fn () => $counter()->delete());
    Refusal::assert(Refusal::UNIQUE, 'number_sequences_pkey', fn () => insertCounter($company));

    expect((array) $counter()->first(['next_value', 'seed_value']))->toBe(['next_value' => 42, 'seed_value' => 42]);
});

it('refuses a counter outside a year or month period, below 1, or a migration seed without its first number (C-10; NM-03)', function (array $columns, string $constraint) {
    $company = Company::factory()->create();

    if (array_key_exists('seeded_by_import_row_id', $columns)) {
        $columns['seeded_by_import_row_id'] = ImportRow::factory()->create(['company_id' => $company->id])->id;
    }

    Refusal::assert(Refusal::CHECK, $constraint, fn () => insertCounter($company, $columns));
})->with([
    'a thirteenth month' => [['period_key' => '2026-13'], 'number_sequences_period_key_format'],
    'a day' => [['period_key' => '2026-10-01'], 'number_sequences_period_key_format'],
    'zero' => [['next_value' => 0], 'number_sequences_next_value_positive'],
    'a seed without its first number' => [['seeded_by_import_row_id' => null], 'number_sequences_seed_states_first_number'],
    'an unknown sequence type' => [['sequence_type' => 'DOC-15'], 'number_sequences_sequence_type_code'],
]);

it('keys a migration seed to an import row of its own company (C-32; AX-28)', function () {
    [$own, $other] = [Company::factory()->create(), Company::factory()->create()];
    $row = ImportRow::factory()->create(['company_id' => $own->id]);

    insertCounter($own, ['seed_value' => 42, 'next_value' => 42, 'seeded_by_import_row_id' => $row->id]);

    Refusal::assert(Refusal::FOREIGN_KEY, 'number_sequences_c32_seed_import_row_foreign', fn () => insertCounter($other, ['seed_value' => 42, 'next_value' => 42, 'seeded_by_import_row_id' => $row->id]));

    // A seed that meets a counter created on first use keys it to its own company's row too (NM-03).
    insertCounter($other);
    $firstUse = fn () => DB::table('number_sequences')->where(['company_id' => $other->id, 'sequence_type' => 'DOC-06', 'period_key' => '2026']);

    Refusal::assert(Refusal::FOREIGN_KEY, 'number_sequences_c32_seed_import_row_foreign', fn () => $firstUse()->update(['seed_value' => 42, 'next_value' => 42, 'seeded_by_import_row_id' => $row->id]));

    $firstUse()->update(['seed_value' => 42, 'next_value' => 42, 'seeded_by_import_row_id' => ImportRow::factory()->create(['company_id' => $other->id])->id]);

    expect($firstUse()->value('seed_value'))->toBe(42);
});

it('retires a discarded project\'s number once and never reissues or removes it (C-10; L-37)', function () {
    $retired = RetiredNumber::factory()->create();
    $same = ['company_id' => $retired->company_id, 'sequence_type' => 'PROJECT', 'period_key' => '2026'];

    Refusal::assert(Refusal::UNIQUE, 'retired_numbers_c10_number', fn () => RetiredNumber::factory()->create([...$same, 'sequence_no' => $retired->sequence_no]));
    Refusal::assert(Refusal::UNIQUE, 'retired_numbers_c10_formatted_number', fn () => RetiredNumber::factory()->create([...$same, 'formatted_number' => $retired->formatted_number]));
    Refusal::assert(Refusal::CHECK, 'retired_numbers_retired_kind_code', fn () => RetiredNumber::factory()->create(['retired_kind' => 'VOIDED_DOCUMENT']));
    Refusal::assert(Refusal::PRIVILEGE, 'permission denied for table retired_numbers', fn () => DB::table('retired_numbers')->where('id', $retired->id)->delete());

    expect(RetiredNumber::factory()->create(['sequence_no' => $retired->sequence_no, 'formatted_number' => $retired->formatted_number])->exists)->toBeTrue();
});

it('never overlaps a tax type\'s rates or a company\'s profiles, and refuses an empty range (C-52)', function () {
    $type = TaxType::factory()->create();
    TaxRateVersion::factory()->for($type)->create(['effective' => '[2026-01-01,2027-01-01)']);
    TaxRateVersion::factory()->for($type)->create(['effective' => '[2027-01-01,)']);
    $company = Company::factory()->create();
    CompanyTaxProfile::factory()->for($company)->create(['effective' => '[2026-01-01,)']);

    Refusal::assert(Refusal::EXCLUSION, 'tax_rate_versions_c52_no_overlap', fn () => TaxRateVersion::factory()->for($type)->create(['effective' => '[2026-06-01,2026-07-01)']));
    Refusal::assert(Refusal::CHECK, 'tax_rate_versions_effective_not_empty', fn () => TaxRateVersion::factory()->for($type)->create(['effective' => 'empty']));
    Refusal::assert(Refusal::EXCLUSION, 'company_tax_profiles_c52_no_overlap', fn () => CompanyTaxProfile::factory()->for($company)->create(['effective' => '[2026-03-01,2026-04-01)']));

    expect(TaxRateVersion::factory()->create(['effective' => '[2026-06-01,2026-07-01)'])->exists)->toBeTrue()
        ->and(CompanyTaxProfile::factory()->create(['effective' => '[2026-03-01,2026-04-01)'])->exists)->toBeTrue();
});

it('never overlaps two global treatment rules, nor two of one company, a company\'s rule overriding the global one (C-52)', function () {
    $type = TaxType::factory()->create();
    $company = Company::factory()->create();
    $rule = ['tax_type_id' => $type->id, 'transaction_context' => 'SALES_INVOICE'];

    TaxTreatmentRule::factory()->create([...$rule, 'effective' => '[2026-01-01,)']);
    TaxTreatmentRule::factory()->create([...$rule, 'company_id' => $company->id, 'effective' => '[2026-06-01,)']);
    TaxTreatmentRule::factory()->create([...$rule, 'transaction_context' => 'PAYMENT_WITHHELD', 'treatment' => 'SETTLEMENT_NO_EXPENSE', 'effective' => '[2026-01-01,)']);

    Refusal::assert(Refusal::EXCLUSION, 'tax_treatment_rules_c52_no_overlap', fn () => TaxTreatmentRule::factory()->create([...$rule, 'effective' => '[2026-09-01,)']));
    Refusal::assert(Refusal::EXCLUSION, 'tax_treatment_rules_c52_no_overlap', fn () => TaxTreatmentRule::factory()->create([...$rule, 'company_id' => $company->id, 'effective' => '[2026-09-01,)']));

    expect(TaxTreatmentRule::query()->where('tax_type_id', $type->id)->count())->toBe(3);
});

it('refuses a code outside the numbering and tax configuration\'s lists', function (Closure $write, string $constraint) {
    Refusal::assert(Refusal::CHECK, $constraint, $write);
})->with([
    'a sequence type' => [fn () => NumberingScheme::factory()->create(['sequence_type' => 'DOC-15']), 'numbering_schemes_sequence_type_code'],
    'a period granularity' => [fn () => NumberingScheme::factory()->create(['period_granularity' => 'WEEK']), 'numbering_schemes_period_granularity_code'],
    'a revision format' => [fn () => NumberingScheme::factory()->create(['revision_format' => 'LETTER_SUFFIX']), 'numbering_schemes_revision_format_code'],
    'a tax category' => [fn () => TaxType::factory()->create(['category' => 'EXCISE']), 'tax_types_category_code'],
    'a rounding mode' => [fn () => TaxRateVersion::factory()->create(['rounding_mode' => 'UP']), 'tax_rate_versions_rounding_mode_code'],
    'a zero base denominator' => [fn () => TaxRateVersion::factory()->create(['base_denominator' => 0]), 'tax_rate_versions_base_denominator_positive'],
    'a transaction context' => [fn () => TaxTreatmentRule::factory()->create(['transaction_context' => 'REFUND']), 'tax_treatment_rules_context_code'],
    'a treatment' => [fn () => TaxTreatmentRule::factory()->create(['treatment' => 'OUTPUT_TO_REVENUE']), 'tax_treatment_rules_treatment_code'],
]);

it('keeps one company per code, one scheme per company, type and date, and one account per bank number', function () {
    $scheme = NumberingScheme::factory()->create();
    $account = CompanyBankAccount::factory()->create();

    Refusal::assert(Refusal::UNIQUE, 'companies_code_unique', fn () => Company::factory()->create(['code' => $scheme->company->code]));
    Refusal::assert(Refusal::UNIQUE, 'numbering_schemes_effective_unique', fn () => NumberingScheme::factory()->for($scheme->company)->create());
    Refusal::assert(Refusal::UNIQUE, 'company_bank_accounts_account_unique', fn () => CompanyBankAccount::factory()->for($account->company)->create(['account_number' => $account->account_number]));

    expect(NumberingScheme::factory()->for($scheme->company)->create(['effective_from' => '2027-01-01'])->exists)->toBeTrue();
});

it('keeps a bank account\'s details once any fact references it, its deactivation still allowed (BR-SN-02)', function (Closure $reference) {
    $account = CompanyBankAccount::factory()->create();
    $account->forceFill(['branch' => 'Cabang Contoh'])->save();

    $reference($account);

    Refusal::assert(Refusal::CHECK, 'company_bank_accounts_detail_immutable', fn () => DB::table('company_bank_accounts')->where('id', $account->id)->update(['account_number' => '1234567890']));
    Refusal::assert(Refusal::CHECK, 'company_bank_accounts_detail_immutable', fn () => DB::table('company_bank_accounts')->where('id', $account->id)->update(['branch' => null]));

    $account->refresh()->forceFill(['is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => User::factory()->create()->id, 'deactivated_reason' => 'Rekening ditutup'])->save();

    expect($account->refresh()->is_active)->toBeFalse()
        ->and($account->branch)->toBe('Cabang Contoh');
})->with([
    'a document version' => [fn (CompanyBankAccount $account) => DocumentVersion::factory()->create([
        'document_id' => Document::factory()->for($account->company)->create()->id,
        'company_bank_account_id' => $account->id,
    ])],
    'a statement line' => [fn (CompanyBankAccount $account) => BankStatementLine::factory()->create([
        'company_id' => $account->company_id,
        'company_bank_account_id' => $account->id,
    ])],
]);

it('keys a company\'s identity assets to its own files (C-32; §14)', function () {
    $company = Company::factory()->create();
    $own = FileObject::factory()->create(['company_id' => $company->id, 'purpose' => 'IDENTITY_ASSET']);
    $foreign = FileObject::factory()->create(['purpose' => 'IDENTITY_ASSET']);

    $company->forceFill(['logo_file_id' => $own->id])->save();

    Refusal::assert(Refusal::FOREIGN_KEY, 'companies_c32_logo_file_foreign', fn () => DB::table('companies')->where('id', $company->id)->update(['logo_file_id' => $foreign->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'companies_c32_stamp_file_foreign', fn () => DB::table('companies')->where('id', $company->id)->update(['stamp_file_id' => $foreign->id]));
    Refusal::assert(Refusal::FOREIGN_KEY, 'companies_c32_signature_file_foreign', fn () => DB::table('companies')->where('id', $company->id)->update(['signature_file_id' => $foreign->id]));

    expect($company->refresh()->logo_file_id)->toBe($own->id);
});
