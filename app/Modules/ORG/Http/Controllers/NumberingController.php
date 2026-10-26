<?php

namespace App\Modules\ORG\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IAM\Models\User;
use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Actions\ConfigureNumbering;
use App\Modules\ORG\Actions\StartNumbering;
use App\Modules\ORG\Data\NumberFormat;
use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\ORG\Queries\CompanyDirectory;
use App\Modules\ORG\Services\NumberingState;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * PG-061 *Penomoran Dokumen* (ROUTE-061, ROUTE-136, ROUTE-137; INT-136, INT-137; NAV-061; ADMIN_FLOW §12), Owner only
 * (`companies.manage`; OD-03): per company and numbered type — the document types, PROJECT and PURCHASE —, the type's
 * schemes, its counters, its seed boundary, go-live and the import batches that cover the company (§12.1); the scheme
 * form (ROUTE-136) and the numbering command's start, seed and raise (ROUTE-137) with their confirmations (IP-08).
 * The company travels in the payload and the type in the address (DIR-098 reading 3); a command answers in place,
 * as flash data the next page reads once (IP-01), and a validation error at its fields (IP-14).
 */
final class NumberingController extends Controller
{
    public function __construct(
        private readonly NumberingState $state,
        private readonly CompanyDirectory $companies,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** `ORG ShowNumbering` (ROUTE-061). */
    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $companies = $this->companies->inScope($this->capabilities->for($user)->scope);
        $companyId = $request->query('perusahaan');
        $selected = collect($companies)->firstWhere('id', is_string($companyId) ? strtolower($companyId) : null) ?? ($companies[0] ?? null);
        $slug = $request->query('jenis');
        $type = (is_string($slug) ? SequenceType::fromSlug($slug) : null) ?? SequenceType::Doc01;
        $company = $selected === null ? null : Company::query()->where('public_id', $selected['id'])->first();

        return Inertia::render('Settings/Numbering', [
            'companies' => $companies,
            'company' => $selected['id'] ?? null,
            'types' => array_map(fn (SequenceType $case): array => ['slug' => $case->slug(), 'code' => $case->value, 'label' => $case->labelKey()], SequenceType::cases()),
            'type' => $type->slug(),
            'detail' => $company === null ? null : $this->detail($company, $type),
        ]);
    }

