<?php

namespace App\Modules\ORG\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ORG\Actions\ConfigureTaxTreatment;
use App\Modules\ORG\Queries\CompanySettings;
use App\Modules\ORG\Queries\TaxConfiguration;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-062 *Pajak* (ROUTE-062, ROUTE-138; INT-138; NAV-062; SCR-56; DATABASE §12; GAP-026), the Owner's alone
 * (`tax.configure`; OD-04): tax types, effective-dated rate versions, company tax profiles and treatment rules,
 * configured values only. One form per kind adds a row or changes one named by its handle at its `lock_version`; an
 * overlapping range answers at its field (C-52), a stale edit CONFLICT (ST-01).
 */
final class TaxController extends Controller
{
    private const TABLES = ['tax_type' => 'tax_types', 'rate_version' => 'tax_rate_versions', 'profile' => 'company_tax_profiles', 'rule' => 'tax_treatment_rules'];

    public function __construct(
        private readonly TaxConfiguration $tax,
        private readonly CompanySettings $companies,
    ) {}

    /** `ORG ShowTax` (ROUTE-062). */
    public function show(Request $request): HttpResponse
    {
        $tab = $request->query('bagian');

        return Inertia::render('ORG/Tax', [...$this->tax->page(self::actor($request)), 'section' => is_string($tab) && in_array($tab, array_keys(self::TABLES), true) ? $tab : 'tax_type'])->toResponse($request);
    }

    /** `ORG ConfigureTaxTreatment` (ROUTE-138). */
    public function configure(Request $request, ConfigureTaxTreatment $action): RedirectResponse
    {
        $kind = $request->input('kind');
        $range = ['effective_from' => ['required', 'date_format:Y-m-d'], 'effective_to' => ['nullable', 'date_format:Y-m-d', 'after:effective_from']];
        $rules = match ($kind) {
            'tax_type' => ['code' => ['required', 'string', 'regex:/^[A-Z0-9_]{2,30}$/'], 'name' => ['required', 'string', 'max:120'],
                'category' => ['required', Rule::in(['OUTPUT_TAX', 'INPUT_TAX', 'WITHHOLDING', 'COLLECTION', 'OTHER'])], 'active' => ['required', 'boolean']],
            'rate_version' => ['type' => ['required', 'string'], 'rate_percent' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
                'base_numerator' => ['required', 'integer', 'min:1', 'max:1000'], 'base_denominator' => ['required', 'integer', 'min:1', 'max:1000'],
                'rounding_mode' => ['required', Rule::in(['HALF_UP', 'DOWN'])], ...$range],
            'profile' => ['company' => ['required', 'uuid'], 'registration_status' => ['required', 'string', 'max:60'], ...$range],
            'rule' => ['company' => ['nullable', 'uuid'], 'context' => ['required', Rule::in(['SALES_INVOICE', 'PURCHASE_LINE', 'PAYMENT_WITHHELD', 'PAYMENT_COLLECTED', 'TAX_REMITTANCE'])],
                'type' => ['required', 'string'], 'treatment' => ['required', Rule::in(['OUTPUT_EXCLUDED_FROM_REVENUE', 'INPUT_CREDITABLE', 'INPUT_TO_ACQUISITION_COST', 'SETTLEMENT_NO_EXPENSE',
                    'SETTLEMENT_TO_EXPENSE', 'REMITTANCE_NOT_COST', 'REMITTANCE_TO_EXPENSE'])], 'evidence_required' => ['required', 'boolean'], ...$range],
            default => [],
        };
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'kind' => ['required', Rule::in(array_keys(self::TABLES))],
            'row' => ['nullable', 'string'], 'lock_version' => ['required_with:row', 'nullable', 'integer', 'min:0'], ...$rules]);
        $kind = self::text($input['kind']);
        $row = is_string($input['row'] ?? null) ? ($this->tax->row(self::TABLES[$kind], $input['row']) ?? throw new NotFoundHttpException) : null;
        $company = is_string($input['company'] ?? null) ? ($this->companies->find($input['company']) ?? throw new NotFoundHttpException) : null;
        $effective = '['.self::text($input['effective_from'] ?? '').','.self::text($input['effective_to'] ?? '').')';
        $type = fn (): int => $this->tax->typeOf(self::text($input['type'])) ?? throw ValidationException::withMessages(['type' => __('tax.validation.type')]);

        $values = match ($kind) {
            'tax_type' => ['code' => self::text($input['code']), 'name' => trim(self::text($input['name'])), 'category' => self::text($input['category']), 'is_active' => (bool) $input['active']],
            'rate_version' => ['tax_type_id' => $type(), 'rate_percent' => self::text($input['rate_percent']), 'base_numerator' => (int) self::text($input['base_numerator']),
                'base_denominator' => (int) self::text($input['base_denominator']), 'rounding_mode' => self::text($input['rounding_mode']), 'effective' => $effective],
            'profile' => ['company_id' => $company?->id, 'registration_status' => self::text($input['registration_status']), 'effective' => $effective],
            default => ['company_id' => $company?->id, 'transaction_context' => self::text($input['context']), 'tax_type_id' => $type(), 'treatment' => self::text($input['treatment']),
                'evidence_required' => (bool) $input['evidence_required'], 'effective' => $effective],
        };
        $companyId = $row !== null ? $row['company_id'] : $company?->id;
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $kind, $row['id'] ?? null, $row === null ? null : (int) self::text($input['lock_version'] ?? 0),
            $values, $companyId, $company?->public_id);

        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-52') {
            throw ValidationException::withMessages(['effective_from' => __('tax.validation.overlap')]);
        }

        if ($outcome->kind === Outcome::CONFLICT && $outcome->code === 'SQ-08') {
            throw ValidationException::withMessages(['code' => __('tax.validation.duplicate')]);
        }

        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __('tax.answer.saved')],
            Outcome::CONFLICT => $outcome->code === 'ST-01' ? ['stale', __('messages.msg_03')] : ['recorded', __('messages.msg_04')],
            Outcome::REJECTED => [null, __('messages.msg_04')],
            default => ['not_processed', __('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'cause' => $cause, 'text' => $text]);

        return redirect()->to('/pengaturan/pajak?bagian='.$kind, 303);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('tax.validation.required'),
            'max' => __('tax.validation.too_long'),
            'in' => __('tax.validation.choice'),
            'regex' => __('tax.validation.format'),
            'date_format' => __('tax.validation.date'),
            'after' => __('tax.validation.range'),
        ])->validate();

        return $valid;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
