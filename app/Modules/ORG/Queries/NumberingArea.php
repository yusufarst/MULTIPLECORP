<?php

namespace App\Modules\ORG\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Data\NumberFormat;
use App\Modules\ORG\Data\NumberingPeriod;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Modules\ORG\Models\NumberingScheme;
use App\Modules\ORG\Services\NumberingState;
use App\Support\BusinessDate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What PG-061 shows (ADMIN_FLOW §12.1; HO-39), read for the Owner's scope — every company (CS-01): per company and
 * numbered type, the type's schemes with an example number, its counters, its seed boundary, go-live, the periods a
 * start would seed today, and the import batches that cover the company, those still holding seed rows marked.
 */
final class NumberingArea
{
    public function __construct(
        private readonly NumberingState $state,
        private readonly CompanyDirectory $companies,
        private readonly EffectiveCapabilities $capabilities,
    ) {}

    /** @return array<string, mixed> */
    public function page(Authenticatable $user, ?string $companyPublicId, ?string $slug): array
    {
        $companies = $this->companies->inScope($this->capabilities->for($user)->scope);
        $selected = collect($companies)->firstWhere('id', $companyPublicId === null ? null : strtolower($companyPublicId)) ?? ($companies[0] ?? null);
        $type = ($slug === null ? null : SequenceType::fromSlug($slug)) ?? SequenceType::Doc01;
        $company = $selected === null ? null : Company::query()->where('public_id', $selected['id'])->first();

        return [
            'companies' => $companies,
            'company' => $selected['id'] ?? null,
            'types' => array_map(fn (SequenceType $case): array => ['slug' => $case->slug(), 'code' => $case->value, 'label' => $case->labelKey()], SequenceType::cases()),
            'type' => $type->slug(),
            'detail' => $company === null ? null : $this->detail($company, $type),
        ];
    }

    /** The payload's company, in the account's scope, or null — answered as not found (IP-21). */
    public function company(Authenticatable $user, string $publicId): ?Company
    {
        $ids = $this->companies->idsOf($this->capabilities->for($user)->scope, [$publicId]);

        return $ids === [] ? null : Company::query()->find($ids[0]);
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
}
