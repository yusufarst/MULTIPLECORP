<?php

namespace App\Modules\ORG\Actions;

use App\Modules\ORG\Data\NumberFormat;
use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\ORG\Services\NumberingSeeds;
use App\Modules\ORG\Services\NumberingState;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditRecorder;
use App\Support\Audit\AuthorityClass;
use App\Support\Command\BusinessDateRule;
use App\Support\Command\CommandContext;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use App\Support\Command\Outcome;
use App\Support\Command\Refused;

/**
 * `ORG ConfigureNumbering` (ROUTE-136; INT-136; NX-01): a numbering scheme of a company's type added, or one not yet
 * in force changed — one in force is closed by a later scheme (CF) —; it creates no counter. Once the type has its
 * seed boundary it refuses a scheme that takes effect before the day it is recorded, unless the scheme only extends
 * the type's schemes backwards over dates no scheme covered, and one whose first period is not live and has no
 * counter, unless the same command seeds that period or the scheme is such an extension; while a batch that is not
 * ABORTED holds VALID seed rows of the type, it refuses a scheme that would govern the batch's cutoff period without a
 * seed row for it (NM-03). The company row (LK-06), the changed scheme (LK-08) and a seeded counter (LK-31) are
 * locked in that order; the change is audited with its before and after, OWNER_ONLY (LG-01).
 */
final class ConfigureNumbering
{
    public const TYPE = 'ORG.ConfigureNumbering';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly NumberingState $state,
        private readonly NumberingSeeds $seeds,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{scheme_id: ?int, lock_version: ?int, period_granularity: string, format_template: string, revision_format: string, effective_from: string, first_number: ?int, business_date: string, business_date_prefilled: bool}  $input
     */
    public function run(string $commandId, int $actorUserId, Company $company, SequenceType $type, array $input): Outcome
    {
        $payload = ['type' => $type->value, ...$input];
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $company->id, 'companies.manage', Fingerprint::of(self::TYPE, $company->public_id, $payload));

