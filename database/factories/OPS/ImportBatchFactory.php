<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\ImportBatch;
use Database\Factories\DOC\FileObjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A prepared batch over an invented source file. Its company scope is a list of invented keys, an array
 * no foreign key checks (DATABASE §24).
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
            'source_file_id' => FileObjectFactory::new()->importSource(),
            'source_sha256' => hash('sha256', "sumber-contoh-{$number}"),
            'cutoff_date' => '2026-09-30',
            'company_scope' => [1001],
        ];
    }
}
