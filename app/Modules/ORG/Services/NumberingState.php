<?php

namespace App\Modules\ORG\Services;

use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\NumberingScheme;
use App\Support\BusinessDate;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\Refused;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What NM-03 reads for a company and a numbered type (CONCURRENCY_IDEMPOTENCY §14; DATABASE §4.2): its schemes and
 * the periods they govern, its counters, its seed boundary — the latest of the cutoff dates of the batches holding a
 * COMMITTED seed row of the type and the day of the start, read from typed columns alone, never the counters —,
 * go-live, which periods are live, and the import batches still holding VALID seed rows of the type. A seed row of
 * the opening import carries the type's registered `import_kind` and, as its `import_identity`, the company and the
 * period it seeds (`{company id}|{period}`; DIR-098 reading 5 — TASK-057 writes them so).
 */
final class NumberingState
{
    public const START_ACTION = 'ORG.NumberingStarted.';

    public function __construct(private readonly NumberingGoLive $goLive) {}

    /**
     * The numbering command's date (IP-07; NM-03): the day of recording, read from the command's own clock once its
     * locks are held — never a date the request chose. SF-DATE refuses a future date (C-50) and a stale prefilled one
     * (ST-08); any other day than the recording day is a CONFLICT carrying it (ST-08).
     */
    public static function recordingDay(CommandContext $context, string $businessDate, bool $prefilled): string
    {
        BusinessDateRule::check($businessDate, $prefilled);
        $day = BusinessDate::of($context->now());

        if ($businessDate !== $day) {
            throw Refused::conflict('ST-08', ['today' => $day]);
        }

        return $day;
    }

    /** The go-live day, `Y-m-d`, or null while it is not recorded (DIR-098 reading 2). */
    public function goLive(): ?string
    {
        return $this->goLive->day();
    }

    /**
     * The type's schemes, oldest first.
     *
     * @return list<NumberingScheme>
     */
    public function schemes(int $companyId, SequenceType $type): array
    {
        return array_values(NumberingScheme::query()->where('company_id', $companyId)->where('sequence_type', $type->value)
            ->orderBy('effective_from')->get()->all());
    }

    /** The scheme in force on a day: the latest whose `effective_from` is not after it (NM-03). */
    public function schemeOn(int $companyId, SequenceType $type, string $day): ?NumberingScheme
    {
        return NumberingScheme::query()->where('company_id', $companyId)->where('sequence_type', $type->value)
            ->where('effective_from', '<=', $day)->orderByDesc('effective_from')->first();
    }

    /**
     * Whether a granularity governs a period: a scheme of the type with the period's granularity is in force on some
     * date of the period (NM-03).
     *
     * @param  list<NumberingScheme>  $schemes  oldest first
     */
    public static function governs(array $schemes, NumberingPeriod $period): bool
    {
        foreach ($schemes as $index => $scheme) {
            $from = $scheme->effective_from->toDateString();
            $next = $schemes[$index + 1] ?? null;
            $until = $next === null ? null : $next->effective_from->subDay()->toDateString();

            if ($scheme->period_granularity === $period->granularity && $from <= $period->lastDay() && ($until === null || $until >= $period->firstDay())) {
                return true;
            }
        }

        return false;
    }

    /**
     * The periods a day falls into under every granularity that governs them (NM-03): what a start seeds.
     *
     * @param  list<NumberingScheme>  $schemes
     * @return list<NumberingPeriod>
     */
    public static function periodsOf(array $schemes, string $day): array
    {
        return array_values(array_filter(
            array_map(fn (string $granularity): NumberingPeriod => NumberingPeriod::of($day, $granularity), NumberingPeriod::GRANULARITIES),
            fn (NumberingPeriod $period): bool => self::governs($schemes, $period),
        ));
    }

    /**
     * The seed boundary, `Y-m-d`, or null when the type has none (NM-03): the latest of the cutoff dates of the
     * batches, whatever their state, that hold a COMMITTED seed row of the type, and the day of the start, which the
     * start's audit event records by its typed columns — the company and the type's registered action code.
     */
    public function boundary(int $companyId, SequenceType $type): ?string
    {
        $cutoff = DB::table('import_batches')->whereExists(fn (Builder $rows) => $rows->from('import_rows')
            ->whereColumn('import_rows.import_batch_id', 'import_batches.id')
            ->where('import_rows.company_id', $companyId)->where('import_rows.import_kind', $type->seedImportKind())
            ->where('import_rows.validation_state', 'COMMITTED'))
            ->max('cutoff_date');
        $started = DB::table('audit_events')->where('action', self::START_ACTION.$type->auditSuffix())->where('company_id', $companyId)
            ->min('occurred_at');
        $days = array_filter([
            is_string($cutoff) ? substr($cutoff, 0, 10) : null,
            is_string($started) ? BusinessDate::of(CarbonImmutable::parse($started)) : null,
        ]);

        return $days === [] ? null : max($days);
    }

