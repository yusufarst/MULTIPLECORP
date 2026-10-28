<?php

namespace App\Modules\DOC\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Actions\IssueDocument;
use App\Modules\DOC\Actions\ReviseDocument;
use App\Modules\DOC\Actions\SaveDocumentDraft;
use App\Modules\DOC\Actions\VoidDocument;
use App\Modules\DOC\Data\DocumentSubject;
use App\Modules\DOC\Http\FileResponses;
use App\Modules\DOC\Queries\DocumentDetail;
use App\Modules\DOC\Queries\DocumentList;
use App\Modules\DOC\Queries\FileDownloads;
use App\Support\BusinessDate;
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
 * PG-035 *Dokumen* and PG-036 *Detail Dokumen* (ROUTE-035, ROUTE-036; NAV-035, NAV-036) with the document engine's
 * commands — `DOC SaveDocumentDraft` (ROUTE-221), `DOC IssueDocument` (ROUTE-222), `DOC ReviseDocument` (ROUTE-223),
 * `DOC VoidDocument` (ROUTE-224) — and `DOC ShowRendition` (ROUTE-225). Each command carries its `command_id` (CI-01),
 * its answer in place as flash data on PG-036 (IP-01; NAV-036); validation at the fields (IP-14; MSG-12), a refusal
 * named (IP-06), a stale draft a CONFLICT with the input kept (IP-03). An unknown document and one the viewer may not
 * see answer alike (AZ-11).
 */
final class DocumentsController extends Controller
{
    public function __construct(private readonly DocumentList $documents, private readonly DocumentDetail $detail) {}

