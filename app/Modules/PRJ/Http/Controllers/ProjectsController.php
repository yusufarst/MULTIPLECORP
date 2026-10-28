<?php

namespace App\Modules\PRJ\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PRJ\Actions\CreateProject;
use App\Modules\PRJ\Actions\DiscardDraftProject;
use App\Modules\PRJ\Actions\UpdateProject;
use App\Modules\PRJ\Queries\ProjectForm;
use App\Modules\PRJ\Queries\ProjectList;
use App\Modules\PRJ\Queries\ProjectWorkspace;
use App\Support\BusinessDate;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The project hub's pages and commands (WF-PRJ-01; J-PRJ-01): PG-010 *Proyek* (ROUTE-010) with its journey summary as a
 * deferred group (QB-01), PG-011 *Perjalanan Proyek* (ROUTE-011) with its three deferred groups (QB-02), PG-013
 * *Formulir Proyek* (ROUTE-013); "Buat Proyek" (ROUTE-151), "Ubah proyek" (ROUTE-152) and "Buang Draf" (ROUTE-153), each
 * with its `command_id` (NX-03; CI-01), its answer in place as flash data (IP-01) — a refusal in words with the input
 * kept (IP-03; IP-14) —, the success on the destination NAV-013 names. A deferred group's failure is its own error, the
 * page and the other groups staying (PT-19). An unknown project and one the viewer may not see answer alike (AZ-11).
 */
final class ProjectsController extends Controller
{
    public function __construct(
        private readonly ProjectList $projects,
        private readonly ProjectWorkspace $workspace,
        private readonly ProjectForm $form,
    ) {}

    /** ROUTE-010: PG-010, the page's rows at once and their journey summary deferred — whose request reads the page's rows only. */
    public function index(Request $request): HttpResponse
    {
        $actor = self::actor($request);
        $screen = self::memo(fn (): array => $this->projects->screen($actor, self::codes($request), self::query($request, 'status'), self::query($request, 'setelah')));

        return Inertia::render('PRJ/Projects', [
            ...self::lazy($screen, ['companies', 'filter', 'rows', 'more', 'can']),
            'journeys' => Inertia::defer(self::group(fn (): array => $this->projects->journeys($actor, self::ids($screen()['rows'] ?? []))), 'journey'),
        ])->toResponse($request);
    }

    /** ROUTE-011: PG-011, its header and track at once, its predicates, documents and history as three deferred groups. */
    public function show(Request $request, string $id): HttpResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->find($actor, $id);

