<?php

namespace App\Http\Controllers;

use App\Modules\IAM\Actions\CreateUser;
use App\Modules\IAM\Actions\DeactivateUser;
use App\Modules\IAM\Actions\EndUserSession;
use App\Modules\IAM\Actions\GrantAccess;
use App\Modules\IAM\Actions\IssueCredentialLink;
use App\Modules\IAM\Actions\ReactivateUser;
use App\Modules\IAM\Actions\ResetTotp;
use App\Modules\IAM\Actions\RevokeCredentialLink;
use App\Modules\IAM\Actions\UnlinkGoogleIdentity;
use App\Modules\IAM\Actions\UpdateUser;
use App\Modules\IAM\Data\CapabilityAreas;
use App\Modules\IAM\Data\SessionHandle;
use App\Modules\IAM\Queries\UsersAndAccess;
use App\Modules\ORG\Queries\CompanyDirectory;
use App\Support\Command\Outcome;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-060 *Pengguna & Akses* (ROUTE-060, ROUTE-121–ROUTE-128, ROUTE-130; INT-121–INT-128, INT-130; NAV-060; SCR-57;
 * J-ACC-02; TASK-024), the Owner's alone (`users.manage`, `permissions.manage`; OD-01, OD-02): the directory and one
 * account's detail — the account a whitelisted address key, `akun`, and its open section another, `bagian` (IA §11;
 * NV-05), a reading within the approved text since no route views one account —; each NX-02 command carrying its
 * `command_id` behind the step-up (AU-15), its answer in place as flash data the next page reads once (IP-01), a
 * credential link shown once (AU-12) and its field errors at their fields (IP-14). The companies are ORG's
 * directory's, named here by public identifier; the page never receives an internal identifier.
 */
final class UsersAndAccessController extends Controller
{
    public const SECTIONS = ['profil', 'akses', 'sesi', 'tautan', 'keamanan'];

    public function __construct(
        private readonly UsersAndAccess $users,
        private readonly CompanyDirectory $companies,
    ) {}

    /** `IAM ShowUsersAndAccess` (ROUTE-060). */
    public function show(Request $request): HttpResponse
    {
        $companies = $this->companies->grantable();
        $selected = $request->query('akun');
        $account = is_string($selected) ? $this->users->account($selected) : null;
        $section = $request->query('bagian');
        $detail = null;
        $preview = null;

        if ($account !== null) {
            $detail = $this->users->detail($account, self::actor($request), $request->session()->getId());
            $held = array_map(fn (int $id): array => $companies[$id] ?? ['id' => '', 'code' => '?', 'name' => '', 'active' => false], $this->users->companyIdsOf($account));
            $detail['companies'] = $held;
            $role = $this->users->roleOf($account);
            // "Pratinjau Akses" (PT-30): computed on the server for the editor's state, on its own partial reload.
            $preview = Inertia::optional(function () use ($request, $account, $held, $role, $companies): array {
                [$asked, $capabilities, $chosen] = $this->desired($request, $role, $companies);

                return $this->users->preview($account, array_column($held, 'id'), $asked, $capabilities, $chosen);
            });
        }

        return Inertia::render('IAM/UsersAndAccess', [
            'accounts' => $this->users->directory(is_numeric($request->query('batas')) ? (int) $request->query('batas') : UsersAndAccess::PAGE),
            'account' => $detail,
            'section' => is_string($section) && in_array($section, self::SECTIONS, true) ? $section : 'profil',
            'catalogue' => $this->users->catalogue(),
            'companies' => array_values($companies),
            'preview' => $preview,
        ])->toResponse($request);
    }

