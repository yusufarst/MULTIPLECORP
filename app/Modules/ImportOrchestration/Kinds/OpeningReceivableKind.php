<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\FIN\Actions\RecordOpeningReceivable;
use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Support\Command\Outcome;
use App\Support\Money;

/**
 * An open receivable (IMP-01; BR-FIN-14): one legacy invoice's remaining value — sales and tax — for a client
 * committed earlier, optionally an open project committed earlier, with its billed date and its due date, or none for
 * the *migrasi* bucket; through FIN's RecordOpeningReceivable; its remainder added to its company's receivables total.
 */
final class OpeningReceivableKind implements ImportKind
{
    public const CODE = 'OPENING_RECEIVABLE';

    public function __construct(private readonly RecordOpeningReceivable $record) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function grouped(): bool
    {
        return false;
    }

    public function companyBound(): bool
    {
        return true;
    }

    public function normalize(Cells $head, array $lines): array
    {
        $billed = $head->date('tanggal_tagih', true);
        $due = $head->date('jatuh_tempo');

        if ($billed !== null && $due !== null && $due < $billed) {
            $head->fail('jatuh_tempo', 'before');
        }

        return [
            'client' => $head->text('klien', true, 100), 'project' => $head->text('proyek', false, 100), 'reference' => $head->text('nomor_faktur', true, 100),
            'billed_at' => $billed, 'due_date' => $due, 'sales' => $head->decimal('nilai', 2, true, true), 'tax' => $head->decimal('ppn', 2, false, true) ?? '0.00',
        ];
    }

    public function steps(ImportRowData $row): array
    {
        return [fn (string $commandId, int $actor): Outcome => $this->record->run($commandId, $actor, (int) $row->companyId, [
            'import_row' => $row->id, 'client' => References::client($row->text('client'), 'klien'),
            'project' => $row->nullable('project') === null ? null : References::imported(OpenProjectKind::CODE, $row->text('project'), 'proyek', $row->companyId),
            'reference' => $row->text('reference'), 'billed_at' => $row->text('billed_at'), 'due_date' => $row->nullable('due_date'),
            'sales' => $row->text('sales'), 'tax' => $row->text('tax'), 'date' => $row->cutoff,
        ])];
    }

    public function totals(array $payload): array
    {
        return ['receivables' => Money::of(OpeningStockKind::scalar($payload['sales'] ?? null))->plus(Money::of(OpeningStockKind::scalar($payload['tax'] ?? null)))->toString()];
    }
}
