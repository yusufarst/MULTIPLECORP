<?php

namespace App\Modules\INV\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\INV\Actions\Dispatch;
use App\Modules\INV\Actions\ReverseDispatch;
use App\Modules\INV\Actions\SubstituteDispatchIdentity;
use App\Modules\INV\Data\DispatchEntry;
use App\Modules\INV\Queries\DispatchQueries;
use App\Modules\INV\Services\DispatchRestoration;
use App\Support\Command\Outcome;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * PG-023 and PG-024's routes and commands (ROUTE-023; ROUTE-024; ROUTE-186; ROUTE-188; ROUTE-189): a thin edge over the actions and the query classes. A refusal answers at its line, the input kept (NV-04; IP-19); an unknown or out-of-scope record answers 404, alike (CS-04; AZ-11).
 */
final class DispatchController extends Controller
{
    private const QUANTITY = 'regex:/^\d{1,15}(\.\d{1,3})?$/';

    public function __construct(
        private readonly DispatchQueries $queries,
    ) {}

    /** ROUTE-023: each company its own group with its own error and retry (NAV-023), read together under one deferred prop. */
    public function worklist(Request $request): Response
    {
        $actor = self::actor($request);
        $all = $this->queries->groups($actor);
        $chosen = self::query($request, 'perusahaan');
        $groups = array_values(array_filter($all, fn (array $group): bool => $chosen === null || $group['id'] === $chosen));
        $closed = array_values(array_filter(explode(',', self::query($request, 'tutup') ?? ''), fn (string $id): bool => $id !== ''));
        $after = self::query($request, 'setelah');
        $only = self::query($request, 'kelompok');
        $strip = fn (array $group): array => array_diff_key($group, ['internal' => 1]);

        return Inertia::render('INV/DispatchWorklist', [
            'groups' => array_map($strip, $groups),
            'companies' => array_map($strip, $all),
            'chosen' => $chosen,
            'closed' => $closed,
            'worklists' => Inertia::defer(function () use ($actor, $groups, $closed, $only, $after): array {
                $pages = [];

                foreach ($groups as $group) {
                    if (! in_array($group['id'], $closed, true) && ($only === null || $only === $group['id'])) {
                        $pages[$group['id']] = self::group(fn (): array => $this->queries->worklist($actor, $group['internal'], $only === $group['id'] ? $after : null))();
                    }
                }

                return $pages;
            }),
        ]);
    }

    /** ROUTE-024: `{id}` the project; the line's lots on request (QB-05). */
    public function show(Request $request, string $id): Response
    {
        $actor = self::actor($request);
        $project = $this->queries->project($actor, $id) ?? throw new NotFoundHttpException;
        $line = self::query($request, 'baris');

        return Inertia::render('INV/DispatchGoods', [
            ...$this->queries->screen($actor, $project['id'], $project['company']),
            'choices' => Inertia::optional(fn (): ?array => $line === null ? null : $this->queries->choices($project['id'], $project['company'], $line)),
        ]);
    }