    /** `ORG ConfigureNumbering` (ROUTE-136). */
    public function configure(Request $request, string $type, ConfigureNumbering $action): RedirectResponse
    {
        $sequence = $this->type($type);
        $input = $this->validated($request, [
            'command_id' => ['required', 'uuid'],
            'company' => ['required', 'uuid'],
            'scheme_id' => ['nullable', 'integer', 'min:1'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
            'period_granularity' => ['required', 'in:YEAR,MONTH'],
            'format_template' => ['required', 'string'],
            'revision_format' => ['required', 'in:NEW_NUMBER,BASE_SUFFIX'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'first_number' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'business_date_prefilled' => ['required', 'boolean'],
        ], function (ValidatorInstance $validator) use ($request): void {
            $template = $request->input('format_template');
            $granularity = $request->input('period_granularity');
            $problem = is_string($template) && is_string($granularity) ? NumberFormat::problem($template, $granularity) : null;

            if ($problem !== null) {
                $validator->errors()->add('format_template', __($problem));
            }
        });
        $company = $this->company($request, self::text($input['company']));
        $outcome = $action->run(self::text($input['command_id']), $this->user($request)->id, $company, $sequence, [
            'scheme_id' => self::integer($input['scheme_id'] ?? null),
            'lock_version' => self::integer($input['lock_version'] ?? null),
            'period_granularity' => self::text($input['period_granularity']),
            'format_template' => self::text($input['format_template']),
            'revision_format' => self::text($input['revision_format']),
            'effective_from' => self::text($input['effective_from']),
            'first_number' => self::integer($input['first_number'] ?? null),
            'business_date' => self::text($input['business_date']),
            'business_date_prefilled' => (bool) $input['business_date_prefilled'],
        ]);

        return $this->answer($outcome, $company, $sequence, 'configured', self::text($input['business_date']));
    }

    /** `ORG StartNumbering` (ROUTE-137): the start, the Owner's seed and the raise after a restore. */
    public function start(Request $request, string $type, StartNumbering $action): RedirectResponse
    {
        $sequence = $this->type($type);
        $common = ['command_id' => ['required', 'uuid'], 'company' => ['required', 'uuid'], 'mode' => ['required', 'in:start,seed,raise'],
            'business_date' => ['required', 'date_format:Y-m-d'], 'business_date_prefilled' => ['required', 'boolean']];
        $mode = $request->input('mode');
        $rules = match ($mode) {
            'start' => ['first_numbers' => ['required', 'array', 'min:1', 'max:2'], 'first_numbers.*' => ['required', 'integer', 'min:1', 'max:999999999999']],
            'seed' => ['period_key' => ['required', 'string', 'regex:/^\d{4}(-(0[1-9]|1[0-2]))?$/'], 'first_number' => ['required', 'integer', 'min:1', 'max:999999999999']],
            'raise' => ['restore_reference' => ['required', 'string', 'max:120'], 'recovery_point' => ['required', 'date'],
                'lines' => ['required', 'array', 'min:1', 'max:24'], 'lines.*.period_key' => ['required', 'string', 'distinct', 'regex:/^\d{4}(-(0[1-9]|1[0-2]))?$/'],
                'lines.*.highest' => ['required', 'integer', 'min:1', 'max:999999999999']],
            default => [],
        };
        $input = $this->validated($request, [...$common, ...$rules], function (ValidatorInstance $validator) use ($request, $mode): void {
            if ($mode === 'start') {
                foreach (array_keys((array) $request->input('first_numbers', [])) as $period) {
                    if (! NumberingPeriod::isKey((string) $period)) {
                        $validator->errors()->add('first_numbers', __('numbering.refusal.period', ['periode' => (string) $period]));
                    }
                }
            }

            if ($mode === 'raise' && ! $validator->errors()->has('recovery_point')) {
                try {
                    $point = CarbonImmutable::parse(self::text($request->input('recovery_point')));
                } catch (Throwable) {
                    $point = null;
                }

                if ($point === null || $point->greaterThan(CarbonImmutable::now())) {
                    $validator->errors()->add('recovery_point', __('numbering.validation.instant'));
                }
            }
        });
        $company = $this->company($request, self::text($input['company']));
        $date = self::text($input['business_date']);
        $prefilled = (bool) $input['business_date_prefilled'];
        $commandId = self::text($input['command_id']);
        $actor = $this->user($request)->id;

        $outcome = match ($mode) {
            'start' => $action->start($commandId, $actor, $company, $sequence, self::numbers((array) $input['first_numbers']), $date, $prefilled),
            'seed' => $action->seed($commandId, $actor, $company, $sequence, self::text($input['period_key']), (int) self::integer($input['first_number']), $date, $prefilled),
            default => $action->raise($commandId, $actor, $company, $sequence, trim(self::text($input['restore_reference'])),
                CarbonImmutable::parse(self::text($input['recovery_point']))->utc()->format('Y-m-d\TH:i:s.u\Z'), self::lines((array) $input['lines']), $date, $prefilled),
        };

        return $this->answer($outcome, $company, $sequence, match ($mode) {
            'start' => 'started', 'seed' => 'seeded', default => 'raised'
        }, $date);
    }

    /** @return array<string, mixed> */
    private function detail(Company $company, SequenceType $type): array
    {
        $today = BusinessDate::today();
        $schemes = $this->state->schemes($company->id, $type);
        $boundary = $this->state->boundary($company->id, $type);
        $goLive = $this->state->goLive();
        $pending = $this->state->pendingSeedBatches($company->id, $type);

        return [
            'today' => $today,
            'schemes' => array_map(fn (NumberingScheme $scheme): array => [
                'id' => $scheme->id,
                'granularity' => $scheme->period_granularity,
                'template' => $scheme->format_template,
                'revision_format' => $scheme->revision_format,
                'effective_from' => $scheme->effective_from->toDateString(),
                'lock_version' => $scheme->lock_version,
                'in_force' => $scheme->effective_from->toDateString() <= $today,
                'example' => NumberFormat::format($scheme->format_template, NumberingPeriod::of(max($today, $scheme->effective_from->toDateString()), $scheme->period_granularity)->key, 1),
            ], $schemes),
            'counters' => $this->state->counters($company->id, $type),
            'boundary' => $boundary,
            'go_live' => $goLive,
            'start_periods' => $goLive !== null && $boundary === null && $pending === []
                ? array_map(fn (NumberingPeriod $period): string => $period->key, NumberingState::periodsOf($schemes, $today)) : [],
            'pending_batches' => $pending,
            'batches' => $this->state->batchesCovering($company->id),
        ];
    }

    private function answer(Outcome $outcome, Company $company, SequenceType $type, string $done, string $sent): RedirectResponse
    {
        $name = __($type->labelKey());
        $state = $outcome->state;
        $periode = is_scalar($state['periode'] ?? null) ? (string) $state['periode'] : '';

        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __("numbering.answer.{$done}", ['jenis' => $name])],
            Outcome::REJECTED => [null, $this->refusal($outcome, $name, $periode)],
            Outcome::CONFLICT => match (true) {
                $outcome->code === 'ST-08' => ['stale', __('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                ($state['reason'] ?? null) === 'periods' => ['stale', __('numbering.refusal.periods')],
                $outcome->code === 'ST-01' => ['stale', __('messages.msg_03')],
                default => ['recorded', __('messages.msg_04')],
            },
            default => ['not_processed', __('messages.msg_06')],
        };

        Inertia::flash('answer', ['kind' => $outcome->kind, 'cause' => $cause, 'text' => $text]);

        return redirect()->to('/pengaturan/penomoran?'.http_build_query(['perusahaan' => $company->public_id, 'jenis' => $type->slug()]), 303);
    }

    private function refusal(Outcome $outcome, string $name, string $periode): string
    {
        $state = $outcome->state;
        $reason = is_string($state['reason'] ?? null) ? $state['reason'] : null;
        $parameters = [
            'jenis' => $name,
            'periode' => $periode,
            'batch' => substr(self::text($state['batch'] ?? ''), 0, 8),
            'tanggal' => self::text($state['earliest'] ?? $state['effective_from'] ?? ''),
            'nomor' => self::text($state['next'] ?? ''),
        ];

        return match (true) {
            $reason !== null && __("numbering.refusal.{$reason}") !== "numbering.refusal.{$reason}" => __("numbering.refusal.{$reason}", $parameters),
            $outcome->code === 'C-10' => __('numbering.refusal.not_started', $parameters),
            default => __('messages.msg_04'),
        };
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules, \Closure $after): array
    {
        $validator = Validator::make($request->all(), $rules, [
            'required' => __('numbering.validation.required'),
            'date_format' => __('numbering.validation.date'),
            'date' => __('numbering.validation.instant'),
            'integer' => __('numbering.validation.first_number'),
            'min' => __('numbering.validation.first_number'),
            'max' => __('numbering.validation.first_number'),
            'restore_reference.max' => __('numbering.validation.reference'),
        ]);
        $validator->after($after);

        /** @var array<string, mixed> */
        return $validator->validate();
    }

    private function type(string $slug): SequenceType
    {
        return SequenceType::fromSlug($slug) ?? throw new NotFoundHttpException;
    }

    /** The payload's company, in the Owner's scope — every company (CS-01) —, or the not-found answer (IP-21). */
    private function company(Request $request, string $publicId): Company
    {
        $ids = $this->companies->idsOf($this->capabilities->for($this->user($request))->scope, [$publicId]);
        $company = $ids === [] ? null : Company::query()->find($ids[0]);

        return $company instanceof Company ? $company : throw new NotFoundHttpException;
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new NotFoundHttpException;
    }

    /**
     * @param  array<array-key, mixed>  $numbers
     * @return array<string, int>
     */
    private static function numbers(array $numbers): array
    {
        $result = [];

        foreach ($numbers as $period => $number) {
            $result[(string) $period] = (int) self::integer($number);
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return array<string, int>
     */
    private static function lines(array $lines): array
    {
        $result = [];

        foreach ($lines as $line) {
            if (is_array($line)) {
                $result[self::text($line['period_key'] ?? '')] = (int) self::integer($line['highest'] ?? null);
            }
        }

        return $result;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
