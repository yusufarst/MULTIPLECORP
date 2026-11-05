<?php

namespace App\Modules\ImportOrchestration\Kinds;

use App\Modules\ImportOrchestration\Actions\CommitSeedRow;
use App\Modules\ImportOrchestration\Data\Cells;
use App\Modules\ImportOrchestration\Data\ImportRowData;
use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Services\NumberingState;
use App\Support\Command\Outcome;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The numbering seeds a batch carries (HO-32; NM-03; IMP-07): a source row naming a numbered type, a period and the
 * last legacy number of that period — 0 or empty where there is none — is a seed row of its company, `NUMBER_SEED:{type}`
 * with the identity `{company}|{period}` that ORG's numbering state reads; it commits before the batch's other rows,
 * and the commit request is refused while a period the cutoff falls into lacks one.
 */
final class SeedRows implements ImportKind
{
    public const PREFIX = 'NUMBER_SEED:';

    public function __construct(
        private readonly CommitSeedRow $commit,
        private readonly NumberingState $state,
    ) {}

    public static function isSeed(Cells $cells): bool
    {
        return ($cells->cells['nomor_jenis'] ?? '') !== '';
    }

    public function code(): string
    {
        return 'NUMBER_SEED';
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
        $type = SequenceType::tryFrom(strtoupper($head->text('nomor_jenis', true, 20) ?? '')) ?? $head->fail('nomor_jenis', 'choice');
        $period = $head->text('nomor_periode', true, 7);

        if ($period !== null && ! NumberingPeriod::isKey($period)) {
            $head->fail('nomor_periode', 'format');
        }

        $last = $head->integer('nomor_terakhir') ?? 0;

        return ['type' => $type?->value, 'period' => $period, 'first' => $last + 1];
    }

    public function steps(ImportRowData $row): array
    {
        return [fn (string $commandId, int $actor): Outcome => $this->commit->run($commandId, $actor, (int) $row->companyId, $row->id,
            SequenceType::from($row->text('type')), $row->text('period'), (int) $row->text('first'), $row->cutoff)];
    }

    public function totals(array $payload): array
    {
        return [];
    }

    /**
     * The first seed a commit request lacks (NM-03; NX-13): for each company of the scope and each numbered type, each
     * period the cutoff falls into under every granularity that governs it needs a seed row in the batch, or one an
     * earlier batch committed, unless the type's seed boundary is later than the cutoff; null when none is missing.
     *
     * @param  list<int>  $companies
     * @return array{type: string, period: string, company: int}|null
     */
    public function missing(int $batchId, array $companies, string $cutoff): ?array
    {
        foreach ($companies as $company) {
            foreach (SequenceType::cases() as $type) {
                $boundary = $this->state->boundary($company, $type);

                if ($boundary !== null && $boundary > $cutoff) {
                    continue;
                }

                foreach (NumberingState::periodsOf($this->state->schemes($company, $type), $cutoff) as $period) {
                    $held = DB::table('import_rows')->where('import_kind', $type->seedImportKind())->where('import_identity', "{$company}|{$period->key}")
                        ->where(fn (Builder $rows) => $rows->where('import_batch_id', $batchId)->whereIn('validation_state', ['VALID', 'COMMITTED', 'SKIPPED'])
                            ->orWhere('validation_state', 'COMMITTED'))
                        ->exists();

                    if (! $held) {
                        return ['type' => $type->value, 'period' => $period->key, 'company' => $company];
                    }
                }
            }
        }

        return null;
    }
}