        // The page's own props are read only by the page's load, never by a deferred group's request (QB-02).
        return Inertia::render('PRJ/Journey', [...self::lazy(self::memo(fn (): array => $this->workspace->screen($actor, $project)), ['project', 'journey', 'financial', 'can']),
            'predicates' => Inertia::defer(self::group(fn (): ?array => $this->workspace->predicates($actor, $project)), 'predicates'),
            'documents' => Inertia::defer(self::group(fn (): array => $this->workspace->documents($actor, $project)), 'documents'),
            'history' => Inertia::defer(self::group(fn (): array => $this->workspace->history($actor, $project)), 'history'),
        ])->toResponse($request);
    }

    /** ROUTE-013: PG-013 for a new project; `klien` and `produk` name the client and the product the form just chose. */
    public function create(Request $request): HttpResponse
    {
        return Inertia::render('PRJ/ProjectForm', $this->form->screen(self::actor($request), null, self::query($request, 'klien'), self::query($request, 'produk')))->toResponse($request);
    }

    /** ROUTE-013: PG-013 amending a project. */
    public function edit(Request $request, string $id): HttpResponse
    {
        $actor = self::actor($request);
        $project = $this->workspace->find($actor, $id);

        if (! $this->workspace->mayManage($actor, $project)) {
            throw new AccessDeniedHttpException;
        }

        return Inertia::render('PRJ/ProjectForm', $this->form->screen($actor, $project, self::query($request, 'klien'), self::query($request, 'produk')))->toResponse($request);
    }

    /** ROUTE-151 "Buat Proyek": PG-011 of the created project, its number drawn (NAV-013). */
    public function store(Request $request, CreateProject $create): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, false);
        $project = $this->form->resolve($this->form->viewer($actor), $input, null);
        $outcome = $create->run(self::text($input['command_id']), $actor, $project, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($actor, $outcome, '/proyek/baru', 'created', self::text($input['tanggal']), $project->companyId);
    }

    /** ROUTE-152 "Ubah proyek": PG-011 with the amendment, or of the new project a DRAFT's company change made. */
    public function update(Request $request, UpdateProject $update, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $input = self::validated($request, true);

        $current = $this->workspace->lookup($actor, $id);

        if ($current === null) {
            // A retry of a company change whose draft that very command discarded answers its recorded outcome (CI-05).
            $replayed = $update->replay(self::text($input['command_id']), $actor, $id, ProjectForm::payload($input), (int) self::text($input['lock_version']), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

            return $replayed === null ? throw new NotFoundHttpException : $this->answer($actor, $replayed, '/proyek', 'moved', self::text($input['tanggal']), 0);
        }

        $project = $this->form->resolve($this->form->viewer($actor), $input, $current);
        $outcome = $update->run(self::text($input['command_id']), $actor, $current, (int) self::text($input['lock_version']), $project, self::text($input['tanggal']), $request->boolean('tanggal_otomatis'));

        return $this->answer($actor, $outcome, "/proyek/{$current->public_id}/ubah", $project->companyId === $current->company_id ? 'amended' : 'moved', self::text($input['tanggal']), $project->companyId);
    }

    /** ROUTE-153 "Buang Draf": PG-010 once discarded, its number kept as a tombstone (L-37). */
    public function discard(Request $request, DiscardDraftProject $discard, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $input = Validator::make($request->only(['command_id']), ['command_id' => ['required', 'uuid']], ['required' => __('projects.validation.required')])->validate();

        $project = $this->workspace->lookup($actor, $id);

        if ($project === null) {
            // A retry of a discard that committed answers its recorded outcome, the draft being gone (CI-05).
            $replayed = $discard->replay(self::text($input['command_id']), $actor, $id) ?? throw new NotFoundHttpException;

            return $this->discarded($replayed, $this->workspace->retiredNumberOf($id) ?? '', $actor, $id, 0);
        }

        return $this->discarded($discard->run(self::text($input['command_id']), $actor, $project), $project->project_number, $actor, $project->public_id, $project->company_id);
    }

    /** "Buang Draf"'s answer: PG-010 once discarded, the refusal on PG-011. */
    private function discarded(Outcome $outcome, string $number, int $actor, string $publicId, int $companyId): RedirectResponse
    {
        if ($outcome->kind === Outcome::COMMITTED) {
            Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => null, 'text' => self::say('projects.answer.discarded', ['nomor' => $number])]);

            return redirect()->to('/proyek', 303);
        }

        return $this->answer($actor, $outcome, "/proyek/{$publicId}", 'discarded', BusinessDate::today(), $companyId);
    }

    /**
     * The command's answer as flash data: success on the project's journey, a refusal on the page it was sent from with
     * the input kept; a future date at its field (C-50).
     */
    private function answer(int $actor, Outcome $outcome, string $origin, string $done, string $sent, int $companyId): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $numbering = $outcome->code === 'C-10' ? $this->form->numberingOf($this->form->viewer($actor), $companyId) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("projects.answer.{$done}")],
            Outcome::REJECTED => [null, match ($outcome->code) {
                'C-10' => self::say(($numbering['numbering'] ?? null) !== null ? 'projects.refusal.numbering_owner' : 'messages.msg_17',
                    ['jenis' => self::say('glossary.gl_004'), 'periode' => self::text($state['periode'] ?? ''), 'perusahaan' => $numbering['company'] ?? '']),
                'C-54' => self::say('projects.refusal.company_inactive'),
                'L-20' => self::say(($state['reason'] ?? null) === 'referenced' ? 'projects.refusal.line_referenced' : 'projects.refusal.confirmed'),
                'L-21' => self::say('projects.refusal.company_locked'),
                'BR-PRJ-05' => self::say('projects.refusal.channel_locked'),
                'L-37' => self::say(($state['reason'] ?? null) === 'downstream' ? 'projects.refusal.downstream' : 'projects.refusal.not_draft'),
                'L-11' => self::say('projects.refusal.closed'),
                'XL-4' => self::say('projects.refusal.link'),
                default => self::say('messages.msg_04'),
            }],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-01' => ['stale', self::say('messages.msg_03')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? BusinessDate::today())])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'link' => $numbering['numbering'] ?? null]);

        if ($outcome->kind === Outcome::COMMITTED && $outcome->resultId !== null) {
            return redirect()->to('/proyek/'.$this->form->publicIdOf($outcome->resultId), 303);
        }

        return redirect()->to($origin, 303);
    }

    /**
     * The public identifiers of the page's rows.
     *
     * @return list<string>
     */
    private static function ids(mixed $rows): array
    {
        $ids = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && is_string($row['id'] ?? null)) {
                $ids[] = $row['id'];
            }
        }

        return $ids;
    }

    /**
     * A read run once, however many props take from it.
     *
     * @param  Closure(): array<string, mixed>  $read
     * @return Closure(): array<string, mixed>
     */
    private static function memo(Closure $read): Closure
    {
        $value = null;

        return function () use ($read, &$value): array {
            return $value ??= $read();
        };
    }

    /**
     * The page's props, each read only when the response includes it — a partial reload naming a deferred group skips them.
     *
     * @param  Closure(): array<string, mixed>  $screen
     * @param  list<string>  $keys
     * @return array<string, Closure(): mixed>
     */
    private static function lazy(Closure $screen, array $keys): array
    {
        $props = [];

        foreach ($keys as $key) {
            $props[$key] = fn (): mixed => $screen()[$key] ?? null;
        }

        return $props;
    }

    /**
     * A deferred group's read; its failure answers the group alone, so the page and the other groups stay (PT-19).
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return Closure(): (T|array{failed: true})
     */
    private static function group(Closure $read): Closure
    {
        return function () use ($read): mixed {
            try {
                return $read();
            } catch (Throwable $error) {
                Log::error('A deferred group failed.', ['exception' => $error::class]);

                return ['failed' => true];
            }
        };
    }

    /**
     * The form's named fields only, never the whole request (WS-01), each message from the catalogue.
     *
     * @return array<string, mixed>
     */
    private static function validated(Request $request, bool $amend): array
    {
        $decimal = 'regex:/^\d{1,16}(\.\d{1,2})?$/';
        $rules = [
            'command_id' => ['required', 'uuid'],
            'perusahaan' => ['required', 'uuid'],
            'klien' => ['required', 'uuid'],
            'unit' => ['nullable', 'string', 'max:128'],
            'alamat' => ['nullable', 'string', 'max:128'],
            'pic' => ['nullable', 'string', 'max:128'],
            'kanal' => ['required', 'in:REGULAR,SIPLAH'],
            'judul' => ['required', 'string', 'min:3', 'max:200'],
            'tenggat' => ['nullable', 'date_format:Y-m-d'],
            'tautan' => ['nullable', 'uuid'],
            'tautan_tetap' => ['nullable', 'boolean'],
            'alasan_tautan' => ['nullable', 'required_with:tautan', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
            'siplah' => ['nullable', 'array'],
            'siplah.external_reference' => ['nullable', 'string', 'max:200'],
            'siplah.program_code' => ['nullable', 'string', 'max:100'],
            'siplah.account_code' => ['nullable', 'string', 'max:100'],
            'siplah.notes' => ['nullable', 'string', 'max:2000'],
            'siplah.siplah_value' => ['nullable', $decimal],
            'siplah.real_purchase_value' => ['nullable', $decimal],
            'siplah.fee_amount' => ['nullable', $decimal],
            'siplah.va_fee_amount' => ['nullable', $decimal],
            'siplah.cashback_amount' => ['nullable', $decimal],
            'siplah.refund_amount' => ['nullable', $decimal],
            'baris' => ['nullable', 'array', 'max:'.ProjectForm::LINES],
            'baris.*.baris' => ['nullable', 'string', 'max:128'],
            'baris.*.produk' => ['required', 'uuid'],
            'baris.*.satuan' => ['required', 'string', 'max:32'],
            'baris.*.jumlah' => ['required', 'regex:/^\d{1,15}(\.\d{1,3})?$/'],
            'baris.*.harga' => ['nullable', $decimal],
            'baris.*.mode' => ['required', 'in:'.implode(',', ProjectForm::MODES)],
            'baris.*.keterangan' => ['nullable', 'string', 'max:500'],
            ...($amend ? ['lock_version' => ['required', 'integer', 'min:0']] : []),
        ];
        $fields = array_values(array_unique(array_map(fn (string $rule): string => explode('.', $rule)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('projects.validation.required'),
            'required_with' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'array' => __('projects.validation.choice'),
            'baris.max' => __('projects.validation.lines'),
        ])->validate();

        return $valid;
    }

    /** @return list<string> */
    private static function codes(Request $request): array
    {
        $value = $request->query('perusahaan');
        $codes = is_array($value) ? $value : (is_string($value) && $value !== '' ? explode(',', $value) : []);

        return array_values(array_filter(array_map(fn (mixed $code): string => is_string($code) ? strtoupper(trim($code)) : '', $codes), fn (string $code): bool => $code !== ''));
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
