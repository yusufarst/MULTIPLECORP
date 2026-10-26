<?php

namespace Tests\Support;

use App\Modules\ORG\Data\DrawnNumber;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\ORG\Services\NumberAllocator;
use App\Modules\ORG\Services\NumberingGoLive;
use App\Modules\ORG\Services\NumberingState;
use App\Support\Command\CommandEnvelope;
use App\Support\Command\CommandRequest;
use App\Support\Command\CommandResult;
use App\Support\Command\Fingerprint;
use App\Support\Command\LockSet;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The numbering tests' fixtures (TASK-021): go-live as a test binds it — the store is TASK-077's (DIR-098 reading 2)
 * —, a company's schemes, its seeds and start, and an issue that draws a number through the one allocator as the
 * issuing Tasks will (SF-ISSUE), each draw its own command, answering its outcome and number as text so a session in
 * another process can return it. Synthetic data only.
 */
final class Numbering
{
    public static function goLive(?string $day): void
    {
        app()->instance(NumberingGoLive::class, new class($day) implements NumberingGoLive
        {
            public function __construct(private readonly ?string $recorded) {}

            public function day(): ?string
            {
                return $this->recorded;
            }
        });
    }

    public static function scheme(Company $company, SequenceType $type, string $from = '2026-01-01', string $granularity = 'YEAR', string $template = 'CTH/{tahun}/{nomor:4}'): NumberingScheme
    {
        return NumberingScheme::factory()->create([
            'company_id' => $company->id, 'sequence_type' => $type->value, 'period_granularity' => $granularity,
            'format_template' => $template, 'effective_from' => $from,
        ]);
    }

    /** A counter seeded with its first number, as the migration or the numbering command writes it. */
    public static function seed(Company $company, SequenceType $type, string $period, int $first): void
    {
        DB::table('number_sequences')->insert(['company_id' => $company->id, 'sequence_type' => $type->value, 'period_key' => $period, 'next_value' => $first, 'seed_value' => $first]);
    }

    /** The start's audit event, whose day is the type's seed boundary (NM-03). */
    public static function started(Company $company, SequenceType $type, CarbonImmutable $at): void
    {
        DB::table('audit_events')->insert(['occurred_at' => $at, 'recorded_at' => $at, 'action' => NumberingState::START_ACTION.$type->auditSuffix(),
            'company_id' => $company->id, 'authority_class' => 'OWNER_ONLY', 'entity_type' => 'company', 'entity_id' => $company->id]);
    }

    /**
     * An issue's draw as its own command: `COMMITTED <number> <formatted>`, or the refusal's kind and code.
     *
     * @param  Closure(DrawnNumber): void|null  $after  run after the draw, inside the issue's transaction
     */
    public static function draw(int $actorUserId, Company $company, SequenceType $type, string $businessDate, ?Closure $after = null, ?string $commandId = null): string
    {
        $drawn = null;
        $request = new CommandRequest($commandId ?? (string) Str::uuid(), 'test.issue', $actorUserId, $company->id, 'companies.manage', Fingerprint::of('test.issue', $company->public_id, ['type' => $type->value, 'date' => $businessDate]));
        $outcome = app(CommandEnvelope::class)->run($request, function ($context) use ($company, $type, $businessDate, $after, &$drawn) {
            $context->lock((new LockSet)->add('companies', ['id' => $company->id]));
            $drawn = app(NumberAllocator::class)->draw($context, $company->id, $type, $businessDate);
            $after?->__invoke($drawn);

            return new CommandResult('test', null);
        });

        return $drawn !== null && $outcome->kind === 'COMMITTED' ? "COMMITTED {$drawn->sequenceNo} {$drawn->formatted}" : trim($outcome->kind.' '.($outcome->code ?? ''));
    }

    public static function next(Company $company, SequenceType $type, string $period): ?int
    {
        $value = DB::table('number_sequences')->where('company_id', $company->id)->where('sequence_type', $type->value)->where('period_key', $period)->value('next_value');

        return is_numeric($value) ? (int) $value : null;
    }
}
