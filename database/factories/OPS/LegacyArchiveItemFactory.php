<?php

namespace Database\Factories\OPS;

use App\Modules\OPS\Models\LegacyArchiveItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Until ORG's companies exist (TASK-006), the company is a synthetic key.
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
            'company_id' => 1001,
            'archive_kind' => 'CONTOH',
            'legacy_reference' => 'ARSIP-'.++self::$sequence,
        ];
    }
}
