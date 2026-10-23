<?php

namespace Database\Factories\PRJ;

use App\Modules\IAM\Models\User;
use App\Modules\PRJ\Models\LineScopeCancellation;
use App\Modules\PRJ\Models\ProjectLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented cancellation of two pieces of a line's remaining scope.
 *
 * @extends Factory<LineScopeCancellation>
 */
final class LineScopeCancellationFactory extends Factory
{
    protected $model = LineScopeCancellation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_line_id' => ProjectLine::factory(),
            'company_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['company_id'],
            'product_id' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['product_id'],
            'quantity_scale' => static fn (array $row): int => ProjectLineFactory::keyOf($row['project_line_id'])['quantity_scale'],
            'qty_base' => '2.000',
            'reason' => 'Klien membatalkan sisa pesanan',
            'decided_by' => User::factory(),
            'business_date' => '2026-10-01',
        ];
    }
}
