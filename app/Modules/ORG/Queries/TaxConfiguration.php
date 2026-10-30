<?php

namespace App\Modules\ORG\Queries;

use App\Modules\IAM\Services\EffectiveCapabilities;
use App\Modules\ORG\Data\TreatmentPrecedence;
use App\Support\RowHandle;
use Illuminate\Support\Facades\DB;

/**
 * PG-062 *Pajak*'s reads (ROUTE-062; SCR-56; DATABASE §12; PERMISSIONS_MATRIX §7.2 rows 13–16) and the treatment a
 * transaction resolves for its company, context, tax type and business date (BR-FIN-17; DIR-024 D-1): tax types, rate
 * versions and global rules read by every account; a company's profiles and rules with `finance.view` or `cost.view`
 * in its scope; each row named by its handle, its effective range by its first and its day after the last.
 */
final class TaxConfiguration
{
    public function __construct(private readonly EffectiveCapabilities $capabilities) {}

    /** @return array<string, mixed> */
    public function page(int $viewerId): array
    {
        $viewer = $this->capabilities->for($viewerId);
        $companies = DB::table('companies')->orderBy('code')->get(['id', 'public_id', 'code', 'legal_name']);
        $codes = $companies->pluck('code', 'id')->all();
        $sees = fn (?int $companyId): bool => $companyId === null || $viewer->allows('finance.view', $companyId) || $viewer->allows('cost.view', $companyId);
        $range = 'lower(effective) as effective_from, upper(effective) as effective_to';

        return [
            'types' => DB::table('tax_types')->orderBy('code')->get()->map(fn (object $row): array => [
                'handle' => RowHandle::of('tax_types', self::int($row->id)), 'code' => self::text($row->code), 'name' => self::text($row->name),
                'category' => self::text($row->category), 'active' => $row->is_active === true, 'lock_version' => self::int($row->lock_version),
            ])->values()->all(),
            'rates' => DB::table('tax_rate_versions as r')->join('tax_types as t', 't.id', '=', 'r.tax_type_id')->orderBy('t.code')->orderByRaw('lower(r.effective)')
                ->selectRaw('r.id, t.code as type, r.rate_percent, r.base_numerator, r.base_denominator, r.rounding_mode, r.lock_version, lower(r.effective) as effective_from, upper(r.effective) as effective_to')
                ->get()->map(fn (object $row): array => [
                    'handle' => RowHandle::of('tax_rate_versions', self::int($row->id)), 'type' => self::text($row->type), 'rate_percent' => self::text($row->rate_percent),
                    'base_numerator' => self::int($row->base_numerator), 'base_denominator' => self::int($row->base_denominator), 'rounding_mode' => self::text($row->rounding_mode),
                    'effective_from' => self::day($row->effective_from), 'effective_to' => self::day($row->effective_to), 'lock_version' => self::int($row->lock_version),
                ])->values()->all(),
            'profiles' => DB::table('company_tax_profiles')->selectRaw("id, company_id, registration_status, lock_version, {$range}")->orderBy('company_id')->orderByRaw('lower(effective)')->get()
                ->filter(fn (object $row): bool => $sees(self::int($row->company_id)))
                ->map(fn (object $row): array => [
                    'handle' => RowHandle::of('company_tax_profiles', self::int($row->id)), 'company' => self::text($codes[self::int($row->company_id)] ?? ''),
                    'registration_status' => self::text($row->registration_status), 'effective_from' => self::day($row->effective_from), 'effective_to' => self::day($row->effective_to),
                    'lock_version' => self::int($row->lock_version),
                ])->values()->all(),
            'rules' => DB::table('tax_treatment_rules as r')->join('tax_types as t', 't.id', '=', 'r.tax_type_id')
                ->selectRaw('r.id, r.company_id, r.transaction_context, t.code as type, r.treatment, r.evidence_required, r.lock_version, lower(r.effective) as effective_from, upper(r.effective) as effective_to')
                ->orderByRaw('r.company_id nulls first')->orderBy('r.transaction_context')->orderBy('t.code')->orderByRaw('lower(r.effective)')->get()
                ->filter(fn (object $row): bool => $sees(is_numeric($row->company_id) ? (int) $row->company_id : null))
                ->map(fn (object $row): array => [
                    'handle' => RowHandle::of('tax_treatment_rules', self::int($row->id)),
                    'company' => is_numeric($row->company_id) ? self::text($codes[(int) $row->company_id] ?? '') : null,
                    'context' => self::text($row->transaction_context), 'type' => self::text($row->type), 'treatment' => self::text($row->treatment),
                    'evidence_required' => $row->evidence_required === true, 'effective_from' => self::day($row->effective_from), 'effective_to' => self::day($row->effective_to),
                    'lock_version' => self::int($row->lock_version),
                ])->values()->all(),
            'companies' => $companies->map(fn (object $row): array => ['id' => self::text($row->public_id), 'code' => self::text($row->code), 'name' => self::text($row->legal_name)])->values()->all(),
        ];
    }

    /**
     * A configuration row by its handle in its table, with its company and `lock_version`.
     *
     * @return array{id: int, company_id: int|null, lock_version: int}|null
     */
    public function row(string $table, string $handle): ?array
    {
        if (! RowHandle::valid($handle) || ! in_array($table, ['tax_types', 'tax_rate_versions', 'company_tax_profiles', 'tax_treatment_rules'], true)) {
            return null;
        }

        foreach (DB::table($table)->get() as $row) {
            $values = (array) $row;
            $id = self::int($values['id'] ?? null);

            if (hash_equals(RowHandle::of($table, $id), $handle)) {
                return ['id' => $id, 'company_id' => is_numeric($values['company_id'] ?? null) ? (int) $values['company_id'] : null, 'lock_version' => self::int($values['lock_version'] ?? null)];
            }
        }

        return null;
    }

    /** A tax type's identifier by its code. */
    public function typeOf(string $code): ?int
    {
        $id = DB::table('tax_types')->where('code', $code)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The treatment a transaction of the company resolves on its business date — the company's rule over the global
     * one —, or none configured (FS-13).
     *
     * @return array{company_id: int|null, treatment: string, evidence_required: bool}|null
     */
    public function treatment(int $companyId, string $context, int $taxTypeId, string $day): ?array
    {
        $rules = DB::table('tax_treatment_rules')->where('transaction_context', $context)->where('tax_type_id', $taxTypeId)
            ->whereRaw('effective @> ?::date', [$day])->whereRaw('(company_id is null or company_id = ?)', [$companyId])
            ->get(['company_id', 'treatment', 'evidence_required'])
            ->map(fn (object $row): array => ['company_id' => is_numeric($row->company_id) ? (int) $row->company_id : null, 'treatment' => self::text($row->treatment), 'evidence_required' => $row->evidence_required === true])
            ->values()->all();

        return TreatmentPrecedence::choose(array_values($rules), $companyId);
    }

    private static function day(mixed $value): ?string
    {
        return is_string($value) ? substr($value, 0, 10) : null;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
