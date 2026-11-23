<?php

namespace App\Modules\DOC\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Actions\AddEvidenceVersion;
use App\Modules\DOC\Actions\LinkEvidence;
use App\Modules\DOC\Actions\RegisterEvidence;
use App\Modules\DOC\Actions\ReplaceEvidence;
use App\Modules\DOC\Actions\StoreUpload;
use App\Modules\DOC\Actions\UnlinkEvidence;
use App\Modules\DOC\Data\EvidenceIdentity;
use App\Modules\DOC\Data\EvidenceTypes;
use App\Modules\DOC\Data\LinkHandle;
use App\Modules\DOC\Data\StoredUpload;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Queries\EvidenceList;
use App\Support\Command\Outcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PG-038 *Bukti* (ROUTE-038; NAV-038; SCR-36; J-DOC-02) and the evidence's routes: `DOC StoreUpload` (ROUTE-228), the
 * one upload of every upload field, answering the file's id or MSG-31 at the field or MSG-20 past WS-12's bounds;
 * `DOC RegisterEvidence` (ROUTE-229), `DOC AddEvidenceVersion` (ROUTE-230) and `DOC LinkEvidence · UnlinkEvidence ·
 * ReplaceEvidence` (ROUTE-231), each carrying its `command_id` with its answer in place as flash data (IP-01) and its
 * field errors at their fields (IP-14). The branch, the cursor and the open evidence are whitelisted address keys —
 * `cabang`, `setelah`, `bukti` — (NV-05); the page never receives an internal identifier.
 */
final class EvidenceController extends Controller
{
    /** The address names of ROUTE-228's purposes. */
    private const PURPOSES = [
        'bukti' => FilePurpose::Upload,
        'aset-identitas' => FilePurpose::IdentityAsset,
        'gambar-produk' => FilePurpose::ProductImage,
        'sumber-impor' => FilePurpose::ImportSource,
        'arsip' => FilePurpose::Archive,
    ];

    /** The pages whose form keeps its input across an evidence upload, `{id}` a record's exact public id (IP-16; NV-04). */
    private const RETURN_PAGES = '#\A/(?:keuangan/(?:pembayaran(?:/baru)?|mutasi-bank|potongan|kas-lain|kredit-pelanggan)'
        .'|gudang/(?:(?:barang-masuk|barang-keluar|pengiriman)/{id}|penyesuaian|stok-opname|selisih-temuan)'
        .'|pembelian/(?:baru|{id})|proyek/{id}/(?:kirim-langsung|serah-terima|retur))\z#';

    /** A return address's length bound, past the longest cursor a host's address carries. */
    private const RETURN_BOUND = 2048;

    public function __construct(private readonly EvidenceList $evidence) {}

    /** `DOC ShowEvidence` (ROUTE-038). */
    public function show(Request $request): HttpResponse
    {
        $query = fn (string $key): ?string => is_string($request->query($key)) ? $request->query($key) : null;

        return Inertia::render('DOC/Evidence', $this->evidence->screen(self::actor($request), $query('cabang'), $query('setelah'), $query('bukti')))->toResponse($request);
    }

    /** `DOC StoreUpload` (ROUTE-228; INT-013, INT-228). */
    public function upload(Request $request, StoreUpload $action): JsonResponse
    {
        $input = $this->validated($request, [
            'purpose' => ['required', Rule::in(array_keys(self::PURPOSES))],
            'company' => ['nullable', 'uuid'],
            'companies' => ['array', 'max:20'],
            'companies.*' => ['uuid'],
            'pool' => ['boolean'],
            'type' => ['nullable', Rule::in(EvidenceTypes::TYPES)],
        ]);
        $purpose = self::PURPOSES[self::text($input['purpose'])];
        $file = $request->file('file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return self::refused(StoredUpload::refused($purpose));
        }

        $companies = is_string($input['company'] ?? null) ? [$input['company']] : self::strings($input['companies'] ?? []);
        $stored = $action->run(self::actor($request), (string) $file->getRealPath(), $file->getClientOriginalName(), $purpose, $companies,
            (bool) ($input['pool'] ?? false), is_string($input['type'] ?? null) ? $input['type'] : null);

        return match (true) {
            $stored->id !== null => response()->json(['id' => $stored->id, 'name' => $stored->name], 201),
            $stored->limitedMinutes !== null => response()->json(['message' => trans_choice('messages.msg_20', $stored->limitedMinutes, ['count' => $stored->limitedMinutes])], 429),
            default => self::refused($stored),
        };
    }

