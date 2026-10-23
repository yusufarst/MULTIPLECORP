<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
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
            'uploaded_by' => User::factory(),
        ];
    }

    /** The file is an upload of the evidence's company, or of no company for pool evidence (C-32; DIR-089). */
    public function configure(): static
    {
        return $this->afterMaking(function (EvidenceDocumentVersion $version): void {
            if ($version->getAttribute('file_object_id') === null) {
                $evidence = EvidenceDocument::query()->findOrFail($version->evidence_document_id);
                $version->file_object_id = FileObjectFactory::new()->createOne(['company_id' => $evidence->company_id])->id;
            }
        });
    }
}
