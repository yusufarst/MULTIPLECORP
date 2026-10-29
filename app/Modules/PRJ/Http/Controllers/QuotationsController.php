<?php

namespace App\Modules\PRJ\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PRJ\Actions\AnswerQuotation;
use App\Modules\PRJ\Actions\IssueQuotation;
use App\Modules\PRJ\Actions\ReviseQuotation;
use App\Modules\PRJ\Actions\SaveQuotationDraft;
use App\Modules\PRJ\Queries\QuotationList;
use App\Modules\PRJ\Queries\QuotationWorkspace;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The quotation cycle's pages and commands (WF-QUO-01; J-QUO-01): PG-014 *Penawaran* (ROUTE-014) in the document
 * composer, PG-037 *Daftar Penawaran* (ROUTE-037); "Simpan Draf" (ROUTE-154), "Terbitkan Penawaran" (ROUTE-155), "Buat
 * Revisi" (ROUTE-156) and "Catat Jawaban Klien" (ROUTE-157), each with its `command_id` (NX-03; CI-01) and its answer
 * in place as flash data (IP-01) — a refusal in words with the input kept (IP-03) —, the approval answered on PG-011
 * (NAV-014). An unknown project and one the viewer may not see answer alike (AZ-11).
 */
final class QuotationsController extends Controller
{
    public function __construct(
        private readonly QuotationWorkspace $workspace,
        private readonly QuotationList $quotations,
    ) {}

    /** ROUTE-037: PG-037, one company's revisions at a time. */
    public function index(Request $request): HttpResponse
    {
        return Inertia::render('PRJ/Quotations', $this->quotations->screen(self::actor($request), self::query($request, 'perusahaan'), self::query($request, 'setelah')))->toResponse($request);
    }

    /** ROUTE-014: PG-014, for `quotation.manage` with `finance.view` or for the client's answer `projects.confirm`. */
    public function show(Request $request, string $id): HttpResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->open($actor, $id);

