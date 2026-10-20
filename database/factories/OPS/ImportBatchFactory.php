<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A prepared batch over an invented source. Until DOC's file objects and ORG's companies exist
 * (TASK-006), the file and the company are synthetic keys.
 *
 * @extends Factory<ImportBatch>
 */
final class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'import_kind' => 'CONTOH',
            'source_file_id' => 2000 + $number,
            'source_sha256' => hash('sha256', "sumber-contoh-{$number}"),
            'cutoff_date' => '2026-09-30',
            'company_scope' => [1001],
        ];
    }
}