    /** ROUTE-186: `{id}` the project. */
    public function dispatch(Request $request, Dispatch $dispatch, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $project = $this->queries->project($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'baris' => ['required', 'string', 'size:32'],
            'satuan' => ['required', 'string', 'max:32'],
            'jumlah' => ['required', self::QUANTITY],
            'lot' => ['required', 'array', 'min:1', 'max:500'],
            'lot.*.lot' => ['required', 'string', 'size:32'],
            'lot.*.rak' => ['required', 'string', 'size:32'],
            'lot.*.jumlah' => ['required', self::QUANTITY],
            'lot.*.seri' => ['nullable', 'string', 'size:32'],
            'alasan_alokasi' => ['nullable', 'string', 'max:500'],
            'duplikat' => ['nullable', 'array', 'max:20'],
            'duplikat.*' => ['required', 'uuid'],
            'alasan_duplikat' => ['nullable', 'string', 'max:500'],
            'kebijakan_seri' => ['required', 'boolean'],
            'kebijakan_skala' => ['required', 'integer', 'min:0', 'max:3'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $line = $this->queries->line($project['id'], self::text($input['baris'])) ?? throw ValidationException::withMessages(['baris' => $unknown]);
        $unit = $this->queries->unit($line, self::text($input['satuan'])) ?? throw ValidationException::withMessages(['satuan' => $unknown]);
        $product = $this->queries->productOf($line);
        $picks = [];

        foreach (is_array($input['lot']) ? $input['lot'] : [] as $position => $pick) {
            $pick = is_array($pick) ? $pick : [];
            $lot = $this->queries->lot($product, self::text($pick['lot'] ?? '')) ?? throw ValidationException::withMessages(["lot.{$position}.lot" => $unknown]);
            $rack = $this->queries->location(self::text($pick['rak'] ?? '')) ?? throw ValidationException::withMessages(["lot.{$position}.rak" => $unknown]);
            $serialHandle = self::nullable($pick['seri'] ?? null);
            $serial = $serialHandle === null ? null : ($this->queries->serial($product, $serialHandle) ?? throw ValidationException::withMessages(["lot.{$position}.seri" => $unknown]));
            $picks[] = ['lot' => $lot, 'location' => $rack, 'qty' => self::text($pick['jumlah'] ?? ''), 'serial' => $serial];
        }

        $entry = new DispatchEntry($line, $unit, self::text($input['jumlah']), $picks, self::nullable($input['alasan_alokasi'] ?? null), self::strings($input['duplikat'] ?? []), self::nullable($input['alasan_duplikat'] ?? null), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), $request->boolean('kebijakan_seri'), (int) self::text($input['kebijakan_skala']));
        $outcome = $dispatch->run(self::text($input['command_id']), $actor, $project['company'], $entry, array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'dispatched', '/gudang/barang-keluar/'.$project['public_id'], self::text($input['tanggal']));
    }

    /** ROUTE-188: `{id}` the dispatch's movement. */
    public function reverse(Request $request, ReverseDispatch $reverse, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $dispatch = $this->queries->dispatch($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'jenis' => ['required', Rule::in(DispatchRestoration::KINDS)],
            'unit' => ['required', 'array', 'min:1', 'max:500'],
            'unit.*.konsumsi' => ['required', 'string', 'size:32'],
            'unit.*.jumlah' => ['required', self::QUANTITY],
            'unit.*.satuan_dasar' => ['nullable', 'boolean'],
            'unit.*.kondisi' => ['required', Rule::in(['USABLE', 'UNUSABLE'])],
            'unit.*.rak' => ['required', 'string', 'size:32'],
            'unit.*.seri' => ['nullable', 'string', 'size:32'],
            'reservasi' => ['nullable', self::QUANTITY],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'duplikat' => ['nullable', 'array', 'max:20'],
            'duplikat.*' => ['required', 'uuid'],
            'alasan_duplikat' => ['nullable', 'string', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $items = [];

        foreach (is_array($input['unit']) ? $input['unit'] : [] as $position => $unit) {
            $unit = is_array($unit) ? $unit : [];
            $consumption = $this->queries->consumption($dispatch['line'], self::text($unit['konsumsi'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$position}.konsumsi" => $unknown]);
            $rack = $this->queries->location(self::text($unit['rak'] ?? '')) ?? throw ValidationException::withMessages(["unit.{$position}.rak" => $unknown]);
            $serialHandle = self::nullable($unit['seri'] ?? null);
            $serial = $serialHandle === null ? null : ($this->queries->serial($dispatch['product'], $serialHandle) ?? throw ValidationException::withMessages(["unit.{$position}.seri" => $unknown]));
            $items[] = ['consumption' => $consumption, 'qty' => self::text($unit['jumlah'] ?? ''), 'base_unit' => filter_var($unit['satuan_dasar'] ?? false, FILTER_VALIDATE_BOOLEAN), 'condition' => self::text($unit['kondisi'] ?? ''), 'location' => $rack, 'serial' => $serial];
        }

        $outcome = $reverse->run(self::text($input['command_id']), $actor, $dispatch['company'], $dispatch['line'], self::text($input['jenis']), $items, self::nullable($input['reservasi'] ?? null), trim(self::text($input['alasan'])), self::strings($input['duplikat'] ?? []), self::nullable($input['alasan_duplikat'] ?? null), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'reversed', '/gudang/barang-keluar/'.$dispatch['project'], self::text($input['tanggal']));
    }

    /** ROUTE-189: `{id}` the dispatch's movement. */
    public function substitute(Request $request, SubstituteDispatchIdentity $substitute, string $id): RedirectResponse
    {
        $actor = self::actor($request);
        $dispatch = $this->queries->dispatch($actor, $id) ?? throw new NotFoundHttpException;
        $input = self::validated($request, [
            'konsumsi' => ['required', 'string', 'size:32'],
            'jumlah' => ['required', self::QUANTITY],
            'seri_tercatat' => ['nullable', 'string', 'size:32'],
            'lot' => ['required', 'string', 'size:32'],
            'rak' => ['required', 'string', 'size:32'],
            'seri_terkirim' => ['nullable', 'string', 'size:32'],
            'alasan_alokasi' => ['nullable', 'string', 'max:500'],
            'bukti' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_otomatis' => ['nullable', 'boolean'],
        ]);
        $unknown = self::say('projects.validation.unknown');
        $consumption = $this->queries->consumption($dispatch['line'], self::text($input['konsumsi'])) ?? throw ValidationException::withMessages(['konsumsi' => $unknown]);
        $lot = $this->queries->lot($dispatch['product'], self::text($input['lot'])) ?? throw ValidationException::withMessages(['lot' => $unknown]);
        $rack = $this->queries->location(self::text($input['rak'])) ?? throw ValidationException::withMessages(['rak' => $unknown]);
        $recordedHandle = self::nullable($input['seri_tercatat'] ?? null);
        $recorded = $recordedHandle === null ? null : ($this->queries->serial($dispatch['product'], $recordedHandle) ?? throw ValidationException::withMessages(['seri_tercatat' => $unknown]));
        $shippedHandle = self::nullable($input['seri_terkirim'] ?? null);
        $shipped = $shippedHandle === null ? null : ($this->queries->serial($dispatch['product'], $shippedHandle) ?? throw ValidationException::withMessages(['seri_terkirim' => $unknown]));
        $evidence = $this->queries->evidence($actor, $dispatch['company'], self::text($input['bukti'])) ?? throw ValidationException::withMessages(['bukti' => $unknown]);
        $outcome = $substitute->run(self::text($input['command_id']), $actor, $dispatch['company'], $consumption, self::text($input['jumlah']), $recorded, $lot, $rack, $shipped, self::nullable($input['alasan_alokasi'] ?? null), $evidence, trim(self::text($input['alasan'])), self::text($input['tanggal']), $request->boolean('tanggal_otomatis'), array_diff_key($input, ['command_id' => 1, 'tanggal_otomatis' => 1]));

        return $this->answer($outcome, 'substituted', '/gudang/barang-keluar/'.$dispatch['project'], self::text($input['tanggal']));
    }

    /** The command's answer as flash data, the input kept in place; a future date at its field (C-50; IP-14). */
    private function answer(Outcome $outcome, string $done, string $to, string $sent): RedirectResponse
    {
        if ($outcome->kind === Outcome::REJECTED && $outcome->code === 'C-50') {
            throw ValidationException::withMessages(['tanggal' => self::say('projects.validation.future')]);
        }

        $state = $outcome->state;
        $matches = $outcome->code === 'L-49' ? $this->queries->matches(self::strings($state['matches'] ?? [])) : null;
        [$cause, $text] = match ($outcome->kind) {
            Outcome::COMMITTED => [null, self::say("dispatch.answer.{$done}")],
            Outcome::REJECTED => [null, self::refusal($outcome, $matches)],
            Outcome::CONFLICT => match ($outcome->code) {
                'ST-09' => ['stale', self::say('receiving.refusal.policy')],
                'ST-08' => ['stale', self::say('messages.msg_16', ['tanggal_lama' => $sent, 'tanggal' => self::text($state['today'] ?? '')])],
                default => ['recorded', self::say('messages.msg_04')],
            },
            default => ['not_processed', self::say('messages.msg_06')],
        };
        Inertia::flash('answer', ['kind' => $outcome->kind, 'code' => $outcome->code, 'cause' => $cause, 'text' => $text, 'state' => [...array_intersect_key($state, ['reason' => 1, 'remainder' => 1, 'available' => 1, 'open' => 1]), ...($matches === null ? [] : ['matches' => $matches])]]);

        return redirect()->to($to, 303);
    }

    /** @param  list<array{id: string, label: string, href: null}>|null  $matches */
    private static function refusal(Outcome $outcome, ?array $matches): string
    {
        $state = $outcome->state;
        $reason = self::text($state['reason'] ?? '');

        return match ($outcome->code) {
            'PX-03' => $reason === 'unconfirmed' ? self::say('dispatch.refusal.unconfirmed') : self::say('dispatch.refusal.above_remainder', ['sisa' => self::text($state['remainder'] ?? '0')]),
            'C-04' => self::say('dispatch.refusal.supply', ['tersedia' => self::text($state['available'] ?? '0')]),
            'C-03' => $reason === 'picks' ? self::say('dispatch.refusal.picks') : self::say('dispatch.refusal.lot_short', ['sisa' => self::text($state['available'] ?? '0')]),
            'C-02' => self::say('dispatch.refusal.serial'),
            'C-09' => self::say('receiving.refusal.quantity'),
            'C-14' => self::say('projects.validation.unknown'),
            'C-07' => self::say('dispatch.refusal.same_product'),
            'C-27' => self::say('dispatch.refusal.above_open', ['sisa' => self::text($state['open'] ?? '0')]),
            'C-30' => self::say('dispatch.refusal.above_undelivered', ['sisa' => self::text($state['remainder'] ?? '0')]),
            'L-49' => self::say('messages.msg_15', ['daftar_catatan_yang_cocok' => implode('; ', array_column($matches ?? [], 'label'))]),
            'L-11' => self::say('dispatch.refusal.project_state'),
            'BR-INV-11' => self::say('dispatch.refusal.not_warehouse'),
            'BR-RSV-01', 'BR-RSV-03', 'AZ-01', 'C-05' => self::say('receiving.refusal.reservation'),
            'AZ-08' => $reason === 'allocation_reason' ? self::say('dispatch.refusal.allocation_reason') : self::say('messages.msg_14'),
            default => self::say('messages.msg_04'),
        };
    }

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
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private static function validated(Request $request, array $rules): array
    {
        $rules = ['command_id' => ['required', 'uuid'], ...$rules];
        $fields = array_values(array_unique(array_map(fn (string $field): string => explode('.', $field)[0], array_keys($rules))));

        /** @var array<string, mixed> $valid */
        $valid = Validator::make($request->only($fields), $rules, [
            'required' => __('projects.validation.required'),
            'uuid' => __('projects.validation.unknown'),
            'in' => __('projects.validation.choice'),
            'min' => __('projects.validation.too_short'),
            'max' => __('projects.validation.too_long'),
            'size' => __('projects.validation.unknown'),
            'date_format' => __('projects.validation.date'),
            'regex' => __('projects.validation.number'),
            'boolean' => __('projects.validation.choice'),
            'integer' => __('projects.validation.number'),
            'array' => __('projects.validation.choice'),
        ])->validate();

        return $valid;
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter(is_array($values) ? $values : [], is_string(...)));
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

        return is_int($id) ? $id : throw new AccessDeniedHttpException;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
