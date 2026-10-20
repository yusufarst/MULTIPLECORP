<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\IAM\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A draft version of an invented document, its company and type denormalized from the document.
 *
 * @extends Factory<DocumentVersion>
 */
final class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version_no' => 1,
        ];
    }

    /** An issued version with its number, payload and issue data (SF-ISSUE). */
    public function issued(): self
    {
        $number = ++self::$sequence;
        $payload = ['judul' => "Dokumen contoh {$number}"];

        return $this->state([
            'state' => 'ISSUED',
            'business_date' => '2026-10-01',
            'period_key' => '2026',
            'sequence_no' => $number,
            'formatted_number' => sprintf('CTH/DOK/2026/%04d', $number),
            'payload' => $payload,
            'payload_sha256' => hash('sha256', (string) json_encode($payload)),
            'issued_by' => User::factory(),
            'issue_command_id' => (string) Str::uuid7(),
        ]);
    }

    /** The company and the type are the document's own (DATABASE §4.10). */
    public function configure(): static
    {
        return $this->afterMaking(function (DocumentVersion $version): void {
            if ($version->getAttribute('company_id') === null) {
                $document = Document::query()->findOrFail($version->document_id);
                $version->company_id = $document->company_id;
                $version->document_type_code = $document->document_type_code;
            }
        });
    }
}