    /** `DOC RegisterEvidence` (ROUTE-229; INT-229; INT-015 for a warning's second submission). */
    public function register(Request $request, RegisterEvidence $action): RedirectResponse
    {
        $input = $this->validated($request, [
            'command_id' => ['required', 'uuid'],
            'file' => ['required', 'uuid'],
            'company' => ['required_unless:pool,true', 'nullable', 'uuid'],
            'pool' => ['boolean'],
            'pool_confirmed' => ['required_if:pool,true', 'boolean'],
            'type' => ['required', Rule::in(EvidenceTypes::TYPES)],
            'issuer' => ['required', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:120'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => [Rule::requiredIf(fn (): bool => EvidenceTypes::isAmountBearing(self::text($request->input('type')))), 'nullable', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'deliverer' => ['nullable', 'string', 'max:200'],
            'received' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'acknowledged' => ['array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $pool = (bool) ($input['pool'] ?? false);

        // L-45: an issuer of no letter or digit normalizes to nothing and would share one identity with every other.
        if (EvidenceIdentity::issuer(self::text($input['issuer'])) === '') {
            throw ValidationException::withMessages(['issuer' => __('evidence.validation.required')]);
        }

        if ($pool && (! in_array($input['type'], EvidenceTypes::POOL_TYPES, true) || ($input['pool_confirmed'] ?? false) !== true)) {
            throw ValidationException::withMessages(['pool_confirmed' => __('evidence.validation.pool')]);
        }

        $actor = self::actor($request);
        $companyPublicId = $pool ? null : self::text($input['company']);
        $companyId = $companyPublicId === null ? null : $this->evidence->companyOf($actor, $companyPublicId);
        $outcome = $action->run(self::text($input['command_id']), $actor, $companyId, $companyPublicId, [
            'file' => self::text($input['file']),
            'type' => self::text($input['type']),
            'issuer' => self::text($input['issuer']),
            'reference' => self::optional($input['reference'] ?? null),
            'date' => self::text($input['date']),
            'amount' => self::optional($input['amount'] ?? null),
            'deliverer' => self::optional($input['deliverer'] ?? null),
            'received' => self::optional($input['received'] ?? null),
            'acknowledged' => self::strings($input['acknowledged'] ?? []),
            'reason' => self::optional($input['reason'] ?? null),
        ]);
        $registered = $outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null ? $this->evidence->publicIdOf($outcome->resultId) : null;

        return $this->answer($outcome, $pool ? EvidenceList::POOL : $companyPublicId, $registered, 'evidence.answer.registered');
    }

    /** `DOC AddEvidenceVersion` (ROUTE-230; INT-230). */
    public function version(Request $request, string $id, AddEvidenceVersion $action): RedirectResponse
    {
        $input = $this->validated($request, [
            'command_id' => ['required', 'uuid'],
            'file' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:500'],
            'acknowledged' => ['array', 'max:50'],
            'acknowledged.*' => ['uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $actor = self::actor($request);
        $evidence = $this->evidence->forCommand($actor, $id);
        $company = $this->evidence->companyPublicIdOf($evidence->company_id);
        $outcome = $action->run(self::text($input['command_id']), $actor, $evidence, $company, [
            'file' => self::text($input['file']),
            'note' => self::optional($input['note'] ?? null),
            'acknowledged' => self::strings($input['acknowledged'] ?? []),
            'reason' => self::optional($input['reason'] ?? null),
        ]);

        return $this->answer($outcome, $company ?? EvidenceList::POOL, $evidence->public_id, 'evidence.answer.version');
    }

    /** `DOC LinkEvidence · UnlinkEvidence · ReplaceEvidence` (ROUTE-231; INT-231). */
    public function links(Request $request, string $id, LinkEvidence $link, UnlinkEvidence $unlink, ReplaceEvidence $replace): RedirectResponse
    {
        $input = $this->validated($request, [
            'command_id' => ['required', 'uuid'],
            'action' => ['required', Rule::in(['link', 'unlink', 'replace'])],
            'project' => ['nullable', 'uuid'],
            'payment' => ['nullable', 'uuid'],
            'role' => ['required_if:action,link', 'nullable', Rule::in(['PRIMARY_PROOF', 'SUPPORTING', 'CLIENT_ORIGINAL', 'REVIEWED'])],
            'link' => ['required_if:action,unlink', 'nullable', 'string'],
            'version' => ['required_if:action,replace', 'nullable', 'integer', 'min:1'],
            'reason' => ['required_if:action,unlink', 'nullable', 'string', 'max:500'],
        ]);
        $actor = self::actor($request);
        $evidence = $this->evidence->forCommand($actor, $id);
        $company = $this->evidence->companyPublicIdOf($evidence->company_id);
        $commandId = self::text($input['command_id']);

        [$outcome, $done] = match ($input['action']) {
            'link' => [is_string($input['payment'] ?? null)
                ? $link->run($commandId, $actor, $evidence, $company, 'payment_id', $this->evidence->paymentOf($actor, $input['payment'], $evidence), $input['payment'], self::text($input['role']))
                : $link->run($commandId, $actor, $evidence, $company, 'project_id',
                    $this->evidence->projectOf($actor, self::text($input['project'] ?? ''), $evidence->company_id), self::text($input['project'] ?? ''), self::text($input['role'])), 'evidence.answer.linked'],
            'unlink' => [LinkHandle::valid(self::text($input['link']))
                ? $unlink->run($commandId, $actor, $evidence, $company, self::text($input['link']), self::text($input['reason']))
                : throw new NotFoundHttpException, 'evidence.answer.unlinked'],
            default => [$replace->run($commandId, $actor, $evidence, $company, (int) self::text($input['version']), self::optional($input['reason'] ?? null)), 'evidence.answer.replaced'],
        };

        return $this->answer($outcome, $company ?? EvidenceList::POOL, $evidence->public_id, $done);
    }

    /** The answer in place (IP-01; IG-02), then the branch with the evidence open (NAV-038). */
    private function answer(Outcome $outcome, ?string $branch, ?string $evidence, string $done): RedirectResponse
    {
        $state = $outcome->state;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __($done)],
            Outcome::REJECTED => [null, $this->refusal($outcome)],
            Outcome::CONFLICT => ['recorded', __('messages.msg_04')],
            default => ['not_processed', __('messages.msg_06')],
        };

        // IP-16 inside a form: the proof registered, or refused, the form stays with its input (NV-04).
        $inPlace = self::inPlace(request()->input('kembali'));

        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text,
            'state' => [...array_intersect_key($state, array_flip(['matches', 'override', 'labels', 'evidence', 'reason'])), ...($inPlace !== null && $outcome->kind === Outcome::COMMITTED ? ['registered' => $evidence] : [])]]);

        if ($inPlace !== null) {
            return redirect()->to($inPlace, 303);
        }

        return redirect()->to('/dokumen/bukti?'.http_build_query(array_filter(['cabang' => $branch, 'bukti' => $evidence])), 303);
    }

    /**
     * The form an evidence upload returns to (IP-16; NV-04; TASK-096): one of the pages hosting the in-place upload —
     * PG-016, PG-017, PG-022, PG-024, PG-026, PG-027, PG-029, PG-031–PG-034, PG-045–PG-050 —, a record named by its exact
     * public id, its query re-serialized here from unreserved characters and escapes; anything else — another host or
     * scheme, `//`, `\`, `:`, `#`, a control character, an array, a value past the bound — is no return address, and
     * PG-038 answers.
     */
    private static function inPlace(mixed $back): ?string
    {
        if (! is_string($back) || strlen($back) > self::RETURN_BOUND) {
            return null;
        }

        [$path, $query] = array_pad(explode('?', $back, 2), 2, '');

        if (preg_match(str_replace('{id}', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}', self::RETURN_PAGES), $path) !== 1
            || preg_match('/\A[A-Za-z0-9._~%+=&*-]*\z/', $query) !== 1) {
            return null;
        }

        $pairs = [];

        foreach (array_filter(explode('&', $query), fn (string $pair): bool => $pair !== '') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $value = urldecode($value);

            if (preg_match('/\A[a-z][a-z0-9_]{0,31}\z/', $key) !== 1 || array_key_exists($key, $pairs) || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                return null;
            }

            $pairs[$key] = $value;
        }

        $address = $pairs === [] ? $path : $path.'?'.http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);

        // Re-escaping can lengthen a value (`+` as `%20`): the address sent back keeps the same bound.
        return strlen($address) > self::RETURN_BOUND ? null : $address;
    }

    private function refusal(Outcome $outcome): string
    {
        $reason = $outcome->state['reason'] ?? null;
        $key = 'evidence.refusal.'.strtolower(str_replace('-', '_', (string) $outcome->code)).(is_string($reason) ? '_'.$reason : '');

        return __($key) !== $key ? __($key) : __('messages.msg_04');
    }

    /** MSG-31 at the field: the purpose's accepted types and size, nothing else sent (FL-02). */
    private static function refused(StoredUpload $refusal): JsonResponse
    {
        return response()->json(['message' => __('messages.msg_31', ['jenis' => $refusal->acceptedTypes(), 'ukuran' => $refusal->sizeLimit()])], 422);
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
            'required' => __('evidence.validation.required'),
            'required_if' => __('evidence.validation.required'),
            'required_unless' => __('evidence.validation.required'),
            'max' => __('evidence.validation.too_long'),
            'in' => __('evidence.validation.choice'),
            'date_format' => __('evidence.validation.date'),
            'before_or_equal' => __('evidence.validation.future'),
            'regex' => __('evidence.validation.amount'),
        ])->validate();

        return $valid;
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }

    private static function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
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
