<?php

namespace App\Modules\ORG\Actions;

use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Services\NumberingSeeds;
use App\Modules\ORG\Services\NumberingState;
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
use Illuminate\Support\Facades\DB;

/**
 * `ORG StartNumbering` (ROUTE-137; INT-137; NX-01; NM-03; HO-39), the numbering command's writes at LK-31 under the
 * company row (LK-06), its date the day of recording (IP-07; ST-08):
 * - the start of a type no import seeds — once go-live is recorded, while the type has no seed boundary and no batch
 *   that is not ABORTED holds VALID seed rows of it —, a seed for each period into which today falls under every
 *   granularity that governs it; its audit event, by the company and the type's registered action code, records the
 *   day that is the seed boundary;
 * - the Owner's seed of a period of the type that has no counter and is not live, once the type has its boundary —
 *   never one wholly before go-live, nor one a pending batch holds a VALID seed row for;
 * - after a restore, the Owner's raise (`DIR-083` OE-11; DIR-098 D2): each affected counter raised — or the live
 *   period's created — to the number after the highest the Owner records from the papers issued after the recovery
 *   point, refused, writing nothing, when it would not raise a counter or when the same recovery point already raised
 *   it; no seed value or boundary moves and no defect alert is raised; each raise's audit event names the incident.
 */
final class StartNumbering
{
    public const TYPE = 'ORG.StartNumbering';

    public const SEEDED = 'ORG.NumberingSeeded';

    public const RAISED = 'ORG.NumberingRaised';

