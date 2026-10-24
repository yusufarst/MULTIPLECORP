<?php

namespace Database\Factories\FUL;

use App\Modules\FUL\Models\DropshipConfirmation;
use App\Modules\ORG\Models\Company;
use Database\Factories\PRJ\ProjectFactory;
use Database\Factories\PUR\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented drop-ship confirmation of a project's purchase in one company, identified by an invented
 * shipment reference.
 *
 * @extends Factory<DropshipConfirmation>
 */
final class DropshipConfirmationFactory extends Factory
{
    protected $model = DropshipConfirmation::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => static fn (array $row): int => ProjectFactory::new()->createOne(['company_id' => $row['company_id']])->id,
            'purchase_id' => static fn (array $row): int => PurchaseFactory::new()->createOne(['company_id' => $row['company_id'], 'project_id' => $row['project_id']])->id,
            'supplier_shipment_reference' => 'SJ-PMS-'.++self::$sequence,
            'business_date' => '2026-10-02',
        ];
    }

    /**
     * The company, project and purchase a line of the confirmation keys to.
     *
     * @return array{company_id: int, project_id: int, purchase_id: int}
     */
    public static function partsOf(mixed $confirmation): array
    {
        $row = DropshipConfirmation::query()->whereKey($confirmation)->firstOrFail(['company_id', 'project_id', 'purchase_id']);

        return ['company_id' => $row->company_id, 'project_id' => $row->project_id, 'purchase_id' => $row->purchase_id];
    }
}
