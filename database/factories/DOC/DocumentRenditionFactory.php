<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\DocumentRendition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The first, still owed attempt at rendering an issued version.
 *
 * @extends Factory<DocumentRendition>
 */
final class DocumentRenditionFactory extends Factory
{
    protected $model = DocumentRendition::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_version_id' => DocumentVersionFactory::new()->issued(),
            'attempt_no' => 1,
        ];
    }
}