        return Inertia::render('PRJ/Quotation', $this->workspace->screen($actor, $project))->toResponse($request);
    }

    /** ROUTE-154 "Simpan Draf": the project's first draft from its lines, or the draft's validity and lines at the version read. */
    public function save(Request $request, SaveQuotationDraft $save, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->open($actor, $id);
        $input = self::validated($request, [
            'revisi' => ['nullable', 'uuid'],
            'lock_version' => ['nullable', 'required_with:revisi', 'integer', 'min:0'],
            'berlaku' => ['nullable', 'date_format:Y-m-d'],
            'baris' => ['nullable', 'array', 'max:500'],
            'baris.*.baris' => ['required', 'string', 'max:64'],
            'baris.*.jumlah' => ['required', 'string', 'max:32'],
            'baris.*.harga' => ['nullable', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'baris.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        $revision = is_string($input['revisi'] ?? null) ? $input['revisi'] : null;
        $entered = is_array($input['baris'] ?? null) ? array_values($input['baris']) : [];
        $lines = $revision === null ? [] : $this->workspace->resolve($actor, $project, $revision, $entered);
        $validUntil = is_string($input['berlaku'] ?? null) ? $input['berlaku'] : null;

        if ($validUntil !== null && $validUntil < BusinessDate::today()) {
            throw ValidationException::withMessages(['berlaku' => self::say('quotations.validation.validity')]);
        }

        $outcome = $save->run(self::text($input['command_id']), $actor, $project, $revision, isset($input['lock_version']) ? (int) self::text($input['lock_version']) : null, $validUntil, $lines,
            array_diff_key($input, ['command_id' => true]));

        return $this->answer($actor, $project->public_id, $outcome, $revision === null ? 'drafted' : 'saved', BusinessDate::today());
    }

    /** ROUTE-155 "Terbitkan Penawaran" after its confirmation (IP-08): PG-014 with the issued revision and its number. */
    public function issue(Request $request, IssueQuotation $issue, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->open($actor, $id);
        $input = self::validated($request, ['revisi' => ['required', 'uuid'], 'lock_version' => ['required', 'integer', 'min:0'], 'tanggal' => ['required', 'date_format:Y-m-d'], 'tanggal_otomatis' => ['nullable', 'boolean'], 'isi' => ['required', 'string', 'size:64']]);
        $outcome = $issue->run(self::text($input['command_id']), $actor, $project, self::text($input['revisi']), (int) self::text($input['lock_version']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), self::text($input['isi']));

        return $this->answer($actor, $project->public_id, $outcome, 'issued', self::text($input['tanggal']));
    }

    /** ROUTE-156 "Buat Revisi": a new draft revision in place. */
    public function revise(Request $request, ReviseQuotation $revise, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->open($actor, $id);
        $input = self::validated($request, ['revisi' => ['required', 'uuid']]);

        return $this->answer($actor, $project->public_id, $revise->run(self::text($input['command_id']), $actor, $project, self::text($input['revisi'])), 'revised', BusinessDate::today());
    }

    /** ROUTE-157 "Catat Jawaban Klien" after its confirmation (IP-08): PG-011 confirmed, or the rejection in place. */
    public function answerClient(Request $request, AnswerQuotation $answer, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->open($actor, $id);
        $input = self::validated($request, [
            'revisi' => ['required', 'uuid'],
            'jawaban' => ['required', 'in:SETUJU,TOLAK'],
            'alasan' => ['nullable', 'required_if:jawaban,TOLAK', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $approve = $input['jawaban'] === 'SETUJU';
        $reason = is_string($input['alasan'] ?? null) ? trim($input['alasan']) : null;
        $outcome = $answer->run(self::text($input['command_id']), $actor, $project, self::text($input['revisi']), $approve, $reason === '' ? null : $reason, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($actor, $project->public_id, $outcome, $approve ? 'approved' : 'rejected', self::text($input['tanggal']));
    }

    /**
     * The command's answer as flash data: an approval on PG-011, anything else on PG-014; a future date at its field (C-50).
     */
    private function answer(int $actor, string $project, Outcome $outcome, string $done, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');
        $numbering = $outcome->code === 'C-10' ? $this->workspace->numberingOf($actor, $project) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("quotations.answer.{$done}")],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'C-10' => self::say(($numbering['numbering'] ?? null) !== null ? 'quotations.refusal.numbering_owner' : 'messages.msg_17',
                    ['jenis' => self::say('quotations.paper'), 'periode' => self::text($state['periode'] ?? ''), 'perusahaan' => $numbering['company'] ?? '']),
                'C-54' => self::say('projects.refusal.company_inactive'),
                'L-11' => self::say('projects.refusal.closed'),
                'L-21' => self::say('fulfilment.refusal.split'),
                'C-05' => self::say('fulfilment.refusal.executed', ['baris' => self::text($state['line'] ?? '')]),
                'C-31' => self::say('fulfilment.refusal.invoiced'),
                'L-25' => self::say($reason === 'expired' ? 'quotations.refusal.expired' : 'quotations.refusal.validity'),
                'WF-QUO-01' => self::say(in_array($reason, ['no_lines', 'not_sent'], true) ? "quotations.refusal.{$reason}" : 'quotations.refusal.no_draft'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-01' => ['stale', self::say(($state['reason'] ?? null) === 'facts' ? 'quotations.refusal.facts_changed' : 'messages.msg_03')],
                'ST-02' => ['stale', self::say('quotations.refusal.state_changed')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                'BD-06' => ['recorded', self::say('quotations.refusal.already_issued', ['nomor' => self::text($state['number'] ?? '')])],
                'BD-05' => ['recorded', self::say('quotations.refusal.already_answered')],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'link' => $numbering['numbering'] ?? null]);

        return redirect()->to($outcome->kind === Outcome::COMMITTED && $done === 'approved' ? "/proyek/{$project}" : "/proyek/{$project}/penawaran", 303);
    }

    /**
     * The command's named fields only, never the whole request (WS-01), each message from the catalogue.
     *
     * @param  array<string, list<string>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $rules = ['command_id' => ['required', 'uuid'], ...$rules];
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('projects.validation.required'),
            'required_with' => __('projects.validation.required'),
            'required_if' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'integer' => __('projects.validation.number'),
            'array' => __('projects.validation.choice'),
            'boolean' => __('projects.validation.choice'),
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
}