    public function __construct(
        private readonly CommandEnvelope $envelope,
        private readonly NumberingState $state,
        private readonly NumberingSeeds $seeds,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * The start: one first number per period of today the type's schemes govern.
     *
     * @param  array<string, int>  $firstNumbers  by period key
     */
    public function start(string $commandId, int $actorUserId, Company $company, SequenceType $type, array $firstNumbers, string $businessDate, bool $prefilled): Outcome
    {
        ksort($firstNumbers);

        return $this->run($commandId, $actorUserId, $company, ['mode' => 'start', 'type' => $type->value, 'first_numbers' => $firstNumbers, 'business_date' => $businessDate, 'prefilled' => $prefilled],
            function (CommandContext $context) use ($company, $type, $firstNumbers, $businessDate, $prefilled): CommandResult {
                $context->lock($this->locks($company, $type, array_map('strval', array_keys($firstNumbers))));
                BusinessDateRule::check($businessDate, $prefilled);
                $goLive = $this->state->goLive();

                if ($goLive === null) {
                    throw Refused::rejected('NM-03', ['reason' => 'go_live']);
                }

                if ($this->state->boundary($company->id, $type) !== null) {
                    throw Refused::rejected('NM-03', ['reason' => 'started']);
                }

                $this->refusePendingBatch($company->id, $type, null);
                $periods = NumberingState::periodsOf($this->state->schemes($company->id, $type), $businessDate);

                if ($periods === []) {
                    throw Refused::rejected('NM-03', ['reason' => 'no_scheme']);
                }

                $keys = array_map(fn (NumberingPeriod $period): string => $period->key, $periods);
                sort($keys);

                if ($keys !== array_map('strval', array_keys($firstNumbers))) {
                    throw Refused::conflict('ST-01', ['reason' => 'periods', 'periods' => $keys]);
                }

                $seeds = array_map(fn (NumberingPeriod $period): array => $this->seeds->seed($context, $company->id, $type, $period, $firstNumbers[$period->key]), $periods);
                $this->audit->record(
                    action: NumberingState::START_ACTION.$type->auditSuffix(),
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $context->request->actorUserId,
                    companyId: $company->id,
                    entityType: 'company',
                    entityId: $company->id,
                    entityPublicId: $company->public_id,
                    commandId: $context->request->commandId,
                    businessDate: $businessDate,
                    metadata: ['sequence_type' => $type->value, 'seeds' => $seeds],
                );

                return new CommandResult('company', $company->id);
            });
    }

    /** The Owner's seed of one period that is not live and has no counter. */
    public function seed(string $commandId, int $actorUserId, Company $company, SequenceType $type, string $periodKey, int $first, string $businessDate, bool $prefilled): Outcome
    {
        return $this->run($commandId, $actorUserId, $company, ['mode' => 'seed', 'type' => $type->value, 'period_key' => $periodKey, 'first_number' => $first, 'business_date' => $businessDate, 'prefilled' => $prefilled],
            function (CommandContext $context) use ($company, $type, $periodKey, $first, $businessDate, $prefilled): CommandResult {
                $context->lock($this->locks($company, $type, [$periodKey]));
                BusinessDateRule::check($businessDate, $prefilled);
                $goLive = $this->state->goLive();
                $boundary = $this->state->boundary($company->id, $type);
                $period = NumberingPeriod::fromKey($periodKey);

                $reason = match (true) {
                    $goLive === null => 'go_live',
                    $boundary === null => 'not_started',
                    ! NumberingState::governs($this->state->schemes($company->id, $type), $period) => 'not_governed',
                    NumberingState::isLive($period, $boundary, $goLive) => 'live',
                    NumberingState::beforeGoLive($period, $goLive) => 'before_go_live',
                    $this->counterExists($company->id, $type, $periodKey) => 'has_counter',
                    default => null,
                };

                if ($reason !== null) {
                    throw Refused::rejected('NM-03', ['reason' => $reason, 'periode' => $periodKey]);
                }

                $this->refusePendingBatch($company->id, $type, $periodKey);
                $seed = $this->seeds->seed($context, $company->id, $type, $period, $first);
                $this->audit->record(
                    action: self::SEEDED,
                    authorityClass: AuthorityClass::OwnerOnly,
                    actorUserId: $context->request->actorUserId,
                    companyId: $company->id,
                    entityType: 'company',
                    entityId: $company->id,
                    entityPublicId: $company->public_id,
                    commandId: $context->request->commandId,
                    businessDate: $businessDate,
                    metadata: ['sequence_type' => $type->value, 'seed' => $seed],
                );

                return new CommandResult('company', $company->id);
            });
    }

    /**
     * The Owner's raise after a restore: the highest paper number per period, the restore's reference and its
     * recovery point naming the incident.
     *
     * @param  array<string, int>  $highest  the highest number on the papers, by period key
     */
    public function raise(string $commandId, int $actorUserId, Company $company, SequenceType $type, string $restore, string $recoveryPoint, array $highest, string $businessDate, bool $prefilled): Outcome
    {
        ksort($highest);

        return $this->run($commandId, $actorUserId, $company, ['mode' => 'raise', 'type' => $type->value, 'restore' => $restore, 'recovery_point' => $recoveryPoint, 'highest' => $highest, 'business_date' => $businessDate, 'prefilled' => $prefilled],
            function (CommandContext $context) use ($company, $type, $restore, $recoveryPoint, $highest, $businessDate, $prefilled): CommandResult {
                $context->lock($this->locks($company, $type, array_map('strval', array_keys($highest))));
                BusinessDateRule::check($businessDate, $prefilled);
                $boundary = $this->state->boundary($company->id, $type);
                $goLive = $this->state->goLive();
                $schemes = $this->state->schemes($company->id, $type);
                $raises = [];

                foreach ($highest as $period => $paper) {
                    $periodKey = (string) $period;
                    $period = NumberingPeriod::fromKey($periodKey);
                    $counter = DB::table('number_sequences')->where('company_id', $company->id)->where('sequence_type', $type->value)->where('period_key', $periodKey)->value('next_value');
                    $current = is_numeric($counter) ? (int) $counter : null;

                    $reason = match (true) {
                        ! NumberingState::governs($schemes, $period) => 'raise_not_governed',
                        $current === null && ! NumberingState::isLive($period, $boundary, $goLive) => 'raise_not_live',
                        $current !== null && $paper + 1 <= $current => 'raise_not_higher',
                        $this->raisedInRecovery($company->id, $type, $periodKey, $recoveryPoint) => 'raise_repeated',
                        default => null,
                    };

                    if ($reason !== null) {
                        throw Refused::rejected('NX-01', ['reason' => $reason, 'periode' => $periodKey, 'next' => $current]);
                    }

                    $raises[] = ['period_key' => $periodKey, 'from' => $current, 'to' => $paper + 1];
                }

                foreach ($raises as $raise) {
                    if ($raise['from'] === null) {
                        DB::table('number_sequences')->insert(['company_id' => $company->id, 'sequence_type' => $type->value, 'period_key' => $raise['period_key'],
                            'next_value' => $raise['to'], 'seed_value' => null, 'recorded_at' => $context->now(), 'recorded_by' => $context->request->actorUserId]);
                    } else {
                        DB::table('number_sequences')->where('company_id', $company->id)->where('sequence_type', $type->value)
                            ->where('period_key', $raise['period_key'])->update(['next_value' => $raise['to']]);
                    }

                    $this->audit->record(
                        action: self::RAISED,
                        authorityClass: AuthorityClass::OwnerOnly,
                        actorUserId: $context->request->actorUserId,
                        companyId: $company->id,
                        entityType: 'company',
                        entityId: $company->id,
                        entityPublicId: $company->public_id,
                        reason: $restore,
                        commandId: $context->request->commandId,
                        businessDate: $businessDate,
                        metadata: ['sequence_type' => $type->value, ...$raise, 'restore' => $restore, 'recovery_point' => $recoveryPoint],
                    );
                }

                return new CommandResult('company', $company->id);
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  \Closure(CommandContext): CommandResult  $action
     */
    private function run(string $commandId, int $actorUserId, Company $company, array $payload, \Closure $action): Outcome
    {
        $request = new CommandRequest($commandId, self::TYPE, $actorUserId, $company->id, 'companies.manage', Fingerprint::of(self::TYPE, $company->public_id, $payload));

        return $this->envelope->run($request, $action);
    }

    /** @param  list<string>  $periodKeys */
    private function locks(Company $company, SequenceType $type, array $periodKeys): LockSet
    {
        $locks = (new LockSet)->add('companies', ['id' => $company->id]);

        foreach ($periodKeys as $periodKey) {
            if (! NumberingPeriod::isKey($periodKey)) {
                throw Refused::rejected('NM-03', ['reason' => 'period', 'periode' => $periodKey]);
            }

            $locks->add('number_sequences', ['company_id' => $company->id, 'sequence_type' => $type->value, 'period_key' => $periodKey]);
        }

        return $locks;
    }

    /** A start, or a seed of the period, is refused while a batch that is not ABORTED holds VALID seed rows of it (NM-03). */
    private function refusePendingBatch(int $companyId, SequenceType $type, ?string $periodKey): void
    {
        foreach ($this->state->pendingSeedBatches($companyId, $type) as $batch) {
            if ($periodKey === null || in_array($periodKey, $batch['periods'], true)) {
                throw Refused::rejected('NM-03', ['reason' => 'batch', 'batch' => $batch['public_id'], 'periode' => $periodKey]);
            }
        }
    }

    private function counterExists(int $companyId, SequenceType $type, string $periodKey): bool
    {
        return DB::table('number_sequences')->where('company_id', $companyId)->where('sequence_type', $type->value)->where('period_key', $periodKey)->exists();
    }

    /** Whether the same recovery — its recovery point — already raised the counter (DIR-098 D2). */
    private function raisedInRecovery(int $companyId, SequenceType $type, string $periodKey, string $recoveryPoint): bool
    {
        return DB::table('audit_events')->where('action', self::RAISED)->where('company_id', $companyId)
            ->whereRaw("metadata->>'sequence_type' = ?", [$type->value])->whereRaw("metadata->>'period_key' = ?", [$periodKey])
            ->whereRaw("metadata->>'recovery_point' = ?", [$recoveryPoint])->exists();
    }
}
