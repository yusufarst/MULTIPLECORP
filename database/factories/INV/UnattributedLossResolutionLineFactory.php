<?php

namespace Database\Factories\INV;

use App\Modules\INV\Models\UnattributedLossCase;
use App\Modules\INV\Models\UnattributedLossResolution;
use App\Modules\INV\Models\UnattributedLossResolutionLine;
use Database\Factories\ORG\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented assignment of one piece of a resolution's case to a candidate company, its exposure row
 * written as the recognition writes it, at company level.
 *
 * @extends Factory<UnattributedLossResolutionLine>
 */
final class UnattributedLossResolutionLineFactory extends Factory
{
    protected $model = UnattributedLossResolutionLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unattributed_loss_resolution_id' => UnattributedLossResolution::factory(),
            'unattributed_loss_case_id' => static fn (array $row): int => UnattributedLossResolution::query()->whereKey($row['unattributed_loss_resolution_id'])->firstOrFail()->unattributed_loss_case_id,
            'product_id' => static fn (array $row): int => UnattributedLossCase::query()->whereKey($row['unattributed_loss_case_id'])->firstOrFail()->product_id,
            'quantity_scale' => static fn (array $row): int => UnattributedLossCase::query()->whereKey($row['unattributed_loss_case_id'])->firstOrFail()->quantity_scale,
            'assigned_company_id' => static fn (array $row): int => UnattributedLossCaseFactory::expose($row['unattributed_loss_case_id'], CompanyFactory::new()->createOne()->id),
            'assigned_qty_base' => '1.000',
        ];
    }
}
