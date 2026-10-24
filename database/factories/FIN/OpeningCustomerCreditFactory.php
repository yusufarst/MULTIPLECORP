<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\OpeningCustomerCredit;
use App\Modules\ORG\Models\Company;
use App\Modules\PTY\Models\ClientOrganization;
use Database\Factories\OPS\ImportRowFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented Rp750.000 credit of a client carried over at cutover, from an invented import row, signed
 * off by an invented proof of its company.
 *
 * @extends Factory<OpeningCustomerCredit>
 */
final class OpeningCustomerCreditFactory extends Factory
{
    protected $model = OpeningCustomerCredit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_organization_id' => ClientOrganization::factory(),
            'amount' => '750000.00',
            'import_row_id' => static fn (array $row): int => ImportRowFactory::new()->createOne(['company_id' => $row['company_id']])->id,
            'signed_evidence_id' => static fn (array $row): int => DeductionSettlementFactory::evidence($row['company_id'], 'OPENING_SIGNOFF')->id,
            'business_date' => '2026-09-30',
        ];
    }
}
