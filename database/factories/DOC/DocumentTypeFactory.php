<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A catalogue row for the tests. The catalogue's own rows are not seeded yet — the codes of
 * `subject_kind` are not stated —, so the subject kind here is a placeholder.
 *
 * @extends Factory<DocumentType>
 */
final class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'DOC-01',
            'name' => 'Penawaran',
            'subject_kind' => 'CONTOH',
            'is_numbered' => true,
            'allowed_modes' => ['STANDARD'],
        ];
    }

    /** The type's row, created once for the test that needs it. */
    public static function ensure(string $code): string
    {
        if (DocumentType::query()->whereKey($code)->doesntExist()) {
            DocumentType::factory()->create([
                'code' => $code,
                'name' => "Dokumen {$code}",
                'allowed_modes' => $code === 'DOC-05' ? ['RECEIPT', 'PRE_PAYMENT'] : ['STANDARD'],
            ]);
        }

        return $code;
    }
}
