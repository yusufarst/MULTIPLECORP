<?php

namespace Database\Factories\DOC;

use App\Modules\DOC\Models\Document;
use App\Modules\ORG\Models\Company;
use Database\Factories\PRJ\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A quotation chain (DOC-01) on a project of the document's company (C-32). The subjects of the modules
 * after TASK-007 are synthetic keys until their Tasks.
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
            'project_id' => static fn (array $row): int => ProjectFactory::new()->createOne(['company_id' => $row['company_id']])->id,
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