    public function index(Request $request): HttpResponse
    {
        return Inertia::render('DOC/Documents', $this->documents->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'nomor'), self::query($request, 'setelah')))->toResponse($request);
    }

    public function show(Request $request, string $id): HttpResponse
    {
        $actor = self::actor($request);

        return Inertia::render('DOC/Document', $this->detail->of($actor, $this->documents->find($actor, $id)))->toResponse($request);
    }

    /** ROUTE-221: a draft prepared from its record's facts, or the open draft's entered fields saved. */
    public function save(Request $request, SaveDocumentDraft $drafts): RedirectResponse
    {
        $actor = self::actor($request);

        if ($request->filled('dokumen')) {
            $input = self::validated($request, [...self::entered(), 'command_id' => ['required', 'uuid'], 'dokumen' => ['required', 'string'], 'draf' => ['required', 'uuid'], 'lock_version' => ['required', 'integer', 'min:0']]);
            $document = $this->documents->find($actor, self::text($input['dokumen']));
            $outcome = $drafts->save(self::text($input['command_id']), $actor, $document, self::integer($input['lock_version']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::nullable($input['rekening'] ?? null), self::text($input['draf']));

            return $this->answer($outcome, $document->public_id, 'saved', self::text($input['tanggal']));
        }

        $input = self::validated($request, [
            'command_id' => ['required', 'uuid'],
            'jenis' => ['required', Rule::in(['DOC-01', 'DOC-02', 'DOC-03', 'DOC-04', 'DOC-05', 'DOC-06', 'DOC-07', 'DOC-08', 'DOC-09', 'DOC-10', 'DOC-11', 'DOC-12', 'DOC-13', 'DOC-14'])],
            'mode' => ['nullable', Rule::in(['STANDARD', 'RECEIPT', 'PRE_PAYMENT'])],
            'subjek' => ['required', 'array:'.implode(',', DocumentSubject::COLUMNS)],
            'subjek.*' => ['required', 'string', 'uuid'],
        ]);
        $records = [];

        foreach ((array) $input['subjek'] as $column => $publicId) {
            $records[(string) $column] = self::text($publicId);
        }

        $type = self::text($input['jenis']);
        $mode = self::nullable($input['mode'] ?? null) ?? ($type === 'DOC-05' ? 'RECEIPT' : 'STANDARD');
        $subject = $this->documents->subjectOf($actor, $type, $mode, $records);
        $outcome = $drafts->prepare(self::text($input['command_id']), $actor, $subject);

        if ($outcome->kind !== Outcome::COMMITTED || $outcome->resultId === null) {
            Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => null, 'text' => $this->refusal($outcome, $type)]);

            return redirect()->back(303);
        }

        return redirect()->to('/dokumen/'.$this->documents->publicIdOf($outcome->resultId), 303);
    }

    /** ROUTE-222: the draft issued after its confirmation, with the entered fields the paper showed. */
    public function issue(Request $request, IssueDocument $issue, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $document = $this->documents->find($actor, $id);
        $input = self::validated($request, [...self::entered(), 'command_id' => ['required', 'uuid'], 'draf' => ['required', 'uuid'], 'lock_version' => ['required', 'integer', 'min:0'], 'isi' => ['nullable', 'string', 'size:64']]);
        $outcome = $issue->run(self::text($input['command_id']), $actor, $document, self::integer($input['lock_version']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::nullable($input['rekening'] ?? null), self::nullable($input['isi'] ?? null), self::text($input['draf']));

        return $this->answer($outcome, $document->public_id, 'issued', self::text($input['tanggal']), $document->document_type_code,
            $outcome->code === 'C-10' ? $this->documents->numberingOf($actor, $document) : null);
    }

    /** ROUTE-223: "Revisi" with its reason. */
    public function revise(Request $request, ReviseDocument $revise, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $document = $this->documents->find($actor, $id);
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'alasan' => ['required', 'string', 'min:3', 'max:500'], 'versi' => ['nullable', 'uuid']]);

        return $this->answer($revise->run(self::text($input['command_id']), $actor, $document, self::text($input['alasan']), self::nullable($input['versi'] ?? null)), $document->public_id, 'revised', BusinessDate::today());
    }

    /** ROUTE-224: "Batalkan Dokumen" with its reason. */
    public function void(Request $request, VoidDocument $void, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $document = $this->documents->find($actor, $id);
        $input = self::validated($request, ['command_id' => ['required', 'uuid'], 'alasan' => ['required', 'string', 'min:3', 'max:500'], 'versi' => ['nullable', 'uuid']]);

        return $this->answer($void->run(self::text($input['command_id']), $actor, $document, self::text($input['alasan']), self::nullable($input['versi'] ?? null)), $document->public_id, 'voided', BusinessDate::today());
    }

    /** ROUTE-225: an issued rendition inline, its document authorizing the viewer at request time (FL-07; DP-05). */
    public function rendition(Request $request, FileDownloads $files, string $id): HttpResponse
    {
        $download = $files->rendition($request->user() ?? throw new NotFoundHttpException, $id);

        return FileResponses::inline($download->stream, $download->mimeType, $download->size, $download->name);
    }

    /** @param  array{company: string, numbering: ?string}|null  $numbering  the C-10 refusal's company and, for the Owner, PG-061 */
    private function answer(Outcome $outcome, string $document, string $done, string $sent, ?string $type = null, ?array $numbering = null): RedirectResponse
    {
        $state = $outcome->state;
        $field = is_string($state['field'] ?? null) ? $state['field'] : null;

        if ($outcome->kind === Outcome::REJECTED && in_array($outcome->code, ['C-50', 'C-32'], true)) {
            throw ValidationException::withMessages([$field === 'bank_account' ? 'rekening' : 'tanggal' => __($outcome->code === 'C-50' ? 'documents.validation.future' : 'documents.validation.bank')]);
        }

        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, __("documents.answer.{$done}")],
            Outcome::REJECTED => [null, $this->refusal($outcome, $type, $numbering)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-01' => ['stale', __('messages.msg_03')],
                'ST-08' => ['stale', __('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                'BD-06' => ['recorded', __('documents.answer.already_issued', ['nomor' => self::text($state['number'] ?? '')])],
                'ST-02' => ['stale', __('documents.answer.state_changed')],
                default => ['recorded', __('messages.msg_04')],
            },
            default => ['not_processed', __('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'link' => $numbering['numbering'] ?? null]);

        return redirect()->to("/dokumen/{$document}", 303);
    }

    /** @param  array{company: string, numbering: ?string}|null  $numbering */
    private function refusal(Outcome $outcome, ?string $type, ?array $numbering = null): string
    {
        $state = $outcome->state;
        $name = $type === null ? '' : self::say('documents.'.strtolower(str_replace('-', '_', $type)));
        $period = ['jenis' => $name, 'periode' => self::text($state['periode'] ?? ''), 'perusahaan' => $numbering['company'] ?? ''];

        return match ($outcome->code) {
            'C-10' => self::say(($numbering['numbering'] ?? null) !== null ? 'documents.refusal.numbering_owner' : 'messages.msg_17', $period),
            'C-54' => self::say('documents.refusal.company_inactive'),
            'L-25', 'CM-23' => self::say('documents.refusal.corrected_at_record'),
            'NX-12' => self::say('documents.refusal.not_issued'),
            'WF-DOC-01' => self::say('documents.refusal.no_subject'),
            default => self::say('documents.refusal.precondition'),
        };
    }

    /** @return array<string, list<mixed>> */
    private static function entered(): array
    {
        return [
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'rekening' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Validates the named fields only — never the whole request (WS-01) —, each message from the catalogue.
     *
     * @param  array<string, list<mixed>|string>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('documents.validation.required'),
            'date_format' => __('documents.validation.date'),
            'min' => __('documents.validation.reason'),
            'max' => __('documents.validation.too_long'),
            'in' => __('documents.validation.choice'),
            'array' => __('documents.validation.choice'),
            'uuid' => __('documents.validation.choice'),
        ])->validate();

        return $valid;
    }

    /** @param  array<string, string>  $replace */
    private static function say(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }

    private static function query(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
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

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
