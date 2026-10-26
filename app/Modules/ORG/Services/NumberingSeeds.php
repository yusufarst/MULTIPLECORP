<?php

namespace App\Modules\ORG\Services;

use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Support\Command\CommandContext;
use App\Support\GuardMissing;
use Illuminate\Support\Facades\Log;

/**
 * A seed of the numbering command (NM-03; FU-07), its counter locked at LK-31 by the caller: a seed that creates its
 * counter states its first number as `next_value` and `seed_value`, once the proof shows no number of the period
 * issued here at or above it — a failed proof is a lost counter (SQ-18); a seed that meets an existing counter may
 * raise it, never lower it, and moves its seed value with it, alerting a defect when the counter has drawn a number
 * and the new seed exceeds the counter's first number — its seed value, or 1 after a first use.
 */
final class NumberingSeeds
{
    public function __construct(private readonly NumberingState $state) {}

    /** @return array{period_key: string, first_number: int, created: bool} */
    public function seed(CommandContext $context, int $companyId, SequenceType $type, NumberingPeriod $period, int $first): array
    {
        $counter = $context->connection->table('number_sequences')->where('company_id', $companyId)->where('sequence_type', $type->value)
            ->where('period_key', $period->key)->first(['next_value', 'seed_value']);

        if ($counter === null) {
            $issued = $this->state->highestIssued($companyId, $type, $period->key);

            if ($issued !== null && $issued >= $first) {
                Log::critical('A seed meets numbers issued here at or above its first number: a counter was lost (NM-03; SQ-18).', ['company_id' => $companyId, 'sequence_type' => $type->value, 'period_key' => $period->key]);

                throw new GuardMissing('number_sequences', 1, 0);
            }

            // FU-07's insert-or-lock: a counter a first use created meanwhile is met, never a unique-key failure.
            $created = $context->connection->affectingStatement(
                'insert into number_sequences (company_id, sequence_type, period_key, next_value, seed_value, recorded_at, recorded_by) values (?, ?, ?, ?, ?, ?, ?) on conflict (company_id, sequence_type, period_key) do nothing',
                [$companyId, $type->value, $period->key, $first, $first, $context->now(), $context->request->actorUserId],
            );

            if ($created === 1) {
                return ['period_key' => $period->key, 'first_number' => $first, 'created' => true];
            }

            $counter = $context->connection->table('number_sequences')->where('company_id', $companyId)->where('sequence_type', $type->value)
                ->where('period_key', $period->key)->lock('for no key update')->first(['next_value', 'seed_value'])
                ?? throw new GuardMissing('number_sequences', 1, 0);
        }

        $next = is_numeric($counter->next_value) ? (int) $counter->next_value : 1;
        $seed = is_numeric($counter->seed_value) ? (int) $counter->seed_value : null;

        if ($next > ($seed ?? 1) && $first > ($seed ?? 1)) {
            Log::alert('A seed exceeds the first number of a counter that has drawn numbers: a number may have been reused (NM-03).', ['company_id' => $companyId, 'sequence_type' => $type->value, 'period_key' => $period->key]);
        }

        $context->connection->table('number_sequences')->where('company_id', $companyId)->where('sequence_type', $type->value)
            ->where('period_key', $period->key)
            ->update(['next_value' => max($next, $first), 'seed_value' => max($seed ?? $first, $first)]);

        return ['period_key' => $period->key, 'first_number' => $first, 'created' => false];
    }
}
