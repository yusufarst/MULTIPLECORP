<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Models\FileObject;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The first version of an invented evidence over an invented file.
 *
 * @extends Factory<EvidenceDocumentVersion>
 */
final class EvidenceDocumentVersionFactory extends Factory
{
    protected $model = EvidenceDocumentVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'evidence_document_id' => EvidenceDocument::factory(),
            'version_no' => 1,
            'file_object_id' => FileObject::factory(),
            'uploaded_by' => User::factory(),
        ];
    }
}