        return $this->envelope->run($request, fn (CommandContext $context): CommandResult => $this->act($context, $company, $type, $input));
    }

    /**
     * @param  array{scheme_id: ?int, lock_version: ?int, period_granularity: string, format_template: string, revision_format: string, effective_from: string, first_number: ?int, business_date: string, business_date_prefilled: bool}  $input
     */
    private function act(CommandContext $context, Company $company, SequenceType $type, array $input): CommandResult
    {
        $first = NumberingPeriod::of($input['effective_from'], $input['period_granularity']);
        $locks = (new LockSet)->add('companies', ['id' => $company->id]);

        if ($input['scheme_id'] !== null) {
            $locks->add('numbering_schemes', ['id' => $input['scheme_id']]);
        }

        if ($input['first_number'] !== null) {
            $locks->add('number_sequences', ['company_id' => $company->id, 'sequence_type' => $type->value, 'period_key' => $first->key]);
        }

        $context->lock($locks);
        BusinessDateRule::check($input['business_date'], $input['business_date_prefilled']);
        $today = $input['business_date'];

        if (NumberFormat::problem($input['format_template'], $input['period_granularity']) !== null) {
            throw Refused::rejected('GAP-019', ['reason' => 'format']);
        }

        $changed = $input['scheme_id'] === null ? null : NumberingScheme::query()->whereKey($input['scheme_id'])
            ->where('company_id', $company->id)->where('sequence_type', $type->value)->first();

        if ($input['scheme_id'] !== null && $changed === null) {
            throw Refused::conflict('ST-01', ['reason' => 'scheme_gone']);
        }

        if ($changed !== null && $changed->lock_version !== $input['lock_version']) {
            throw Refused::conflict('ST-01', ['reason' => 'stale']);
        }

        // CF: a scheme in force is closed by a later scheme, never changed.
        if ($changed !== null && $changed->effective_from->toDateString() <= $today) {
            throw Refused::rejected('NM-03', ['reason' => 'in_force', 'effective_from' => $changed->effective_from->toDateString()]);
        }

        $others = array_values(array_filter($this->state->schemes($company->id, $type), fn (NumberingScheme $scheme): bool => $scheme->id !== $changed?->id));

        if (array_filter($others, fn (NumberingScheme $scheme): bool => $scheme->effective_from->toDateString() === $input['effective_from']) !== []) {
            throw Refused::rejected('NM-03', ['reason' => 'same_date', 'effective_from' => $input['effective_from']]);
        }

        $boundary = $this->state->boundary($company->id, $type);
        $goLive = $this->state->goLive();
        $backward = $changed === null && $others !== [] && $input['effective_from'] < $others[0]->effective_from->toDateString();
        $seeded = null;

        if ($boundary !== null) {
            if ($input['effective_from'] < $today && ! $backward) {
                throw Refused::rejected('NM-03', ['reason' => 'past', 'earliest' => $today]);
            }

            $hasCounter = array_filter($this->state->counters($company->id, $type), fn (array $counter): bool => $counter['period_key'] === $first->key) !== [];

            if (! $backward && ! $hasCounter && ! NumberingState::isLive($first, $boundary, $goLive)) {
                if ($input['first_number'] === null) {
                    throw Refused::rejected('NM-03', ['reason' => 'first_period', 'periode' => $first->key]);
                }

                if (NumberingState::beforeGoLive($first, $goLive)) {
                    throw Refused::rejected('NM-03', ['reason' => 'before_go_live', 'periode' => $first->key]);
                }

                $seeded = $first;
            }
        }

        $this->refuseUncoveredBatchCutoff($company->id, $type, $others, $input);

        $values = [
            'period_granularity' => $input['period_granularity'],
            'format_template' => $input['format_template'],
            'revision_format' => $input['revision_format'],
            'effective_from' => $input['effective_from'],
        ];

        if ($changed === null) {
            $scheme = new NumberingScheme;
            $scheme->forceFill([...$values, 'company_id' => $company->id, 'sequence_type' => $type->value, 'recorded_by' => $context->request->actorUserId,
                'command_id' => $context->request->commandId, 'command_line_no' => 1, 'recorded_at' => $context->now()])->save();
            $before = null;
        } else {
            $before = $changed->only(array_keys($values));
            $before['effective_from'] = $changed->effective_from->toDateString();
            $updated = NumberingScheme::query()->whereKey($changed->id)->where('lock_version', $changed->lock_version)
                ->update([...$values, 'lock_version' => $changed->lock_version + 1, 'updated_at' => $context->now()]);

            if ($updated !== 1) {
                throw Refused::conflict('ST-01', ['reason' => 'stale']);
            }

            $scheme = $changed;
        }

        $seed = $seeded === null ? null : $this->seeds->seed($context, $company->id, $type, $seeded, (int) $input['first_number']);

        $this->audit->record(
            action: 'ORG.NumberingSchemeConfigured',
            authorityClass: AuthorityClass::OwnerOnly,
            actorUserId: $context->request->actorUserId,
            companyId: $company->id,
            entityType: 'numbering_scheme',
            entityId: $scheme->id,
            commandId: $context->request->commandId,
            change: $before === null ? AuditChange::created($values) : AuditChange::updated($before, $values),
            businessDate: $today,
            metadata: ['sequence_type' => $type->value, 'seed' => $seed],
        );

        return new CommandResult('numbering_scheme', $scheme->id);
    }

    /**
     * While a batch that is not ABORTED holds VALID seed rows of the type, a scheme that would govern the period into
     * which the batch's cutoff falls is refused when the batch holds no seed row for that period (NM-03).
     *
     * @param  list<NumberingScheme>  $others
     * @param  array{period_granularity: string, effective_from: string, format_template: string, revision_format: string}  $input
     */
    private function refuseUncoveredBatchCutoff(int $companyId, SequenceType $type, array $others, array $input): void
    {
        $candidate = new NumberingScheme;
        $candidate->forceFill(['period_granularity' => $input['period_granularity'], 'effective_from' => $input['effective_from']]);
        $schemes = [...$others, $candidate];
        usort($schemes, fn (NumberingScheme $a, NumberingScheme $b): int => $a->effective_from <=> $b->effective_from);

        foreach ($this->state->pendingSeedBatches($companyId, $type) as $batch) {
            $period = NumberingPeriod::of($batch['cutoff_date'], $input['period_granularity']);
            $without = array_values(array_filter($schemes, fn (NumberingScheme $scheme): bool => $scheme !== $candidate));

            if (NumberingState::governs($schemes, $period) && ! NumberingState::governs($without, $period) && ! in_array($period->key, $batch['periods'], true)) {
                throw Refused::rejected('NM-03', ['reason' => 'batch_cutoff', 'batch' => $batch['public_id'], 'periode' => $period->key]);
            }
        }
    }
}
