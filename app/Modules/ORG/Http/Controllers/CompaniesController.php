<?php

namespace App\Modules\ORG\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireStepUp;
use App\Modules\ORG\Actions\CreateCompany;
use App\Modules\ORG\Actions\DeactivateCompany;
use App\Modules\ORG\Actions\ManageBankAccount;
use App\Modules\ORG\Actions\ReplaceIdentityAsset;
use App\Modules\ORG\Actions\UpdateCompany;
use App\Modules\ORG\Data\CompanyFields;
use App\Modules\ORG\Queries\CompanySettings;
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
 * PG-059 *Perusahaan* (ROUTE-059, ROUTE-131–ROUTE-135; INT-131–INT-135; NAV-059; SCR-54; J-MD-01), the Owner's alone
 * (`companies.manage`; OD-03): the companies and one company's sections — its legal data, identity assets, bank
 * accounts and state — with the readiness list derived from what exists. The company and the section are whitelisted
 * address keys, `perusahaan` and `bagian` (NV-05); each NX-01 command carries its `command_id`, its answer in place as
 * flash data (IP-01) and its field errors at their fields (IP-14). The step-up guards the bank accounts and the
 * identity assets on their routes, and a legal-data change only when it touches an identity asset (AU-15; ROUTE-132).
 */
final class CompaniesController extends Controller
{
    public const SECTIONS = ['data', 'aset', 'rekening', 'status'];

    public function __construct(private readonly CompanySettings $companies) {}

    /** `ORG ShowCompanies` (ROUTE-059). */
    public function show(Request $request): HttpResponse
    {
        $selected = $request->query('perusahaan');
        $company = is_string($selected) ? $this->companies->find($selected) : null;
        $section = $request->query('bagian');

        return Inertia::render('ORG/Companies', [
            'companies' => $this->companies->directory(),
            'company' => $company === null ? null : $this->companies->detail($company),
            'section' => is_string($section) && in_array($section, self::SECTIONS, true) ? $section : 'data',
        ])->toResponse($request);
    }

