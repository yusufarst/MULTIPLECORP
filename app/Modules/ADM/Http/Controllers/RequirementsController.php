<?php

namespace App\Modules\ADM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ADM\Actions\LinkRequirementEvidence;
use App\Modules\ADM\Actions\RelaxRequirement;
use App\Modules\ADM\Actions\SaveRequirement;
use App\Modules\ADM\Actions\SetWaiverEligibility;
use App\Modules\ADM\Actions\SupersedeDecision;
use App\Modules\ADM\Actions\WaiveRequirement;
use App\Modules\ADM\Queries\AdministrativeDocuments;
use App\Modules\ADM\Queries\ProjectChecklist;
use App\Modules\PRJ\Data\ProjectRef;
use App\Modules\PRJ\Queries\ProjectWorkspace;
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
 * PG-039 *Dokumen Administrasi* (ROUTE-039; NAV-039) and the checklist's commands in PG-011's "Kesiapan dokumen" or on
 * PG-039 (ROUTE-164–ROUTE-169; J-ADM-01): each command with its `command_id` (CI-01), its answer in place as flash data
 * on the page it came from (IP-01; NV-03) — a refusal in business words with the input kept (IP-03; IP-14). A project,
 * requirement, decision, document or evidence the actor may not see answers as unknown (AZ-11; CS-04). The Owner-only
 * fields never travel with an Admin's edit (OD-05; OD-06): the server decides either way (AZ-01).
 */
final class RequirementsController extends Controller
{
    public function __construct(
        private readonly ProjectWorkspace $projects,
        private readonly ProjectChecklist $checklist,
    ) {}

    /** ROUTE-039: PG-039, its branch, filters, sort and cursor in the address (IP-18). */
    public function index(Request $request, AdministrativeDocuments $documents): HttpResponse
    {
        $query = fn (string $key): ?string => is_string($request->query($key)) ? $request->query($key) : null;

        return Inertia::render('ADM/AdministrativeDocuments', $documents->screen(self::actor($request), [
            'cabang' => $query('cabang'), 'tab' => $query('tab'), 'status' => $query('status'), 'urut' => $query('urut'), 'setelah' => $query('setelah'),
        ]))->toResponse($request);
    }

