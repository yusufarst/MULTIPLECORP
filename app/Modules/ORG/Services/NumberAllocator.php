<?php

namespace App\Modules\ORG\Services;

use App\Modules\ORG\Data\DrawnNumber;
use App\Modules\ORG\Data\NumberFormat;
use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Support\Command\CommandContext;
use App\Support\Command\LockSet;
use App\Support\Command\Refused;
use App\Support\GuardMissing;
use Illuminate\Support\Facades\Log;

/**
 * The one numbering service (D-DB-12; CONCURRENCY_IDEMPOTENCY NM-01–NM-10): an issuing action draws its number at
 * issue only (SF-ISSUE), inside its own transaction, the counter (LK-31) its last lock, after every business anchor.
 * The period comes from the business date and the scheme in force on it; the counter is created on first use only
 * for a live period, once FU-07's proof shows no number of the period issued here; an issue that finds no counter
 * for a period that is not live is REJECTED (C-10) — or SQ-18 when a number of the period exists, a counter lost —;
 * then one statement increments the counter and returns the pre-increment value. Never the highest number plus one, never a
 * PostgreSQL sequence, no gapless promise (NM-10).
 */
final class NumberAllocator
{
    public function __construct(private readonly NumberingState $state) {}

    /**
     * Draws the next number of the type's period of a business date.
     *
     * @throws Refused C-10 when the period may create no counter (NM-03)
     */
    public function draw(CommandContext $context, int $companyId, SequenceType $type, string $businessDate): DrawnNumber
    {
        $scheme = $this->state->schemeOn($companyId, $type, $businessDate);

        if ($scheme === null) {
            throw Refused::rejected('C-10', ['jenis' => $type->value, 'periode' => $businessDate]);
        }

        $period = NumberingPeriod::of($businessDate, $scheme->period_granularity);
        $key = ['company_id' => $companyId, 'sequence_type' => $type->value, 'period_key' => $period->key];
        $missing = $context->lock((new LockSet)->add('number_sequences', $key));

        if ($missing !== []) {
            $this->firstUse($context, $companyId, $type, $period);
        }

        $drawn = $context->connection->selectOne(
            'update number_sequences set next_value = next_value + 1 where company_id = ? and sequence_type = ? and period_key = ? returning next_value - 1 as allocated',
            [$companyId, $type->value, $period->key],
        );

        if (! is_object($drawn) || ! is_numeric($drawn->allocated ?? null)) {
            throw new GuardMissing('number_sequences', 1, 0);
        }

        $number = (int) $drawn->allocated;

        return new DrawnNumber($scheme->id, $period->key, $number, NumberFormat::format($scheme->format_template, $period->key, $number));
    }

    /**
     * FU-07: insert-or-lock the counter with `next_value` 1 and no seed. Only when the insert really creates it is
     * it a first use, which needs a live period and no number of the period issued here: a period that is not live
     * gets no counter — REJECTED (C-10) —, and an issued number means a lost counter (SQ-18); either way the
     * command's savepoint removes the row again. A counter another issuer created meanwhile is simply locked.
     */
    private function firstUse(CommandContext $context, int $companyId, SequenceType $type, NumberingPeriod $period): void
    {
        $created = $context->connection->affectingStatement(
            'insert into number_sequences (company_id, sequence_type, period_key, next_value, seed_value) values (?, ?, ?, 1, null) on conflict (company_id, sequence_type, period_key) do nothing',
            [$companyId, $type->value, $period->key],
        );
        $context->connection->table('number_sequences')->where('company_id', $companyId)->where('sequence_type', $type->value)
            ->where('period_key', $period->key)->lock('for no key update')->first(['next_value']);

        if ($created !== 1) {
            return;
        }

        if ($this->state->highestIssued($companyId, $type, $period->key) !== null) {
            Log::critical('A number counter is missing under numbers issued here (NM-03; FU-07; SQ-18).', ['company_id' => $companyId, 'sequence_type' => $type->value, 'period_key' => $period->key]);

            throw new GuardMissing('number_sequences', 1, 0);
        }

        if (! NumberingState::isLive($period, $this->state->boundary($companyId, $type), $this->state->goLive())) {
            throw Refused::rejected('C-10', ['jenis' => $type->value, 'periode' => $period->key]);
        }
    }
}
