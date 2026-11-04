<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\FIN\Actions\RecordOpeningCredit;
use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Services\RowRefused;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;

/**
 * Opening customer credit (IMP-01; L-50): a client's credit in a company with its signed evidence — an evidence record
 * of that company named by its public id — through FIN's RecordOpeningCredit, never Cash-In; its amount added to its
 * company's credits total, which the signed opening evidence reconciles.
 */
final class OpeningCreditKind implements ImportKind
{
    public const CODE = 'OPENING_CREDIT';

    public function __construct(private readonly RecordOpeningCredit $record) {}

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
        $evidence = $head->text('bukti', true, 40);

        if ($evidence !== null && preg_match('/^[0-9a-f-]{36}$/i', $evidence) !== 1) {
            $head->fail('bukti', 'format');
        }

        return ['client' => $head->text('klien', true, 100), 'amount' => $head->decimal('nilai', 2, true, true, false), 'evidence' => $evidence === null ? null : strtolower($evidence)];
    }

    public function steps(ImportRowData $row): array
    {
        return [function (string $commandId, int $actor) use ($row): Outcome {
            $evidence = DB::table('evidence_documents')->where('public_id', $row->text('evidence'))->where('company_id', $row->companyId)->value('id');

            return $this->record->run($commandId, $actor, (int) $row->companyId, [
                'import_row' => $row->id, 'client' => References::client($row->text('client'), 'klien'), 'amount' => $row->text('amount'),
                'evidence' => is_numeric($evidence) ? (int) $evidence : throw new RowRefused('bukti', 'reference'), 'date' => $row->cutoff,
            ]);
        }];
    }

    public function totals(array $payload): array
    {
        return ['credits' => OpeningStockKind::scalar($payload['amount'] ?? null)];
    }
}
