<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ImportOrchestration\Services\RowRefused;
use App\Modules\OPS\Actions\RecordLegacyArchiveItem;
use App\Support\Command\Outcome;
use Illuminate\Support\Facades\DB;

/**
 * A useful current-year document kept as an identified archive item (IMP-01; BR-XD-06): its company, kind, legacy
 * reference and description, and the ARCHIVE file the batch's preparer uploaded for it, named by its public id; history
 * only, never a current effect, through OPS's RecordLegacyArchiveItem.
 */
final class LegacyDocumentKind implements ImportKind
{
    public const CODE = 'LEGACY_DOCUMENT';

    public function __construct(private readonly RecordLegacyArchiveItem $record) {}

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
        $file = $head->text('berkas', false, 40);

        if ($file !== null && preg_match('/^[0-9a-f-]{36}$/i', $file) !== 1) {
            $head->fail('berkas', 'format');
        }

        return [
            'kind' => $head->text('jenis', true, 60), 'reference' => $head->text('nomor', true, 100),
            'description' => $head->text('keterangan', false, 500), 'file' => $file === null ? null : strtolower($file),
        ];
    }

    public function steps(ImportRowData $row): array
    {
        return [function (string $commandId, int $actor) use ($row): Outcome {
            $file = null;

            if ($row->nullable('file') !== null) {
                $preparer = DB::table('import_batches')->where('id', $row->batchId)->value('prepared_by');
                $found = DB::table('file_objects')->where('public_id', $row->text('file'))->where('purpose', 'ARCHIVE')->where('company_id', $row->companyId)
                    ->where('uploaded_by', $preparer)->value('id');
                $file = is_numeric($found) ? (int) $found : throw new RowRefused('berkas', 'reference');
            }

            return $this->record->run($commandId, $actor, (int) $row->companyId, [
                'batch' => $row->batchId, 'kind' => $row->text('kind'), 'reference' => $row->text('reference'), 'description' => $row->nullable('description'), 'file' => $file,
            ]);
        }];
    }

    public function totals(array $payload): array
    {
        return [];
    }
}
