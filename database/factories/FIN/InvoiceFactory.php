<?php

namespace Database\Factories\FIN;

use App\Modules\FIN\Models\Invoice;
use App\Modules\ORG\Models\Company;
use App\Modules\PRJ\Models\Project;
use App\Modules\PTY\Models\ClientOrganization;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented DRAFT invoice of a project, to the project's client.
 *
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_organization_id' => ClientOrganization::factory(),
            'project_id' => static fn (array $row): int => ProjectFactory::new()->createOne(['company_id' => $row['company_id'], 'client_organization_id' => $row['client_organization_id']])->id,
        ];
    }

    /** An invoice of the company to the client, on a new project of theirs. */
    public static function of(mixed $company, mixed $client): Invoice
    {
        return self::new()->createOne(['company_id' => $company, 'client_organization_id' => $client]);
    }

    /** The project of an invented invoice. */
    public static function projectOf(mixed $invoice): Project
    {
        return Project::query()->whereKey(Invoice::query()->whereKey($invoice)->firstOrFail()->project_id)->firstOrFail();
    }
}