    /** `ORG CreateCompany` (ROUTE-131). */
    public function store(Request $request, CreateCompany $action): RedirectResponse
    {
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'code' => ['required', 'string', 'regex:/^[A-Z0-9]{2,12}$/'], ...self::legalRules()]);
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), ['code' => self::text($input['code']), ...self::legal($input)]);
        $this->fieldRefusal($outcome);
        $created = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null ? $this->companies->publicIdOf($outcome->resultId) : null;

        return $this->answer($outcome, $created, 'data', 'companies.answer.created');
    }

    /** `ORG UpdateCompany` (ROUTE-132): the step-up only when an identity asset changes (AU-15). */
    public function update(Request $request, string $id, UpdateCompany $action): HttpResponse
    {
        $company = $this->companies->find($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'lock_version' => ['required', 'integer', 'min:0'], ...self::legalRules()]);
        $fields = self::legal($input);

        foreach (CompanyFields::IDENTITY as $field) {
            if (($company->{$field} ?? null) !== $fields[$field] && ! RequireStepUp::recent($request)) {
                return RequireStepUp::ask();
            }
        }

        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $company, (int) self::text($input['lock_version']), $fields);
        $this->fieldRefusal($outcome);

        return $this->answer($outcome, $company->public_id, 'data', 'companies.answer.updated');
    }

    /** `ORG DeactivateCompany` (ROUTE-133). */
    public function deactivate(Request $request, string $id, DeactivateCompany $action): RedirectResponse
    {
        $company = $this->companies->find($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:500']]);
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $company, (int) self::text($input['lock_version']), self::text($input['reason']));

        return $this->answer($outcome, $company->public_id, 'status', 'companies.answer.deactivated');
    }

    /** `ORG AddBankAccount · ChangeBankAccount` (ROUTE-134), and an account's deactivation. */
    public function accounts(Request $request, string $id, ManageBankAccount $action): RedirectResponse
    {
        $company = $this->companies->find($id) ?? throw new NotFoundHttpException;
        $mode = $request->input('action');
        $details = ['bank_name' => ['required', 'string', 'max:120'], 'account_number' => ['required', 'string', 'regex:/^[0-9 .-]{4,40}$/'], 'account_holder' => ['required', 'string', 'max:200'], 'branch' => ['nullable', 'string', 'max:120']];
        $existing = ['account' => ['required', 'string'], 'lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'max:500']];
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'action' => ['required', Rule::in(['add', 'change', 'deactivate'])],
            ...($mode === 'deactivate' ? [] : $details), ...($mode === 'add' ? [] : $existing)]);
        $commandId = self::text($input['command_id']);
        $actor = self::actor($request);
        $account = $mode === 'add' ? 0 : ($this->companies->accountOf($company, self::text($input['account'])) ?? throw new NotFoundHttpException);

        $outcome = match ($mode) {
            'add' => $action->add($commandId, $actor, $company, self::details($input)),
            'change' => $action->change($commandId, $actor, $company, $account, (int) self::text($input['lock_version']), self::details($input), self::text($input['reason'])),
            default => $action->deactivate($commandId, $actor, $company, $account, (int) self::text($input['lock_version']), self::text($input['reason'])),
        };
        $this->fieldRefusal($outcome);

        return $this->answer($outcome, $company->public_id, 'rekening', 'companies.answer.account_'.self::text($mode));
    }

    /** `ORG ReplaceIdentityAsset` (ROUTE-135): the file uploaded first by ROUTE-228. */
    public function asset(Request $request, string $id, ReplaceIdentityAsset $action): RedirectResponse
    {
        $company = $this->companies->find($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'asset' => ['required', Rule::in(array_keys(ReplaceIdentityAsset::ASSETS))],
            'file' => ['required', 'uuid'], 'lock_version' => ['required', 'integer', 'min:0']]);
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $company, self::text($input['asset']), self::text($input['file']), (int) self::text($input['lock_version']));

        return $this->answer($outcome, $company->public_id, 'aset', 'companies.answer.asset');
    }

    /** @return array<string, list<mixed>> */
    private static function legalRules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:200'],
            'legal_form' => ['nullable', 'string', 'max:60'],
            'npwp' => ['nullable', 'string', 'regex:/^[0-9 .\-]{15,25}$/'],
            'address_lines' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9 +()\-]{6,30}$/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'director_name' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    private static function legal(array $input): array
    {
        $fields = [];

        foreach (CompanyFields::LEGAL as $field) {
            $fields[$field] = self::optional($input[$field] ?? null);
        }

        $fields['legal_name'] = trim(self::text($input['legal_name'] ?? ''));

        return $fields;
    }

    /**
     * A bank account's details as the form sent them.
     *
     * @param  array<string, mixed>  $input
     * @return array{bank_name: string, account_number: string, account_holder: string, branch: ?string}
     */
    private static function details(array $input): array
    {
        return ['bank_name' => trim(self::text($input['bank_name'] ?? '')), 'account_number' => trim(self::text($input['account_number'] ?? '')),
            'account_holder' => trim(self::text($input['account_holder'] ?? '')), 'branch' => self::optional($input['branch'] ?? null)];
    }

    /** A duplicate legal identity or bank account at its field (ROUTE-131; IP-14). */
    private function fieldRefusal(Outcome $outcome): void
    {
        $field = $outcome->state['field'] ?? null;

        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'BD-16' && is_string($field)) {
            throw ValidationException::withMessages([$field => __('companies.validation.duplicate')]);
        }

        if ($outcome->kind === Outcome::CONFLICT && $outcome->code === 'SQ-08') {
            throw ValidationException::withMessages(['code' => __('companies.validation.duplicate')]);
        }
    }

    /** The answer in place (IP-01; IG-02), then the company at its section. */
    private function answer(Outcome $outcome, ?string $company, string $section, string $done): RedirectResponse
    {
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __($done)],
            Outcome::REJECTED => [null, $this->refusal($outcome)],
            Outcome::CONFLICT => $outcome->code === 'ST-01' ? ['stale', __('messages.msg_03')] : ['recorded', __('messages.msg_04')],
            default => ['not_processed', __('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'cause' => $cause, 'text' => $text]);

        return redirect()->to('/pengaturan/perusahaan?'.http_build_query(array_filter(['perusahaan' => $company, 'bagian' => $company === null ? null : $section])), 303);
    }

    private function refusal(Outcome $outcome): string
    {
        $reason = $outcome->state['reason'] ?? null;
        $key = 'companies.refusal.'.strtolower(str_replace('-', '_', (string) $outcome->code)).(is_string($reason) ? '_'.$reason : '');

        return __($key) !== $key ? __($key) : __('messages.msg_04');
    }

    /**
     * Validates the named fields only (WS-01), each message from the catalogue.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('companies.validation.required'),
            'max' => __('companies.validation.too_long'),
            'in' => __('companies.validation.choice'),
            'email' => __('companies.validation.email'),
            'regex' => __('companies.validation.format'),
        ])->validate();

        return $valid;
    }

    private static function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
