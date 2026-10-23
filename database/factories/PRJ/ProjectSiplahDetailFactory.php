<?php

namespace Database\Factories\PRJ;

use App\Modules\PRJ\Models\ProjectSiplahDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Invented SIPLAH metadata of a SIPLAH project, not yet locked.
 *
 * @extends Factory<ProjectSiplahDetail>
 */
final class ProjectSiplahDetailFactory extends Factory
{
    protected $model = ProjectSiplahDetail::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'project_id' => ProjectFactory::new()->state(['channel' => 'SIPLAH']),
            'external_reference' => "SIPLAH-CTH-{$number}",
            'program_code' => 'BOS',
            'siplah_value' => '15000000.00',
            'real_purchase_value' => '14000000.00',
            'fee_amount' => '300000.00',
        ];
    }
}