    /** `IAM CreateUser` (ROUTE-121). */
    public function store(Request $request, CreateUser $action): RedirectResponse
    {
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'string', 'email:rfc', 'max:254']]);
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), self::text($input['name']), self::text($input['email']));

        if (self::duplicate($outcome)) {
            throw ValidationException::withMessages(['email' => __('users.validation.email_taken', ['nilai' => CreateUser::normalized(self::text($input['email']))])]);
        }

        $created = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null ? $this->users->publicIdOf($outcome->resultId) : null;

        return $this->answer($outcome, $created, 'profil', 'users.answer.created');
    }

    /** `IAM UpdateUser` (ROUTE-122). */
    public function update(Request $request, string $id, UpdateUser $action): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'string', 'email:rfc', 'max:254'], 'lock_version' => ['required', 'integer', 'min:0']]);
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $account, self::text($input['name']), self::text($input['email']), self::integer($input['lock_version']));

        if (self::duplicate($outcome)) {
            throw ValidationException::withMessages(['email' => __('users.validation.email_taken', ['nilai' => CreateUser::normalized(self::text($input['email']))])]);
        }

        return $this->answer($outcome, $id, 'profil', 'users.answer.updated');
    }

    /** `IAM DeactivateUser · ReactivateUser` (ROUTE-123). */
    public function status(Request $request, string $id, DeactivateUser $deactivate, ReactivateUser $reactivate): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'active' => ['required', 'boolean']]);

        if ((bool) $input['active']) {
            $outcome = $reactivate->run(self::text($input['command_id']), self::actor($request), $account);
            $this->flashLink($outcome, $reactivate->issued());

            return $this->answer($outcome, $id, 'profil', 'users.answer.activated');
        }

        return $this->answer($deactivate->run(self::text($input['command_id']), self::actor($request), $account), $id, 'profil', 'users.answer.deactivated');
    }

    /** `IAM GrantRole · GrantCompany · GrantCapability` (ROUTE-124), "Simpan Akses" after "Pratinjau Akses". */
    public function access(Request $request, string $id, GrantAccess $action): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, [
            'command_id' => ['required', 'uuid'],
            'role' => ['required', Rule::in(UsersAndAccess::ROLES)],
            'companies' => ['present', 'array', 'max:200'],
            'companies.*' => ['string', 'uuid'],
            'capabilities' => ['present', 'array', 'max:100'],
            'capabilities.*' => ['string', Rule::in(array_merge(...array_values(CapabilityAreas::grantable())))],
            'lock_version' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $companies = $this->companyIds(self::strings($input['companies']));
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $account, self::text($input['role']), $companies,
            self::strings($input['capabilities']), self::integer($input['lock_version']), isset($input['reason']) ? self::text($input['reason']) : null);

        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'RG-08') {
            throw ValidationException::withMessages(['reason' => __('users.validation.reason_special')]);
        }

        $this->flashLink($outcome, $action->issued());

        return $this->answer($outcome, $id, 'akses', 'users.answer.access');
    }

    /** `IAM IssueCredentialLink` (ROUTE-125), "Buat Tautan Atur Kata Sandi". */
    public function link(Request $request, string $id, IssueCredentialLink $action): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid']]);
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $account);
        $this->flashLink($outcome, $action->issued());

        return $this->answer($outcome, $id, 'tautan', 'users.answer.link');
    }

    /** `IAM RevokeCredentialLink` (ROUTE-126). */
    public function revokeLink(Request $request, string $id, RevokeCredentialLink $action): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'purpose' => ['required', Rule::in(['ONBOARDING', 'RESET'])]]);

        return $this->answer($action->run(self::text($input['command_id']), self::actor($request), $account, self::text($input['purpose'])), $id, 'tautan', 'users.answer.link_revoked');
    }

    /** `IAM ResetTotp` (ROUTE-127). */
    public function reset(Request $request, string $id, ResetTotp $action): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500']]);
        $outcome = $action->run(self::text($input['command_id']), self::actor($request), $account, self::text($input['reason']));
        $this->flashLink($outcome, $action->issued());

        return $this->answer($outcome, $id, 'keamanan', 'users.answer.reset');
    }

    /** `IAM UnlinkGoogleIdentity` (ROUTE-128). */
    public function unlink(Request $request, string $id, UnlinkGoogleIdentity $action): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;
        $input = $this->validated($request, ['command_id' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500']]);

        return $this->answer($action->run(self::text($input['command_id']), self::actor($request), $account, self::text($input['reason'])), $id, 'keamanan', 'users.answer.unlinked');
    }

    /** `IAM EndUserSession` (ROUTE-130), "Akhiri Sesi". */
    public function endSession(Request $request, string $id, string $sesi, EndUserSession $action): RedirectResponse
    {
        $account = $this->users->account($id) ?? throw new NotFoundHttpException;

        if (! SessionHandle::valid($sesi)) {
            throw new NotFoundHttpException;
        }

        $input = $this->validated($request, ['command_id' => ['required', 'uuid']]);

        return $this->answer($action->run(self::text($input['command_id']), self::actor($request), $account, $sesi, $request->session()->getId()), $id, 'sesi', 'users.answer.session_ended');
    }

    /**
     * The editor's state the preview's partial reload names: `peran`, `kewenangan[]` and `perusahaan[]`, each value
     * an enumeration or a public identifier (IA §11), anything else dropped.
     *
     * @param  array<int, array{id: string, code: string, name: string, active: bool}>  $companies
     * @return array{0: string, 1: list<string>, 2: list<string>}
     */
    private function desired(Request $request, ?string $currentRole, array $companies): array
    {
        $role = $request->query('peran');
        $codes = array_merge(...array_values(CapabilityAreas::grantable()));
        $capabilities = array_values(array_intersect(self::strings($request->query('kewenangan')), $codes));
        $chosen = array_values(array_intersect(self::strings($request->query('perusahaan')), array_column($companies, 'id')));

        return [is_string($role) ? $role : (string) $currentRole, $capabilities, $chosen];
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }

    /**
     * A credential link the command issued, shown once as flash data — never a page prop kept in history —, built from
     * the configured application URL with its token in the fragment (AU-12; WS-11); never on a replay (CI-05).
     *
     * @param  array{token: string, id: int, expires_at: CarbonImmutable, purpose: string}|null  $issued
     */
    private function flashLink(Outcome $outcome, ?array $issued): void
    {
        if ($issued === null || $outcome->kind !== Outcome::COMMITTED || $outcome->replayed) {
            return;
        }

        $base = config('app.url');
        Inertia::flash('credential_link', [
            'url' => rtrim(is_string($base) ? $base : '', '/').'/atur-kata-sandi#'.$issued['token'],
            'purpose' => $issued['purpose'],
            'expires_at' => $issued['expires_at']->toIso8601ZuluString(),
        ]);
    }

    /** The answer in place (IP-01; IG-02), then the account's detail at its section. */
    private function answer(Outcome $outcome, ?string $account, string $section, string $done): RedirectResponse
    {
        $state = $outcome->state;

        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __($outcome->replayed && $outcome->resultType === 'credential_link' ? 'users.answer.link_replayed' : $done)],
            Outcome::REJECTED => [null, $this->refusal($outcome)],
            Outcome::CONFLICT => match (true) {
                $outcome->code === 'CI-05' => ['recorded', __('messages.msg_04')],
                $outcome->code === 'ST-01' => ['stale', __('messages.msg_03')],
                default => ['recorded', __('messages.msg_04')],
            },
            default => ['not_processed', __('messages.msg_06')],
        };

        Inertia::flash('answer', ['kind' => $outcome->kind, 'cause' => $cause, 'text' => $text, 'state' => array_intersect_key($state, array_flip(['link', 'purpose', 'expires_at', 'lock_version']))]);

        return redirect()->to('/pengaturan/pengguna?'.http_build_query(array_filter(['akun' => $account, 'bagian' => $account === null ? null : $section])), 303);
    }

    private function refusal(Outcome $outcome): string
    {
        $reason = $outcome->state['reason'] ?? null;
        $key = 'users.refusal.'.strtolower(str_replace('-', '_', (string) $outcome->code)).(is_string($reason) ? '_'.$reason : '');

        return __($key) !== $key ? __($key) : __('messages.msg_04');
    }

    private static function duplicate(Outcome $outcome): bool
    {
        return ($outcome->kind === Outcome::REJECTED && $outcome->code === 'AU-01') || ($outcome->kind === Outcome::CONFLICT && $outcome->code === 'SQ-08');
    }

    /**
     * The companies' internal identifiers, every one named required to exist (C-32).
     *
     * @param  list<string>  $publicIds
     * @return list<int>
     */
    private function companyIds(array $publicIds): array
    {
        $ids = [];

        foreach ($this->companies->grantable() as $id => $company) {
            if (in_array($company['id'], $publicIds, true)) {
                $ids[] = $id;
            }
        }

        if (count($ids) !== count(array_unique($publicIds))) {
            throw ValidationException::withMessages(['companies' => __('users.validation.company')]);
        }

        return $ids;
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
            'required' => __('users.validation.required'),
            'email' => __('users.validation.email'),
            'max' => __('users.validation.too_long'),
            'in' => __('users.validation.choice'),
        ])->validate();

        return $valid;
    }

    private static function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : throw new NotFoundHttpException;
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
