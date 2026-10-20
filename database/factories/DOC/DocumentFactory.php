<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\Document;
use App\Modules\ORG\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A quotation chain (DOC-01) on a project. Until PRJ's projects exist (TASK-007), the project is a
 * synthetic key, as every subject of a later module is.
 *
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    protected $model = Document::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_type_code' => static fn (): string => DocumentTypeFactory::ensure('DOC-01'),
            'project_id' => 3000 + ++self::$sequence,
        ];
    }

    /** A receipt-mode Kuitansi (DOC-05) on a payment and its invoice, both synthetic keys until TASK-009. */
    public function receipt(): self
    {
        $number = ++self::$sequence;

        return $this->state([
            'document_type_code' => static fn (): string => DocumentTypeFactory::ensure('DOC-05'),
            'mode' => 'RECEIPT',
            'project_id' => null,
            'payment_id' => 5000 + $number,
            'invoice_id' => 4000 + $number,
        ]);
    }
}