    /** ROUTE-164: a requirement added, or one the page showed made stricter at the version it read. */
    public function store(Request $request, SaveRequirement $save, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->project($actor, $id);
        $input = self::validated($request, [
            'persyaratan' => ['nullable', 'string', 'size:32'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
            'jenis' => ['required', 'in:CATALOG_DOCUMENT,EXTERNAL_DOCUMENT,FREE_TEXT'],
            'tipe_dokumen' => ['nullable', 'required_if:jenis,CATALOG_DOCUMENT', 'string', 'max:10'],
            'tipe_bukti' => ['nullable', 'required_if:jenis,EXTERNAL_DOCUMENT', 'string', 'max:40'],
            'uraian' => ['required', 'string', 'min:3', 'max:500'],
            'wajib' => ['required', 'boolean'],
            'cara' => ['required', 'in:GENERATED,UPLOADED,EITHER'],
            'asli_klien' => ['required', 'boolean'],
        ]);
        $kind = self::text($input['jenis']);
        $documentType = $kind === 'CATALOG_DOCUMENT' ? self::text($input['tipe_dokumen'] ?? '') : null;
        $evidenceType = $kind === 'EXTERNAL_DOCUMENT' ? self::text($input['tipe_bukti'] ?? '') : null;

        if ($documentType !== null && ! SaveRequirement::knownType($documentType)) {
            throw ValidationException::withMessages(['tipe_dokumen' => self::say('adm.validation.type')]);
        }

        if ($evidenceType !== null && ! in_array($evidenceType, $this->checklist->evidenceTypes(), true)) {
            throw ValidationException::withMessages(['tipe_bukti' => self::say('adm.validation.type')]);
        }

        $handle = self::text($input['persyaratan'] ?? '');
        $requirement = $handle === '' ? null : ($this->checklist->requirementOf($project, $handle) ?? throw ValidationException::withMessages(['persyaratan' => self::say('adm.validation.unknown')]));
        // OD-05: an edit carrying waiver eligibility is refused by the command, whose fingerprint keeps what was sent.
        $payload = [...array_diff_key($input, ['command_id' => true]), ...($request->exists('kelayakan') ? ['kelayakan' => self::text($request->input('kelayakan'))] : [])];
        $outcome = $save->run(self::text($input['command_id']), $actor, $project, $requirement, isset($input['lock_version']) ? (int) self::text($input['lock_version']) : null, [
            'kind' => $kind, 'document_type' => $documentType, 'evidence_type' => $evidenceType, 'description' => trim(self::text($input['uraian'])),
            'required' => $request->boolean('wajib'), 'mode' => self::text($input['cara']), 'client_original' => $request->boolean('asli_klien'),
        ], $payload);

        return $this->answer($project, $outcome, $requirement === null ? 'added' : 'tightened');
    }

    /** ROUTE-165: the issued document or the evidence that satisfies the item linked. */
    public function link(Request $request, LinkRequirementEvidence $link, string $id, string $req): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->project($actor, $id);
        $requirement = $this->checklist->requirementOf($project, $req) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'dokumen' => ['nullable', 'required_without:bukti', 'prohibits:bukti', 'uuid'],
            'bukti' => ['nullable', 'required_without:dokumen', 'uuid'],
        ]);
        $document = self::text($input['dokumen'] ?? '');
        $evidence = self::text($input['bukti'] ?? '');
        $versionId = $document === '' ? null : ($this->checklist->versionOf($actor, $project, $document) ?? throw ValidationException::withMessages(['dokumen' => self::say('adm.validation.document')]));
        $evidenceId = $evidence === '' ? null : ($this->checklist->evidenceOf($actor, $project, $evidence) ?? throw ValidationException::withMessages(['bukti' => self::say('adm.validation.evidence')]));
        $outcome = $link->run(self::text($input['command_id']), $actor, $project, $requirement, $versionId, $evidenceId, $document === '' ? "bukti:{$evidence}" : "dokumen:{$document}");

        return $this->answer($project, $outcome, 'linked');
    }

    /** ROUTE-166 "Tandai Tidak Berlaku" with MSG-40's reason (IP-09). */
    public function waive(Request $request, WaiveRequirement $waive, string $id, string $req): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->project($actor, $id);
        $requirement = $this->checklist->requirementOf($project, $req) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['alasan' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->answer($project, $waive->run(self::text($input['command_id']), $actor, $project, $requirement, trim(self::text($input['alasan']))), 'waived');
    }

    /** ROUTE-167: the Owner's waiver eligibility at the version read (OD-05). */
    public function eligibility(Request $request, SetWaiverEligibility $set, string $id, string $req): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->project($actor, $id);
        $requirement = $this->checklist->requirementOf($project, $req) ?? throw new NotFoundHttpException;
        $input = self::validated($request, ['lock_version' => ['required', 'integer', 'min:0'], 'layak' => ['required', 'boolean']]);

        return $this->answer($project, $set->run(self::text($input['command_id']), $actor, $project, $requirement, (int) self::text($input['lock_version']), $request->boolean('layak')), 'eligibility');
    }

    /** ROUTE-168: the Owner's relaxation or removal with its reason (OD-06; IP-09). */
    public function relax(Request $request, RelaxRequirement $relax, string $id, string $req): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->project($actor, $id);
        $requirement = $this->checklist->requirementOf($project, $req) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'lock_version' => ['required', 'integer', 'min:0'],
            'hapus' => ['required', 'boolean'],
            'wajib' => ['required_if:hapus,false', 'boolean'],
            'cara' => ['required_if:hapus,false', 'in:GENERATED,UPLOADED,EITHER'],
            'asli_klien' => ['required_if:hapus,false', 'boolean'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $remove = $request->boolean('hapus');
        $outcome = $relax->run(self::text($input['command_id']), $actor, $project, $requirement, (int) self::text($input['lock_version']),
            $remove ? null : ['required' => $request->boolean('wajib'), 'mode' => self::text($input['cara'] ?? ''), 'client_original' => $request->boolean('asli_klien')], trim(self::text($input['alasan'])));

        return $this->answer($project, $outcome, $remove ? 'removed' : 'relaxed');
    }

    /** ROUTE-169 "Koreksi Keputusan": a waiver or a quotation approval superseded with its reason (CM-27; IP-09). */
    public function supersede(Request $request, SupersedeDecision $supersede, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, [
            'proyek' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $project = $this->project($actor, self::text($input['proyek']));
        [$kind, $decision] = $this->checklist->decisionOf($project, $id) ?? throw new NotFoundHttpException;

        if ($kind === SupersedeDecision::APPROVAL && ! isset($input['tanggal'])) {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.required')]);
        }

        $outcome = $supersede->run(self::text($input['command_id']), $actor, $project, $kind, $decision, trim(self::text($input['alasan'])),
            $kind === SupersedeDecision::APPROVAL ? self::text($input['tanggal']) : null, $request->boolean('tanggal_otomatis'));

        return $this->answer($project, $outcome, 'superseded', self::text($input['tanggal'] ?? BusinessDate::today()));
    }

    private function project(int $actor, string $publicId): ProjectRef
    {
        return $this->projects->reference($actor, $publicId) ?? throw new NotFoundHttpException;
    }

    /** The command's answer as flash data on the page it came from — PG-011 or PG-039 —; a future date at its field (C-50). */
    private function answer(ProjectRef $project, Outcome $outcome, string $done, ?string $sent = null): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("adm.answer.{$done}")],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'OD-05' => self::say('adm.refusal.eligibility'),
                'OD-06' => self::say('adm.refusal.relax'),
                'OD-15' => self::say('adm.refusal.owner_decision'),
                'BR-ADM-02' => self::say('adm.refusal.ineligible'),
                'BR-ADM-01' => self::say($reason === 'not_issued' ? 'adm.refusal.not_issued' : 'adm.refusal.mode'),
                'WF-ADM-01' => self::say(in_array($reason, ['satisfied', 'client_original', 'linked_document', 'invoice'], true) ? "adm.refusal.{$reason}" : 'messages.msg_04'),
                'NX-03' => self::say('adm.refusal.linked'),
                'L-11' => self::say('projects.refusal.closed'),
                'C-05' => self::say('fulfilment.refusal.executed', ['baris' => self::text($state['line'] ?? '')]),
                'C-31' => self::say('fulfilment.refusal.invoiced'),
                'L-21' => self::say('fulfilment.refusal.split'),
                'WF-PRJ-02' => self::say('fulfilment.refusal.details'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-01' => ['stale', self::say('adm.refusal.stale')],
                'ST-02' => ['stale', self::say('adm.refusal.changed')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent ?? BusinessDate::today(), 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text]);

        return redirect()->back(303, [], "/proyek/{$project->publicId}");
    }

    /**
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
            'required_if' => __('projects.validation.required'),
            'required_without' => __('adm.validation.target'),
            'prohibits' => __('adm.validation.target'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'integer' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
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