    /**
     * A period is live when it begins after the seed boundary and does not lie wholly before go-live (NM-03); with
     * no boundary, or go-live not recorded, no period is live.
     */
    public static function isLive(NumberingPeriod $period, ?string $boundary, ?string $goLive): bool
    {
        return $boundary !== null && $goLive !== null && $period->firstDay() > $boundary && $period->lastDay() >= $goLive;
    }

    /** Whether a period lies wholly before go-live; with go-live not recorded, every period does. */
    public static function beforeGoLive(NumberingPeriod $period, ?string $goLive): bool
    {
        return $goLive === null || $period->lastDay() < $goLive;
    }

    /**
     * The type's counters, by period.
     *
     * @return list<array{period_key: string, next_value: int, seed_value: int|null}>
     */
    public function counters(int $companyId, SequenceType $type): array
    {
        return array_values(DB::table('number_sequences')->where('company_id', $companyId)->where('sequence_type', $type->value)
            ->orderBy('period_key')->get(['period_key', 'next_value', 'seed_value'])
            ->map(fn (object $row): array => [
                'period_key' => is_string($row->period_key) ? $row->period_key : '',
                'next_value' => is_numeric($row->next_value) ? (int) $row->next_value : 1,
                'seed_value' => is_numeric($row->seed_value) ? (int) $row->seed_value : null,
            ])->all());
    }

    /**
     * The batches that are not ABORTED and hold VALID seed rows of the type for the company, each with the periods
     * those rows seed (NM-03).
     *
     * @return list<array{id: int, public_id: string, cutoff_date: string, periods: list<string>}>
     */
    public function pendingSeedBatches(int $companyId, SequenceType $type): array
    {
        $rows = DB::table('import_rows')->join('import_batches', 'import_batches.id', '=', 'import_rows.import_batch_id')
            ->where('import_rows.company_id', $companyId)->where('import_rows.import_kind', $type->seedImportKind())
            ->where('import_rows.validation_state', 'VALID')->where('import_batches.state', '!=', 'ABORTED')
            ->orderBy('import_batches.id')
            ->get(['import_batches.id', 'import_batches.public_id', 'import_batches.cutoff_date', 'import_rows.import_identity']);
        $batches = [];

        foreach ($rows as $row) {
            $id = is_numeric($row->id) ? (int) $row->id : 0;
            $batches[$id] ??= ['id' => $id, 'public_id' => is_string($row->public_id) ? $row->public_id : '', 'cutoff_date' => substr(is_string($row->cutoff_date) ? $row->cutoff_date : '', 0, 10), 'periods' => []];
            $identity = is_string($row->import_identity) ? explode('|', $row->import_identity, 2) : [];

            if (count($identity) === 2 && $identity[0] === (string) $companyId && NumberingPeriod::isKey($identity[1])) {
                $batches[$id]['periods'][] = $identity[1];
            }
        }

        return array_values($batches);
    }

    /**
     * The import batches whose scope covers the company, with their state and whether they still hold seed rows
     * (ADMIN_FLOW §12.1).
     *
     * @return list<array{public_id: string, state: string, cutoff_date: string, holds_seed_rows: bool}>
     */
    public function batchesCovering(int $companyId): array
    {
        return array_values(DB::table('import_batches')->whereRaw('? = any(company_scope)', [$companyId])->orderBy('id')
            ->get(['id', 'public_id', 'state', 'cutoff_date'])
            ->map(fn (object $batch): array => [
                'public_id' => is_string($batch->public_id) ? $batch->public_id : '',
                'state' => is_string($batch->state) ? $batch->state : '',
                'cutoff_date' => substr(is_string($batch->cutoff_date) ? $batch->cutoff_date : '', 0, 10),
                'holds_seed_rows' => $batch->state !== 'ABORTED' && DB::table('import_rows')->where('import_batch_id', $batch->id)
                    ->where('company_id', $companyId)->where('import_kind', 'like', 'NUMBER_SEED:%')->where('validation_state', 'VALID')->exists(),
            ])->all());
    }

    /**
     * The proof of NM-03 and FU-07: the highest number of a period that a version issued here, a project, a purchase
     * or a tombstone carries, or null when none carries the period. It reads issued numbers, never allocates one.
     */
    public function highestIssued(int $companyId, SequenceType $type, string $periodKey): ?int
    {
        $highest = match ($type) {
            SequenceType::Project => DB::table('projects')->where('company_id', $companyId)->where('period_key', $periodKey)->max('sequence_no'),
            SequenceType::Purchase => DB::table('purchases')->where('company_id', $companyId)->where('period_key', $periodKey)->max('sequence_no'),
            default => DB::table('document_versions')->where('company_id', $companyId)->where('document_type_code', $type->value)
                ->where('period_key', $periodKey)->whereNotNull('sequence_no')->max('sequence_no'),
        };
        $retired = DB::table('retired_numbers')->where('company_id', $companyId)->where('sequence_type', $type->value)
            ->where('period_key', $periodKey)->max('sequence_no');
        $values = array_map(fn (mixed $value): int => is_numeric($value) ? (int) $value : 0, array_filter([$highest, $retired], fn (mixed $value): bool => $value !== null));

        return $values === [] ? null : max($values);
    }
}
