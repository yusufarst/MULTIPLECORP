<?php

namespace Database\Factories\INV;

use App\Modules\IAM\Models\User;
use App\Modules\INV\Models\UnattributedLossCase;
use App\Modules\INV\Models\UnattributedLossResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented Owner attribution of a pending case, posted by its pool resolution movement.
 *
 * @extends Factory<UnattributedLossResolution>
 */
final class UnattributedLossResolutionFactory extends Factory
{
    protected $model = UnattributedLossResolution::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unattributed_loss_case_id' => UnattributedLossCase::factory(),
            'resolution_kind' => 'OWNER_ATTRIBUTION',
            'stock_movement_id' => static fn (): int => StockMovementFactory::post('UNATTRIBUTED_RESOLUTION'),
            'decided_by' => User::factory(),
            'business_date' => '2026-10-02',
            'reason' => 'Owner menetapkan perusahaan penanggung',
        ];
    }
}
