<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\ImportBatch;
use App\Modules\OPS\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A validated row with an invented legacy identity.
 *
 * @extends Factory<ImportRow>
 */
final class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => $number,
            'import_kind' => 'CONTOH',
            'import_identity' => "LEGACY-{$number}",
            'validation_state' => 'VALID',
        ];
    }
}
