<?php

namespace Database\Factories\FUL;

use App\Modules\FUL\Models\ServiceHandover;
use App\Modules\ORG\Models\Company;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented service handover to a project's client, received by an invented recipient.
 *
 * @extends Factory<ServiceHandover>
 */
final class ServiceHandoverFactory extends Factory
{
    protected $model = ServiceHandover::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => static fn (array $row): int => ProjectFactory::new()->createOne(['company_id' => $row['company_id']])->id,
            'business_date' => '2026-10-03',
            'recipient_name' => 'Bapak Contoh Penerima',
        ];
    }
}
