<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\LegacyArchiveItem;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An identified archive item of an invented company, without its file.
 *
 * @extends Factory<LegacyArchiveItem>
 */
final class LegacyArchiveItemFactory extends Factory
{
    protected $model = LegacyArchiveItem::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'archive_kind' => 'CONTOH',
            'legacy_reference' => 'ARSIP-'.++self::$sequence,
        ];
    }
}
